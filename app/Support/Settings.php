<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * System values from the `settings` table (PRD section 9), changed by the Admin on System
 * Settings (T-14.1). A key without a row uses its SettingsCatalog default. All rows are cached
 * (forgotten on set()) and kept for the rest of the request.
 */
class Settings
{
    public const CACHE_KEY = 'settings';

    /** Container key of this request's copy of the rows. */
    public const MEMO = 'settings.values';

    /** Container key of the test overrides (fake()). */
    private const FAKE = 'settings.fake';

    /**
     * The stored value; without a row, `$default`, or else the catalog default.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $fake = self::fakes();

        if (array_key_exists($key, $fake)) {
            return $fake[$key];
        }

        return self::rows()[$key] ?? $default ?? SettingsCatalog::find($key)['default'] ?? null;
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function string(string $key): string
    {
        return (string) self::get($key);
    }

    /**
     * Save a value and forget the cached rows. Drops a test override of that key.
     */
    public static function set(string $key, string $value): void
    {
        DB::table('settings')->upsert([['key' => $key, 'value' => $value]], ['key'], ['value']);

        self::forget();

        $fake = self::fakes();
        unset($fake[$key]);
        app()->instance(self::FAKE, $fake);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        app()->forgetInstance(self::MEMO);
    }

    /**
     * Tests only: override values without the database (Tests\TestCase turns photos and approved
     * devices off this way).
     *
     * @param  array<string, string>  $values
     */
    public static function fake(array $values): void
    {
        app()->instance(self::FAKE, [...self::fakes(), ...$values]);
    }

    public static function clearFake(): void
    {
        app()->instance(self::FAKE, []);
    }

    /**
     * A percent with at most 2 decimals as a rate with 4 ("12.5" → "0.1250"). Hundredths of a
     * percent and ten-thousandths of the rate are the same integer, so no floats are needed.
     */
    public static function percentToRate(string $percent): string
    {
        if (! preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', trim($percent), $parts)) {
            throw new InvalidArgumentException("Invalid percent [{$percent}].");
        }

        $tenThousandths = (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');

        return intdiv($tenThousandths, 10000).'.'.str_pad((string) ($tenThousandths % 10000), 4, '0', STR_PAD_LEFT);
    }

    /**
     * A rate with at most 4 decimals as a percent, trailing zeros dropped ("0.1250" → "12.5").
     */
    public static function rateToPercent(string $rate): string
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,4}))?$/', trim($rate), $parts)) {
            throw new InvalidArgumentException("Invalid rate [{$rate}].");
        }

        $hundredths = (int) $parts[1] * 10000 + (int) str_pad($parts[2] ?? '', 4, '0');
        $decimals = rtrim(str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT), '0');

        return intdiv($hundredths, 100).($decimals === '' ? '' : '.'.$decimals);
    }

    /**
     * @return array<string, string>
     */
    private static function rows(): array
    {
        if (! app()->bound(self::MEMO)) {
            app()->instance(self::MEMO, Cache::rememberForever(
                self::CACHE_KEY,
                fn () => DB::table('settings')->pluck('value', 'key')->map(fn ($value) => (string) $value)->all(),
            ));
        }

        return app(self::MEMO);
    }

    /**
     * @return array<string, string>
     */
    private static function fakes(): array
    {
        return app()->bound(self::FAKE) ? app(self::FAKE) : [];
    }
}
