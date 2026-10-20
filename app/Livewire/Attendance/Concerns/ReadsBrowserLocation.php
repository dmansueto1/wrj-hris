<?php

namespace App\Livewire\Attendance\Concerns;

/**
 * Clock in/out screens receive the browser's coordinates from the clockPad Alpine helper
 * (resources/js/app.js). Location is optional: denied, unavailable or impossible values become null.
 */
trait ReadsBrowserLocation
{
    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function location(mixed $lat, mixed $lng): array
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return [null, null];
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return [null, null];
        }

        return [$lat, $lng];
    }
}
