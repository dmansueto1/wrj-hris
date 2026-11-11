<?php

namespace Tests\Unit;

use App\Support\Settings;
use App\Support\SettingsCatalog;
use PHPUnit\Framework\TestCase;

class SettingsCatalogTest extends TestCase
{
    public function testEverySettingIsComplete(): void
    {
        foreach (SettingsCatalog::SETTINGS as $setting) {
            $this->assertNotSame('', $setting['key']);
            $this->assertNotSame('', $setting['label'], $setting['key']);
            $this->assertNotSame('', $setting['description'], $setting['key']);
            $this->assertContains($setting['group'], SettingsCatalog::GROUPS, $setting['key']);
            $this->assertContains($setting['type'], ['bool', 'int', 'percent'], $setting['key']);

            match ($setting['type']) {
                'bool' => $this->assertContains($setting['default'], ['0', '1'], $setting['key']),
                'int' => $this->assertTrue(
                    ctype_digit($setting['default']) && (int) $setting['default'] >= $setting['min'] && (int) $setting['default'] <= $setting['max'],
                    $setting['key'].' default is outside its range',
                ),
                // "0.2000" read as 2000 hundredths of a percent; no floats.
                'percent' => $this->assertTrue(
                    (int) str_replace('.', '', Settings::percentToRate(Settings::rateToPercent($setting['default']))) >= $setting['min'] * 100
                        && (int) str_replace('.', '', Settings::percentToRate(Settings::rateToPercent($setting['default']))) <= $setting['max'] * 100,
                    $setting['key'].' default is outside its range',
                ),
            };
        }
    }

    public function testKeysAreUniqueAndFindWorks(): void
    {
        $keys = array_column(SettingsCatalog::SETTINGS, 'key');

        $this->assertSame($keys, array_values(array_unique($keys)));
        $this->assertSame('12', SettingsCatalog::find('attendance_flag_after_hours')['default']);
        $this->assertNull(SettingsCatalog::find('nope'));
    }

    public function testTheSettingsAreGroupedInOrder(): void
    {
        $this->assertSame(['Attendance', 'Materials', 'Security', 'Devices'], array_keys(SettingsCatalog::all()));
        $this->assertSame(
            ['attendance_require_photo', 'attendance_require_approved_device', 'attendance_flag_after_hours', 'kiosk_verified_seconds'],
            array_column(SettingsCatalog::all()['Attendance'], 'key'),
        );
    }

    public function testTheDefaultsMatchTheRulesBeforeThisPage(): void
    {
        $defaults = array_column(SettingsCatalog::SETTINGS, 'default', 'key');

        $this->assertSame([
            'attendance_require_photo' => '1',
            'attendance_require_approved_device' => '1',
            'attendance_flag_after_hours' => '12',
            'kiosk_verified_seconds' => '120',
            'material_markup_rate' => '0.20',
            'idle_logout_minutes' => '30',
            'login_max_attempts' => '5',
            'login_lockout_minutes' => '15',
            'device_prune_pending_days' => '30',
        ], $defaults);
    }
}
