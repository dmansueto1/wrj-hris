<?php

namespace Tests\Unit;

use App\Services\DeviceService;
use PHPUnit\Framework\TestCase;

class DeviceCodeTest extends TestCase
{
    public function testCodesAreThreeAndThreeCharactersWithoutLookAlikes(): void
    {
        foreach (range(1, 200) as $i) {
            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{3}-[A-HJ-NP-Z2-9]{3}$/', DeviceService::newCode());
        }
    }
}
