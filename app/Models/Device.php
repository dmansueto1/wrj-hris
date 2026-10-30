<?php

namespace App\Models;

use App\Support\UserAgent;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A browser on a phone, tablet or computer that asked to use the kiosk or Clock In/Out (T-11.1).
 * Written by DeviceService only, never mass-assigned.
 *
 * @property int $id
 * @property string $token_hash
 * @property string $code
 * @property string|null $name
 * @property string $status
 * @property string $requested_from
 * @property int|null $requested_by
 * @property Carbon $requested_at
 * @property Carbon $last_seen_at
 * @property string $browser
 * @property string $platform
 * @property string $user_agent
 * @property string $ip_address
 * @property int|null $last_user_id
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_BLOCKED = 'blocked';

    /**
     * On-screen label and badge color per status.
     */
    public const STATUSES = [
        self::STATUS_PENDING => ['Pending', 'amber'],
        self::STATUS_APPROVED => ['Approved', 'green'],
        self::STATUS_BLOCKED => ['Blocked', 'red'],
    ];

    /**
     * Where the request came from, as shown on the Devices page.
     */
    public const SOURCES = [
        'kiosk' => 'Kiosk',
        'clock' => 'Clock In/Out',
    ];

    /**
     * Device type label and icon, worked out from the saved user agent.
     */
    public const TYPES = [
        'phone' => ['Phone', 'device-phone-mobile'],
        'tablet' => ['Tablet', 'device-tablet'],
        'computer' => ['Computer', 'laptop'],
        'unknown' => ['Unknown', 'question-mark-circle'],
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lastUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    /**
     * Badge color: Approved green, Pending amber, Blocked red.
     */
    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'zinc';
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->requested_from] ?? ucfirst($this->requested_from);
    }

    /**
     * phone, tablet, computer or unknown.
     */
    public function type(): string
    {
        return UserAgent::type($this->user_agent);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type()][0];
    }

    public function typeIcon(): string
    {
        return self::TYPES[$this->type()][1];
    }

    /**
     * "Chrome on Android".
     */
    public function description(): string
    {
        return __(':browser on :platform', ['browser' => $this->browser, 'platform' => $this->platform]);
    }
}
