<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The time-of-day greeting at the top of the Dashboard (Asia/Manila), the same wording as the
 * kiosk and sign-in pages: morning until 12:00, afternoon until 18:00, evening after.
 */
class Greeting
{
    public static function for(CarbonInterface $at): string
    {
        return match (true) {
            $at->hour < 12 => __('Good morning'),
            $at->hour < 18 => __('Good afternoon'),
            default => __('Good evening'),
        };
    }

    /**
     * The Heroicon shown next to the greeting: the sun by day, the moon in the evening.
     */
    public static function icon(CarbonInterface $at): string
    {
        return $at->hour < 18 ? 'sun' : 'moon';
    }

    /**
     * The line under the greeting: the date, then what the dashboard shows. The overview for the
     * Admin; otherwise an encouragement while Clocked In, or the user's last 14 days.
     */
    public static function subtitle(bool $overview, bool $clockedIn, CarbonInterface $at): string
    {
        $date = $at->format('l, M j, Y');

        return match (true) {
            $overview => __(':date · Here is how today is going on site.', ['date' => $date]),
            $clockedIn => __(':date · You are Clocked In. Keep up the good work!', ['date' => $date]),
            default => __(':date · Here is your attendance for the last 14 days.', ['date' => $date]),
        };
    }

    /**
     * "Good morning, Ana!"
     */
    public static function to(string $firstName, CarbonInterface $at): string
    {
        return self::for($at).', '.$firstName.'!';
    }
}
