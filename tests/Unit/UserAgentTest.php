<?php

namespace Tests\Unit;

use App\Support\UserAgent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserAgentTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function userAgents(): array
    {
        return [
            'Android Chrome' => [
                'Mozilla/5.0 (Linux; Android 14; SM-X200) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
                'Chrome',
                'Android',
            ],
            'iPhone Safari' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
                'Safari',
                'iPhone',
            ],
            'Windows Edge' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0',
                'Edge',
                'Windows',
            ],
            'unknown' => ['curl/8.4.0', 'Unknown', 'Unknown'],
        ];
    }

    #[DataProvider('userAgents')]
    public function testItNamesTheBrowserAndPlatform(string $userAgent, string $browser, string $platform): void
    {
        $this->assertSame(['browser' => $browser, 'platform' => $platform], UserAgent::describe($userAgent));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function types(): array
    {
        return [
            'Android phone' => ['Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36', 'phone'],
            'Android tablet' => ['Mozilla/5.0 (Linux; Android 14; SM-X200) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', 'tablet'],
            'iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'phone'],
            'iPad' => ['Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'tablet'],
            'Windows laptop' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0', 'computer'],
            'MacBook' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15', 'computer'],
            'unknown' => ['curl/8.4.0', 'unknown'],
        ];
    }

    #[DataProvider('types')]
    public function testItTellsPhonesTabletsAndComputersApart(string $userAgent, string $type): void
    {
        $this->assertSame($type, UserAgent::type($userAgent));
    }
}
