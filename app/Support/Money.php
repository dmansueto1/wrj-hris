<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    /**
     * Turn a peso amount with at most two decimals into whole centavos ("80.5" → 8050). No floats.
     */
    public static function toCentavos(string $amount): int
    {
        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', trim($amount), $parts)) {
            throw new InvalidArgumentException("Invalid peso amount [{$amount}].");
        }

        $centavos = (int) $parts[2] * 100 + (int) str_pad($parts[3] ?? '', 2, '0');

        return $parts[1] === '-' ? -$centavos : $centavos;
    }

    /**
     * Turn whole centavos back into a two-decimal peso string (34667 → "346.67").
     */
    public static function fromCentavos(int $centavos): string
    {
        $sign = $centavos < 0 ? '-' : '';
        $centavos = abs($centavos);

        return $sign.intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

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
