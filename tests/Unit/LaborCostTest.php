<?php

namespace Tests\Unit;

use App\Support\Costing\LaborCost;
use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PRD section 10.2: labor cost is computed from exact minutes, so rounding the hours never changes the amount.
 */
class LaborCostTest extends TestCase
{
    /**
     * @return array<string, array{int, string, string, string}>
     */
    public static function prdCases(): array
    {
        return [
            'morning session 7:00-11:30' => [270, '80.00', '4.50', '360.00'],
            'uneven minutes 7:00-11:20' => [260, '80.00', '4.33', '346.67'],
            'one-minute session' => [1, '75.00', '0.02', '1.25'],
            'zero minutes' => [0, '80.00', '0.00', '0.00'],
            'overnight 10:00 PM-6:00 AM' => [480, '95.50', '8.00', '764.00'],
        ];
    }

    #[DataProvider('prdCases')]
    public function testForMinutes(int $minutes, string $rate, string $hours, string $cost): void
    {
        $this->assertSame(['hours' => $hours, 'cost' => $cost], LaborCost::forMinutes($minutes, $rate));
    }

    public function testTwoSessionsInOneDayTotalNineHoursAndSevenHundredTwenty(): void
    {
        $morning = LaborCost::forMinutes(270, '80.00');
        $afternoon = LaborCost::forMinutes(270, '80.00');

        $this->assertSame('9.00', Money::fromCentavos(Money::toCentavos($morning['hours']) + Money::toCentavos($afternoon['hours'])));
        $this->assertSame('720.00', Money::fromCentavos(Money::toCentavos($morning['cost']) + Money::toCentavos($afternoon['cost'])));
    }

    public function testTheCostComesFromExactMinutesNotTheRoundedHours(): void
    {
        // 260 minutes = 4.333… hours. 4.33 × 80 would be 346.40; exact minutes give 346.67.
        $this->assertSame('346.67', LaborCost::forMinutes(260, '80.00')['cost']);
    }

    public function testRatesWithoutCentsAndOneDecimalAreAccepted(): void
    {
        $this->assertSame('360.00', LaborCost::forMinutes(270, '80')['cost']);
        $this->assertSame('429.75', LaborCost::forMinutes(270, '95.5')['cost']);
    }

    public function testNegativeMinutesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        LaborCost::forMinutes(-1, '80.00');
    }
}
