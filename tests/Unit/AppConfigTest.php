<?php

namespace Tests\Unit;

use Tests\TestCase;

class AppConfigTest extends TestCase
{
    public function testAppTimezoneIsAsiaManila(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
    }
}
