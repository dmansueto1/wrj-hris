<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One weekday of a user's shift (T-11.2). Written by UserShiftService only, never mass-assigned.
 * Times read back as "H:i" ("08:00"), or null.
 *
 * @property int $id
 * @property int $user_id
 * @property int $weekday
 * @property bool $is_rest_day
 * @property string|null $first_in
 * @property string|null $first_out
 * @property string|null $second_in
 * @property string|null $second_out
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserShift extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_rest_day' => 'boolean',
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
     * @return Attribute<string|null, string|null>
     */
    protected function firstIn(): Attribute
    {
        return $this->time();
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function firstOut(): Attribute
    {
        return $this->time();
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function secondIn(): Attribute
    {
        return $this->time();
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function secondOut(): Attribute
    {
        return $this->time();
    }

    /**
     * MySQL TIME comes back as "08:00:00"; the app works with "08:00".
     *
     * @return Attribute<string|null, string|null>
     */
    private function time(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : substr($value, 0, 5),
            set: fn (?string $value) => $value === null || $value === '' ? null : $value,
        );
    }
}
