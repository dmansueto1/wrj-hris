<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UserRateService
{
    /**
     * Change the user's hourly rate and record it in the rate history.
     * Does nothing when the rate is unchanged.
     */
    public function changeRate(User $user, string $newRate, User $changedBy): void
    {
        $newRate = self::normalize($newRate);

        DB::transaction(function () use ($user, $newRate, $changedBy) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->hourly_rate === $newRate) {
                return;
            }

            $locked->forceFill(['hourly_rate' => $newRate])->save();

            $locked->rateHistories()->create([
                'hourly_rate' => $newRate,
                'effective_from' => now()->toDateString(),
                'changed_by' => $changedBy->id,
            ]);

            $user->forceFill(['hourly_rate' => $newRate])->syncOriginalAttribute('hourly_rate');
        });
    }

    /**
     * Record the rate a user starts with. Called when the user is created.
     */
    public function recordInitialRate(User $user, ?User $changedBy = null): void
    {
        $user->rateHistories()->create([
            'hourly_rate' => self::normalize((string) ($user->hourly_rate ?? '0')),
            'effective_from' => now()->toDateString(),
            'changed_by' => $changedBy?->id,
        ]);
    }

    /**
     * Normalize a peso amount string to two decimals ("80" → "80.00") without using floats.
     */
    public static function normalize(string $rate): string
    {
        if (! preg_match('/^(\d{1,8})(?:\.(\d{1,2}))?$/', trim($rate), $parts)) {
            throw new InvalidArgumentException("Invalid hourly rate [{$rate}].");
        }

        $pesos = ltrim($parts[1], '0') ?: '0';
        $centavos = str_pad($parts[2] ?? '', 2, '0');

        return "{$pesos}.{$centavos}";
    }
}
