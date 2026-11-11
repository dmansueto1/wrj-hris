<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * System values from the `settings` table (PRD section 9), e.g. material_markup_rate = 0.20.
 */
class Settings
{
    /**
     * The stored value, or the default when the key has no row.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = DB::table('settings')->where('key', $key)->value('value');

        return $value === null ? $default : (string) $value;
    }
}
