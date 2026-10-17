<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $location
 * @property string|null $client_name
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string|null $budget
 * @property string $status
 * @property string|null $latitude
 * @property string|null $longitude
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['code', 'name', 'location', 'client_name', 'start_date', 'end_date', 'budget', 'latitude', 'longitude'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_PLANNING = 'planning';

    public const STATUS_ONGOING = 'ongoing';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_COMPLETED = 'completed';

    /**
     * Status => [label, Flux badge color].
     */
    public const STATUSES = [
        self::STATUS_PLANNING => ['Planning', 'zinc'],
        self::STATUS_ONGOING => ['Ongoing', 'green'],
        self::STATUS_ON_HOLD => ['On hold', 'amber'],
        self::STATUS_COMPLETED => ['Completed', 'blue'],
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PLANNING,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Projects workers can clock in to (PRD US-10).
     *
     * @param  Builder<Project>  $query
     */
    public function scopeOngoing(Builder $query): void
    {
        $query->where('status', self::STATUS_ONGOING);
    }

    /**
     * How much of the planned schedule has passed, 0–100, from the start and end dates.
     * Null when either date is missing. Whole days only, no floating point.
     */
    public function scheduleProgress(?Carbon $today = null): ?int
    {
        if ($this->start_date === null || $this->end_date === null) {
            return null;
        }

        $today = ($today ?? now())->startOfDay();
        $totalDays = (int) $this->start_date->diffInDays($this->end_date);

        if ($totalDays <= 0 || $today->lessThan($this->start_date)) {
            return $today->lessThan($this->start_date) ? 0 : 100;
        }

        $elapsedDays = (int) $this->start_date->diffInDays(min($today, $this->end_date));

        return intdiv($elapsedDays * 100, $totalDays);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    /**
     * Badge color: planning gray, ongoing green, on hold amber, completed blue.
     */
    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'zinc';
    }
}
