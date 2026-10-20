<?php

namespace App\Models;

use App\Services\UsernameService;
use App\Services\UserRateService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $employee_no
 * @property string $first_name
 * @property string $last_name
 * @property string $name
 * @property string $username
 * @property string|null $email
 * @property string|null $position
 * @property string $hourly_rate
 * @property string $status
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $must_change_password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['employee_no', 'first_name', 'last_name', 'name', 'email', 'position', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const ROLES = ['admin', 'staff', 'worker'];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'hourly_rate' => '0.00',
        'status' => self::STATUS_ACTIVE,
        'must_change_password' => false,
    ];

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty(['first_name', 'last_name'])) {
                $user->name = trim("{$user->first_name} {$user->last_name}");
            }
        });

        static::creating(function (User $user) {
            // Validate the rate before the insert so a bad value never leaves a user without history.
            $user->hourly_rate = UserRateService::normalize((string) ($user->hourly_rate ?? '0'));

            if (blank($user->username)) {
                $user->username = app(UsernameService::class)
                    ->generate((string) $user->first_name, (string) $user->last_name);
            }
        });

        static::created(function (User $user) {
            $changedBy = Auth::user();

            app(UserRateService::class)->recordInitialRate(
                $user,
                $changedBy instanceof User ? $changedBy : null,
            );
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'hourly_rate' => 'decimal:2',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * @return HasMany<UserRateHistory, $this>
     */
    public function rateHistories(): HasMany
    {
        return $this->hasMany(UserRateHistory::class);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * The user's role for display, e.g. "Staff". Empty when no role is assigned.
     */
    public function roleLabel(): string
    {
        return ucfirst((string) $this->getRoleNames()->first());
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
