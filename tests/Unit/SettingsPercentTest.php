<?php

namespace Tests\Unit;

use App\Support\Settings;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The markup is shown as a percent and stored as a rate, converted with integers only (T-14.1).
 */
class SettingsPercentTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function percentToRate(): array
    {
        return [
            'twenty' => ['20', '0.2000'],
            'twelve and a half' => ['12.5', '0.1250'],
            'quarter of a percent' => ['0.25', '0.0025'],
            'zero' => ['0', '0.0000'],
            'one hundred' => ['100', '1.0000'],
        ];
    }

    #[DataProvider('percentToRate')]
    public function testPercentToRate(string $percent, string $rate): void
    {
        $this->assertSame($rate, Settings::percentToRate($percent));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rateToPercent(): array
    {
        return [
            'seeded two decimals' => ['0.20', '20'],
            'twelve and a half' => ['0.1250', '12.5'],
            'quarter of a percent' => ['0.0025', '0.25'],
            'one hundred' => ['1.0000', '100'],
            'zero' => ['0.0000', '0'],
        ];
    }

    #[DataProvider('rateToPercent')]
    public function testRateToPercent(string $rate, string $percent): void
    {
        $this->assertSame($percent, Settings::rateToPercent($rate));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badPercents(): array
    {
        return [
            'three decimals' => ['12.345'],
            'negative' => ['-1'],
            'text' => ['abc'],
            'empty' => [''],
        ];
    }

    #[DataProvider('badPercents')]
    public function testABadPercentIsRejected(string $percent): void
    {
        $this->expectException(InvalidArgumentException::class);

        Settings::percentToRate($percent);
    }
}
