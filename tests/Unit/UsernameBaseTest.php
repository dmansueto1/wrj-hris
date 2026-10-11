<?php

namespace Tests\Unit;

use App\Services\UsernameService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UsernameBaseTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function names(): array
    {
        return [
            'simple' => ['John', 'Doe', 'jdoe'],
            'multi-word last name' => ['Juan', 'dela Cruz', 'jdelacruz'],
            'accents and hyphens' => ['Ñino', 'Peña-Santos', 'npenasantos'],
            'extra spaces and case' => ['  maria ', ' SANTOS ', 'msantos'],
            'empty names' => ['', '', 'user'],
            'very long last name' => ['Ana', str_repeat('b', 100), 'a'.str_repeat('b', 44)],
        ];
    }

    #[DataProvider('names')]
    public function testTheUsernameBaseIsTheFirstInitialPlusTheLastName(string $first, string $last, string $expected): void
    {
        $this->assertSame($expected, UsernameService::base($first, $last));
    }
}
