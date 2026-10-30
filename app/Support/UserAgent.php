<?php

namespace App\Support;

/**
 * A short, readable browser and platform name from a User-Agent header, for the Devices page.
 * Deliberately simple: no fingerprinting and no extra package.
 */
class UserAgent
{
    /**
     * @return array{browser: string, platform: string}
     */
    public static function describe(string $userAgent): array
    {
        return [
            'browser' => self::browser($userAgent),
            'platform' => self::platform($userAgent),
        ];
    }

    /**
     * phone, tablet, computer or unknown. A User-Agent does not say laptop or desktop, so both are
     * "computer". iPads set to "Request desktop website" (the iPadOS default) report as a Mac.
     */
    public static function type(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'iPad') || str_contains($userAgent, 'Tablet') => 'tablet',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPod') => 'phone',
            // Android phones say "Mobile"; Android tablets do not.
            str_contains($userAgent, 'Android') => str_contains($userAgent, 'Mobile') ? 'phone' : 'tablet',
            str_contains($userAgent, 'Mobile') => 'phone',
            str_contains($userAgent, 'Windows') || str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'X11') || str_contains($userAgent, 'CrOS') => 'computer',
            default => 'unknown',
        };
    }

    private static function browser(string $userAgent): string
    {
        // Order matters: Edge and Chrome also say "Safari", Edge also says "Chrome".
        return match (true) {
            str_contains($userAgent, 'Edg/') || str_contains($userAgent, 'EdgA/') || str_contains($userAgent, 'EdgiOS/') => 'Edge',
            str_contains($userAgent, 'Firefox/') || str_contains($userAgent, 'FxiOS/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') || str_contains($userAgent, 'CriOS/') => 'Chrome',
            str_contains($userAgent, 'Safari/') && str_contains($userAgent, 'Version/') => 'Safari',
            default => 'Unknown',
        };
    }

    private static function platform(string $userAgent): string
    {
        // iPhone and iPad also say "like Mac OS X", so they are checked first.
        return match (true) {
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'Mac OS X') => 'macOS',
            default => 'Unknown',
        };
    }
}
