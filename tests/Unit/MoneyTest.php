<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function amounts(): array
    {
        return [
            'one decimal' => ['1234.5', '₱ 1,234.50'],
            'zero' => ['0', '₱ 0.00'],
            'two decimals' => ['3600.00', '₱ 3,600.00'],
            'millions' => ['1234567.89', '₱ 1,234,567.89'],
            'no thousands separator below 1000' => ['999.99', '₱ 999.99'],
            'third decimal rounds half up' => ['346.665', '₱ 346.67'],
            'third decimal rounds down' => ['346.664', '₱ 346.66'],
            'half a centavo rounds up' => ['0.005', '₱ 0.01'],
            'negative amount' => ['-25', '-₱ 25.00'],
            'leading zeros' => ['007.5', '₱ 7.50'],
        ];
    }

    #[DataProvider('amounts')]
    public function testFormat(string $amount, string $expected): void
    {
        $this->assertSame($expected, Money::format($amount));
    }

    public function testFormatRejectsANonNumericAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::format('12abc');
    }
}
