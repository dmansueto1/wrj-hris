<?php

namespace App\Models;

use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One clock-in/clock-out session (PRD 9). Rows are written by AttendanceService, never mass-assigned.
 *
 * @property int $id
 * @property int $user_id
 * @property int $project_id
 * @property Carbon $work_date
 * @property Carbon $clock_in_at
 * @property Carbon|null $clock_out_at
 * @property string $hourly_rate
 * @property int|null $minutes_worked
 * @property string|null $hours_worked
 * @property string|null $labor_cost
 * @property string $status
 * @property string|null $clock_in_lat
 * @property string|null $clock_in_lng
 * @property string|null $clock_out_lat
 * @property string|null $clock_out_lng
 * @property string|null $clock_in_photo_path
 * @property string|null $clock_out_photo_path
 * @property int|null $adjusted_by
 * @property string|null $adjustment_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ADJUSTED = 'adjusted';

    public const STATUS_FLAGGED = 'flagged';

    public const STATUS_VOID = 'void';

    /**
     * Sessions whose labor cost counts toward project expenses (PRD 7.4 rule 3).
     */
    public const COUNTED_STATUSES = [self::STATUS_CLOSED, self::STATUS_ADJUSTED];

    /**
     * On-screen label and badge color per status (T-11). Never "session" on screen.
     */
    public const STATUSES = [
        self::STATUS_OPEN => ['Clocked In', 'green'],
        self::STATUS_CLOSED => ['Clocked Out', 'zinc'],
        self::STATUS_ADJUSTED => ['Adjusted', 'indigo'],
        self::STATUS_FLAGGED => ['Flagged', 'red'],
        self::STATUS_VOID => ['Void', 'zinc'],
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_OPEN,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'hourly_rate' => 'decimal:2',
            'minutes_worked' => 'integer',
            'hours_worked' => 'decimal:2',
            'labor_cost' => 'decimal:2',
            'clock_in_lat' => 'decimal:7',
            'clock_in_lng' => 'decimal:7',
            'clock_out_lat' => 'decimal:7',
            'clock_out_lng' => 'decimal:7',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Includes archived projects, so past sessions always show where the work was done.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    /**
     * Badge color: Clocked In green and Clocked Out gray (as on the clock screens and kiosk), Adjusted purple, Flagged red, Void gray.
     */
    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'zinc';
    }

    /**
     * Seconds since clock-in while Clocked In (for the running worked time); null otherwise.
     */
    public function runningSeconds(): ?int
    {
        if ($this->status !== self::STATUS_OPEN) {
            return null;
        }

        return max(0, (int) $this->clock_in_at->diffInSeconds(now()));
    }

    /**
     * The running worked time as "2h 05m 09s" (the same format the runningTime Alpine helper
     * ticks), or null when not Clocked In.
     */
    public function runningTimeLabel(): ?string
    {
        $seconds = $this->runningSeconds();

        if ($seconds === null) {
            return null;
        }

        return sprintf('%dh %02dm %02ds', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /**
     * How many calendar days after the work date the clock-out falls: 0 the same day, 1 after
     * midnight, and so on. 0 while Clocked In.
     */
    public function clockOutDayOffset(): int
    {
        if ($this->clock_out_at === null) {
            return 0;
        }

        return (int) $this->work_date->copy()->startOfDay()->diffInDays($this->clock_out_at->copy()->startOfDay());
    }

    /**
     * The session a user is currently clocked in to. Flagged sessions are not open (T-13).
     *
     * @param  Builder<Attendance>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Sessions that count toward labor cost: closed or adjusted.
     *
     * @param  Builder<Attendance>  $query
     */
    public function scopeCounted(Builder $query): void
    {
        $query->whereIn('status', self::COUNTED_STATUSES);
    }
}
