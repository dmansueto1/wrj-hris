<?php

namespace Tests\Feature\SystemSettings;

use App\Support\Settings;
use App\Support\SettingsCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingsStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests read the real table, not the test defaults from Tests\TestCase.
        Settings::clearFake();
    }

    public function testAFreshDatabaseReturnsTheCatalogDefaults(): void
    {
        foreach (SettingsCatalog::SETTINGS as $setting) {
            $this->assertSame($setting['default'], Settings::get($setting['key']), $setting['key']);
        }

        $this->assertTrue(Settings::bool('attendance_require_photo'));
        $this->assertTrue(Settings::bool('attendance_require_approved_device'));
        $this->assertSame(12, Settings::int('attendance_flag_after_hours'));
        $this->assertSame(120, Settings::int('kiosk_verified_seconds'));
        $this->assertSame('0.20', Settings::string('material_markup_rate'));
        $this->assertSame(30, Settings::int('idle_logout_minutes'));
        $this->assertSame(5, Settings::int('login_max_attempts'));
        $this->assertSame(15, Settings::int('login_lockout_minutes'));
        $this->assertSame(30, Settings::int('device_prune_pending_days'));
    }

    public function testAnUnknownKeyUsesTheGivenDefault(): void
    {
        $this->assertNull(Settings::get('missing_key'));
        $this->assertSame('fallback', Settings::get('missing_key', 'fallback'));
    }

    public function testSetSavesTheValueAndItIsReadBackAfterTheCacheIsCleared(): void
    {
        $this->assertSame(12, Settings::int('attendance_flag_after_hours')); // warms the cache

        Settings::set('attendance_flag_after_hours', '8');

        $this->assertSame(8, Settings::int('attendance_flag_after_hours'));
        $this->assertSame('8', DB::table('settings')->where('key', 'attendance_flag_after_hours')->value('value'));

        Cache::flush();
        $this->app->forgetInstance(Settings::MEMO);
        $this->assertSame(8, Settings::int('attendance_flag_after_hours'));
    }

    public function testAFakeOverridesUntilTheKeyIsSet(): void
    {
        Settings::fake(['attendance_require_photo' => '0']);
        $this->assertFalse(Settings::bool('attendance_require_photo'));

        Settings::set('attendance_require_photo', '1');
        $this->assertTrue(Settings::bool('attendance_require_photo'));

        Settings::fake(['login_max_attempts' => '3']);
        Settings::clearFake();
        $this->assertSame(5, Settings::int('login_max_attempts'));
    }
}
