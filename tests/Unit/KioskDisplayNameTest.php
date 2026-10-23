<?php

namespace Tests\Unit;

use App\Services\KioskService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KioskDisplayNameTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function names(): array
    {
        return [
            'two-word last name' => ['Juan', 'Dela Cruz', 'Juan Dela Cruz'],
            'simple' => ['Maria', 'Santos', 'Maria Santos'],
            'accented last name' => ['Nino', 'Ñavarro', 'Nino Ñavarro'],
            'extra spaces' => ['  Jose ', '  Rizal ', 'Jose Rizal'],
            'no last name' => ['Pedro', '', 'Pedro'],
        ];
    }

    #[DataProvider('names')]
    public function testTheKioskShowsTheFullName(string $first, string $last, string $expected): void
    {
        $this->assertSame($expected, KioskService::nameFor($first, $last));
    }
}
