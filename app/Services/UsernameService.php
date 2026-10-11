<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class UsernameService
{
    /**
     * Generate a unique username: first initial plus last name (John Doe → jdoe),
     * with 2, 3, … appended when taken. Archived users keep their username reserved.
     */
    public function generate(string $firstName, string $lastName): string
    {
        $base = self::base($firstName, $lastName);

        $taken = User::withTrashed()
            ->where('username', 'like', $base.'%')
            ->pluck('username')
            ->all();

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        $suffix = 2;

        while (in_array($base.$suffix, $taken, true)) {
            $suffix++;
        }

        return $base.$suffix;
    }

    /**
     * Build the username base from the names, lowercase letters only.
     */
    public static function base(string $firstName, string $lastName): string
    {
        $first = self::lettersOnly($firstName);
        $last = self::lettersOnly($lastName);

        // Capped below the 50-character column to leave room for a numeric suffix.
        $base = substr(substr($first, 0, 1).$last, 0, 45);

        return $base === '' ? 'user' : $base;
    }

    private static function lettersOnly(string $value): string
    {
        return (string) preg_replace('/[^a-z]/', '', Str::lower(Str::ascii($value)));
    }
}
