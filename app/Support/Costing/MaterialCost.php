<?php

namespace App\Support\Costing;

use App\Support\Money;
use InvalidArgumentException;

/**
 * Cost of one material entry (PRD 7.2 and 10.3): subtotal = price × qty, markup = subtotal × rate,
 * total = subtotal + markup. Integers only, with half-up rounding to the centavo.
 */
class MaterialCost
{
    /** The largest amount the DECIMAL(14,2) columns hold, in centavos. */
    private const MAX_CENTAVOS = 99_999_999_999_999;

    /**
     * @param  string  $unitPrice  pesos, at most 2 decimals ("30.00")
     * @param  string  $quantity  at most 2 decimals ("3.5")
     * @param  string  $markupRate  at most 4 decimals ("0.20", "0.2500")
     * @return array{subtotal: string, markup: string, total: string}
     */
    public static function compute(string $unitPrice, string $quantity, string $markupRate): array
    {
        $priceCentavos = self::nonNegative(Money::toCentavos($unitPrice), $unitPrice);

        // Hundredths of a unit use the same two-decimal parsing as centavos.
        try {
            $quantityHundredths = self::nonNegative(Money::toCentavos($quantity), $quantity);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException("Invalid quantity [{$quantity}].");
        }

        $rateTenThousandths = self::tenThousandths($markupRate);

        // Checked before multiplying, so the product never overflows into a float.
        if ($priceCentavos > 0 && $quantityHundredths > intdiv(self::MAX_CENTAVOS * 100, $priceCentavos)) {
            throw new InvalidArgumentException("Material cost too large [{$unitPrice} × {$quantity}].");
        }

        $subtotal = intdiv($priceCentavos * $quantityHundredths + 50, 100);
        $markup = intdiv($subtotal * $rateTenThousandths + 5000, 10000);

        if ($subtotal + $markup > self::MAX_CENTAVOS) {
            throw new InvalidArgumentException("Material cost too large [{$unitPrice} × {$quantity}].");
        }

        return [
            'subtotal' => Money::fromCentavos($subtotal),
            'markup' => Money::fromCentavos($markup),
            'total' => Money::fromCentavos($subtotal + $markup),
        ];
    }

    /**
     * A rate with at most four decimals as ten-thousandths ("0.20" → 2000). No floats.
     */
    private static function tenThousandths(string $rate): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,4}))?$/', trim($rate), $parts)) {
            throw new InvalidArgumentException("Invalid markup rate [{$rate}].");
        }

        return (int) $parts[1] * 10000 + (int) str_pad($parts[2] ?? '', 4, '0');
    }

    private static function nonNegative(int $value, string $input): int
    {
        if ($value < 0) {
            throw new InvalidArgumentException("Amount cannot be negative [{$input}].");
        }

        return $value;
    }
}
