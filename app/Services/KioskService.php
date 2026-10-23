<?php

namespace App\Services;

use App\Actions\Fortify\AuthenticateUser;
use App\Models\Attendance;
use App\Models\User;
use App\Support\Auth\LoginRateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The shared site kiosk (T-10.1): checks a worker's username and password without logging them in,
 * and lists today's latest clock events so workers can confirm their own entry.
 */
class KioskService
{
    public function __construct(
        private LoginRateLimiter $limiter,
        private AuthenticateUser $authenticate,
    ) {}

    /**
     * Check the credentials with the same rules and the same 15-minute lockout as the login page.
     * Never logs the user in.
     *
     * @throws ValidationException on a lockout, wrong credentials, an inactive account or no clock permission
     */
    public function verify(string $username, string $password, string $ip): User
    {
        // The lockout key is "username|ip", the same as the login page, so both share one lockout.
        $request = Request::create('/kiosk', 'POST', ['username' => $username, 'password' => $password], server: ['REMOTE_ADDR' => $ip]);

        if ($this->limiter->tooManyAttempts($request)) {
            $minutes = max(1, (int) ceil($this->limiter->availableIn($request) / 60));

            throw ValidationException::withMessages([
                'username' => [trans_choice('auth.lockout', $minutes, ['minutes' => $minutes])],
            ]);
        }

        $user = ($this->authenticate)($request);

        if ($user === null) {
            $this->limiter->increment($request);

            throw ValidationException::withMessages(['username' => [__('auth.failed')]]);
        }

        $this->limiter->clear($request);

        if (! $user->can('attendance.clock')) {
            throw ValidationException::withMessages(['username' => [__('Your account cannot clock in. Please contact the administrator.')]]);
        }

        return $user;
    }

    /**
     * Today's latest clock events across all users, newest first: one "Clocked In" row per clock-in
     * and one "Clocked Out" row per clock-out that happened today. Void duties are left out.
     *
     * @return Collection<int, array{key: string, direction: string, name: string, initials: string, type: string, project: string, time: string}>
     */
    public function recentDuties(int $limit = 10): Collection
    {
        $startOfToday = now()->startOfDay();

        // A duty's newest event is its clock-out (or clock-in while still clocked in), so the newest $limit
        // duties by that time always contain the newest $limit events.
        $duties = Attendance::query()
            ->with(['user' => fn ($query) => $query->withTrashed(), 'project'])
            ->where('status', '!=', Attendance::STATUS_VOID)
            ->where(fn ($query) => $query->where('clock_in_at', '>=', $startOfToday)->orWhere('clock_out_at', '>=', $startOfToday))
            ->orderByRaw('COALESCE(clock_out_at, clock_in_at) DESC')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        /** @var list<array{key: string, direction: string, name: string, initials: string, type: string, project: string, time: string, sort: string}> $events */
        $events = [];

        foreach ($duties as $duty) {
            $name = self::nameFor($duty->user->first_name, $duty->user->last_name);

            if ($duty->clock_in_at->greaterThanOrEqualTo($startOfToday)) {
                $events[] = $this->event($duty, $name, 'in', __('Clocked In'), $duty->clock_in_at);
            }

            if ($duty->clock_out_at !== null && $duty->clock_out_at->greaterThanOrEqualTo($startOfToday)) {
                $events[] = $this->event($duty, $name, 'out', __('Clocked Out'), $duty->clock_out_at);
            }
        }

        usort($events, fn (array $a, array $b) => strcmp($b['sort'], $a['sort']));

        $rows = [];
        foreach (array_slice($events, 0, $limit) as $event) {
            unset($event['sort']);
            $rows[] = $event;
        }

        return collect($rows);
    }

    /**
     * How many people are clocked in right now (open duties), for the kiosk's live counter.
     */
    public function onDutyCount(): int
    {
        return Attendance::query()->open()->count();
    }

    /**
     * First name and last initial for the public kiosk screen ("Juan Dela Cruz" → "Juan D.").
     */
    public static function nameFor(string $firstName, string $lastName): string
    {
        $first = trim($firstName);
        $initial = Str::upper(mb_substr(trim($lastName), 0, 1));

        return $initial === '' ? $first : "{$first} {$initial}.";
    }

    /**
     * @return array{key: string, direction: string, name: string, initials: string, type: string, project: string, time: string, sort: string}
     */
    private function event(Attendance $duty, string $name, string $direction, string $type, \DateTimeInterface $at): array
    {
        return [
            'key' => "{$duty->id}-{$direction}",
            'direction' => $direction,
            'name' => $name,
            'initials' => $duty->user->initials(),
            'type' => $type,
            'project' => $duty->project->name,
            'time' => $at->format('g:i A'),
            // Same minute: clock-out sorts above clock-in, then newer duties first.
            'sort' => $at->format('YmdHi').($direction === 'out' ? '1' : '0').str_pad((string) $duty->id, 10, '0', STR_PAD_LEFT),
        ];
    }
}
