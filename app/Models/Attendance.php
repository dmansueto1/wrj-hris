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
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
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
