<?php

namespace Tests\Unit;

use App\Support\Costing\MaterialCost;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PRD section 10.3: material cost = (price × qty) + markup, in whole centavos with half-up rounding.
 */
class MaterialCostTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string, string, string}>
     */
    public static function prdCases(): array
    {
        return [
            'hollow blocks' => ['30.00', '100', '0.20', '3000.00', '600.00', '3600.00'],
            'decimal quantity' => ['12.50', '3.5', '0.20', '43.75', '8.75', '52.50'],
            'markup rounding' => ['0.33', '3', '0.20', '0.99', '0.20', '1.19'],
        ];
    }

    #[DataProvider('prdCases')]
    public function testCompute(string $price, string $quantity, string $rate, string $subtotal, string $markup, string $total): void
    {
        $this->assertSame(
            ['subtotal' => $subtotal, 'markup' => $markup, 'total' => $total],
            MaterialCost::compute($price, $quantity, $rate),
        );
    }

    public function testTheRateAcceptsFourDecimals(): void
    {
        // 3,000.00 × 0.2500 = 750.00; 3,000.00 × 0.1250 = 375.00.
        $this->assertSame('3750.00', MaterialCost::compute('30.00', '100', '0.2500')['total']);
        $this->assertSame('375.00', MaterialCost::compute('30.00', '100', '0.1250')['markup']);
    }

    public function testAZeroRateHasNoMarkup(): void
    {
        $this->assertSame(
            ['subtotal' => '3000.00', 'markup' => '0.00', 'total' => '3000.00'],
            MaterialCost::compute('30.00', '100', '0'),
        );
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'negative price' => ['-1.00', '1', '0.20'],
            'negative quantity' => ['1.00', '-1', '0.20'],
            'price with three decimals' => ['1.005', '1', '0.20'],
            'quantity with three decimals' => ['1.00', '1.005', '0.20'],
            'rate with five decimals' => ['1.00', '1', '0.20005'],
            'not a number' => ['abc', '1', '0.20'],
            'too large for the columns' => ['1000000.00', '10000000', '0.20'],
            'markup pushes the total over the columns' => ['999999999999.99', '1', '0.20'],
        ];
    }

    public function testABadQuantityNamesTheQuantity(): void
    {
        $this->expectExceptionMessage('Invalid quantity [1.005].');

        MaterialCost::compute('1.00', '1.005', '0.20');
    }

    public function testTheLargestStorableAmountIsAccepted(): void
    {
        $this->assertSame('999999999999.99', MaterialCost::compute('999999999999.99', '1', '0')['total']);
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidInputIsRejected(string $price, string $quantity, string $rate): void
    {
        $this->expectException(InvalidArgumentException::class);

        MaterialCost::compute($price, $quantity, $rate);
    }
}
