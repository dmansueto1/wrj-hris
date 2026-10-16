<?php

namespace App\Support;

/**
 * Page sizes for every table in the app. Users pick one from "Rows per page".
 */
class Pagination
{
    public const DEFAULT_PER_PAGE = 10;

    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * The requested page size if it is one of the options, otherwise the default.
     */
    public static function perPage(int $requested): int
    {
        return in_array($requested, self::PER_PAGE_OPTIONS, true) ? $requested : self::DEFAULT_PER_PAGE;
    }
}
