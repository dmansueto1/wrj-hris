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
            'two-word last name' => ['Juan', 'Dela Cruz', 'Juan D.'],
            'simple' => ['Maria', 'Santos', 'Maria S.'],
            'lowercase last name' => ['Ana', 'reyes', 'Ana R.'],
            'accented last name' => ['Nino', 'Ñavarro', 'Nino Ñ.'],
            'extra spaces' => ['  Jose ', '  Rizal ', 'Jose R.'],
            'no last name' => ['Pedro', '', 'Pedro'],
        ];
    }

    #[DataProvider('names')]
    public function testTheKioskShowsTheFirstNameAndLastInitial(string $first, string $last, string $expected): void
    {
        $this->assertSame($expected, KioskService::nameFor($first, $last));
    }
}
