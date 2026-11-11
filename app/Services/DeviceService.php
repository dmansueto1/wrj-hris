<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use App\Support\Settings;
use App\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Approved devices for the kiosk and Clock In/Out (T-11.1). A web page cannot read a device's
 * hardware ID, so a device is a browser remembered by the long-lived `wrj_device` cookie. Only a
 * SHA-256 hash of the cookie's token is stored.
 */
class DeviceService
{
    public const COOKIE = 'wrj_device';

    /** Five years, renewed on every visit. */
    private const COOKIE_MINUTES = 60 * 24 * 365 * 5;

    /** No look-alikes: no 0/O and no 1/I. */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const REQUESTS_PER_HOUR = 5;

    public const STATE_ALLOWED = 'allowed';

    public const STATE_NEW = 'new';

    public const STATE_PENDING = 'pending';

    public const STATE_BLOCKED = 'blocked';

    public static function required(): bool
    {
        return Settings::bool('attendance_require_approved_device');
    }

    /**
     * A new short code such as "K7F-29Q", shown on the device so the Admin can find its request.
     */
    public static function newCode(): string
    {
        $pick = fn (): string => self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];

        return $pick().$pick().$pick().'-'.$pick().$pick().$pick();
    }

    /**
     * This browser's device, or null when it never asked, its token is unknown, or it was archived.
     * Creates nothing. A found device's last-seen time is refreshed (at most every 5 minutes) and its
     * cookie is sent again so the 5-year expiry renews on each visit.
     */
    public function find(Request $request): ?Device
    {
        // Looked up once per request: render and the action guards all ask.
        if ($request->attributes->has('device')) {
            return $request->attributes->get('device');
        }

        $device = $this->lookUp($request);
        $request->attributes->set('device', $device);

        return $device;
    }

    private function lookUp(Request $request): ?Device
    {
        $token = $this->token($request);

        if ($token === null) {
            return null;
        }

        $device = Device::query()->where('token_hash', hash('sha256', $token))->first();

        if ($device === null) {
            return null;
        }

        if ($device->last_seen_at->lt(now()->subMinutes(5))) {
            $device->forceFill(['last_seen_at' => now(), 'ip_address' => (string) $request->ip()])->save();
        }

        $this->queueCookie($token);

        return $device;
    }

    /**
     * allowed, new, pending or blocked. Always allowed while the setting is off.
     */
    public function state(Request $request): string
    {
        if (! self::required()) {
            return self::STATE_ALLOWED;
        }

        return match ($this->find($request)?->status) {
            Device::STATUS_APPROVED => self::STATE_ALLOWED,
            Device::STATUS_PENDING => self::STATE_PENDING,
            Device::STATUS_BLOCKED => self::STATE_BLOCKED,
            default => self::STATE_NEW,
        };
    }

    /**
     * "Request access": save this browser as a pending device. A browser that already has a
     * device (pending, approved or blocked) keeps it, so a blocked device cannot start over and
     * an approved one cannot lose its approval. At most 5 new requests per IP address per hour.
     */
    public function requestAccess(Request $request, string $from, ?User $requestedBy): Device
    {
        $existing = $this->find($request);

        if ($existing !== null) {
            return $existing;
        }

        $key = 'device-request:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::REQUESTS_PER_HOUR)) {
            throw ValidationException::withMessages([
                'device' => __('Too many requests from this network. Try again later.'),
            ]);
        }

        RateLimiter::hit($key, 3600);

        $token = Str::random(40);
        $agent = (string) $request->userAgent();
        ['browser' => $browser, 'platform' => $platform] = UserAgent::describe($agent);

        $device = new Device;
        $device->forceFill([
            'token_hash' => hash('sha256', $token),
            'code' => $this->uniqueCode(),
            'status' => Device::STATUS_PENDING,
            'requested_from' => $from,
            'requested_by' => $requestedBy?->id,
            'requested_at' => now(),
            'last_seen_at' => now(),
            'browser' => $browser,
            'platform' => $platform,
            'user_agent' => Str::limit($agent, 1000, ''),
            'ip_address' => (string) $request->ip(),
        ])->save();

        $this->queueCookie($token);
        $request->attributes->set('device', $device);

        return $device;
    }

    public function approve(Device $device, string $name, User $admin): void
    {
        $this->change($device, $admin, 'Approved device', [
            'status' => Device::STATUS_APPROVED,
            'name' => $name,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
    }

    public function rename(Device $device, string $name, User $admin): void
    {
        $this->change($device, $admin, 'Renamed device', ['name' => $name]);
    }

    public function block(Device $device, User $admin): void
    {
        $this->change($device, $admin, 'Blocked device', ['status' => Device::STATUS_BLOCKED]);
    }

    public function archive(Device $device, User $admin): void
    {
        DB::transaction(function () use ($device, $admin) {
            $device->delete();

            activity('devices')
                ->performedOn($device)
                ->causedBy($admin)
                ->withProperties(['old' => ['status' => $device->status], 'new' => ['archived' => true]])
                ->log('Archived device');
        });
    }

    /**
     * Who last clocked in or out on this device.
     */
    public function recordUser(Request $request, User $user): void
    {
        $this->find($request)?->forceFill(['last_user_id' => $user->id])->save();
    }

    /**
     * Archive pending requests nobody approved within the days set in System Settings (30 by
     * default). Returns how many.
     */
    public function prunePending(): int
    {
        $count = 0;

        Device::query()
            ->where('status', Device::STATUS_PENDING)
            ->where('last_seen_at', '<', now()->subDays(Settings::int('device_prune_pending_days')))
            ->each(function (Device $device) use (&$count) {
                $device->delete();
                $count++;
            });

        return $count;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function change(Device $device, User $admin, string $description, array $changes): void
    {
        DB::transaction(function () use ($device, $admin, $description, $changes) {
            $old = array_intersect_key($device->only(['status', 'name']), $changes);

            $device->forceFill($changes)->save();

            activity('devices')
                ->performedOn($device)
                ->causedBy($admin)
                ->withProperties(['old' => $old, 'new' => array_intersect_key($device->only(['status', 'name']), $changes)])
                ->log($description);
        });
    }

    /**
     * The cookie token sent by the browser, or the one queued earlier in this same request
     * (right after "Request access", before the browser has it).
     */
    private function token(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE) ?? Cookie::queued(self::COOKIE)?->getValue();

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function queueCookie(string $token): void
    {
        Cookie::queue(Cookie::make(self::COOKIE, $token, self::COOKIE_MINUTES, httpOnly: true, sameSite: 'lax'));
    }

    private function uniqueCode(): string
    {
        do {
            $code = self::newCode();
        } while (Device::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
