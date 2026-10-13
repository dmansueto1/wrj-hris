<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    /**
     * Format a peso amount string as "₱ 1,234.50", rounding half-up to the centavo.
     * Works on the digits as strings and integers, never floats.
     */
    public static function format(string $amount): string
    {
        if (! preg_match('/^(-)?(\d+)(?:\.(\d+))?$/', trim($amount), $parts)) {
            throw new InvalidArgumentException("Invalid peso amount [{$amount}].");
        }

        $negative = $parts[1] === '-';
        $decimals = str_pad($parts[3] ?? '', 3, '0');

        $pesos = (int) $parts[2];
        $centavos = (int) substr($decimals, 0, 2);

        // Half-up on the third decimal.
        if ((int) $decimals[2] >= 5) {
            $centavos++;
        }

        if ($centavos === 100) {
            $pesos++;
            $centavos = 0;
        }

        $formatted = '₱ '.number_format($pesos).'.'.str_pad((string) $centavos, 2, '0', STR_PAD_LEFT);

        return $negative && ($pesos > 0 || $centavos > 0) ? '-'.$formatted : $formatted;
    }
}
