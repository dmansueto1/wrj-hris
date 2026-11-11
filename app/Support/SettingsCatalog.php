<?php

namespace App\Support;

/**
 * Every value on the System Settings page (T-14.1), in display order. Definitions only: values
 * are read through App\Support\Settings and saved by App\Services\SystemSettingsService.
 * Defaults are stored strings: '1'/'0' for bool, whole numbers for int, a rate ("0.20") for
 * percent. min and max are in the unit shown (percent for percent).
 */
class SettingsCatalog
{
    public const GROUPS = ['Attendance', 'Materials', 'Security', 'Devices'];

    /**
     * @var list<array{key: string, label: string, description: string, group: string, type: string, default: string, min?: int, max?: int, unit: string}>
     */
    public const SETTINGS = [
        [
            'key' => 'attendance_require_photo',
            'label' => 'Require a photo on clock in and out',
            'description' => 'Workers take a photo with the camera on Clock In/Out and the kiosk.',
            'group' => 'Attendance',
            'type' => 'bool',
            'default' => '1',
            'unit' => '',
        ],
        [
            'key' => 'attendance_require_approved_device',
            'label' => 'Allow clock in and out only on approved devices',
            'description' => 'Other phones, tablets and computers see the Request access screen.',
            'group' => 'Attendance',
            'type' => 'bool',
            'default' => '1',
            'unit' => '',
        ],
        [
            'key' => 'attendance_flag_after_hours',
            'label' => 'Flag attendance not clocked out after',
            'description' => 'Still Clocked In after this long, it goes to Needs correction and no longer blocks the next clock-in.',
            'group' => 'Attendance',
            'type' => 'int',
            'default' => '12',
            'min' => 1,
            'max' => 24,
            'unit' => 'hours',
        ],
        [
            'key' => 'kiosk_verified_seconds',
            'label' => 'Time to press Clock In or Clock Out after signing in at the kiosk',
            'description' => 'After this, the worker enters their username and password again.',
            'group' => 'Attendance',
            'type' => 'int',
            'default' => '120',
            'min' => 30,
            'max' => 600,
            'unit' => 'seconds',
        ],
        [
            'key' => 'material_markup_rate',
            'label' => 'Markup on material purchases',
            'description' => 'Added to every new material entry. Entries already saved keep their markup.',
            'group' => 'Materials',
            'type' => 'percent',
            'default' => '0.20',
            'min' => 0,
            'max' => 100,
            'unit' => '%',
        ],
        [
            'key' => 'idle_logout_minutes',
            'label' => 'Log out Admin and Staff after this much inactivity',
            'description' => 'Workers keep the normal sign-in time.',
            'group' => 'Security',
            'type' => 'int',
            'default' => '30',
            'min' => 5,
            'max' => 120,
            'unit' => 'minutes',
        ],
        [
            'key' => 'login_max_attempts',
            'label' => 'Lock an account after',
            'description' => 'Wrong passwords in a row on the sign-in page or the kiosk.',
            'group' => 'Security',
            'type' => 'int',
            'default' => '5',
            'min' => 3,
            'max' => 10,
            'unit' => 'wrong passwords',
        ],
        [
            'key' => 'login_lockout_minutes',
            'label' => 'Lock for',
            'description' => 'How long a locked account must wait before trying again.',
            'group' => 'Security',
            'type' => 'int',
            'default' => '15',
            'min' => 5,
            'max' => 60,
            'unit' => 'minutes',
        ],
        [
            'key' => 'device_prune_pending_days',
            'label' => 'Archive device requests not seen for',
            'description' => 'Pending requests nobody approved are archived after this many days.',
            'group' => 'Devices',
            'type' => 'int',
            'default' => '30',
            'min' => 7,
            'max' => 365,
            'unit' => 'days',
        ],
    ];

    /**
     * The settings grouped by GROUPS, in order.
     *
     * @return array<string, list<array{key: string, label: string, description: string, group: string, type: string, default: string, min?: int, max?: int, unit: string}>>
     */
    public static function all(): array
    {
        $groups = array_fill_keys(self::GROUPS, []);

        foreach (self::SETTINGS as $setting) {
            $groups[$setting['group']][] = $setting;
        }

        return $groups;
    }

    /**
     * The default as shown under each setting, e.g. "12 hours", "20 %", "On".
     *
     * @param  array{type: string, default: string, unit: string}  $setting
     */
    public static function defaultLabel(array $setting): string
    {
        return match ($setting['type']) {
            'bool' => $setting['default'] === '1' ? __('On') : __('Off'),
            'percent' => Settings::rateToPercent($setting['default']).' %',
            default => $setting['default'].' '.__($setting['unit']),
        };
    }

    /**
     * @return array{key: string, label: string, description: string, group: string, type: string, default: string, min?: int, max?: int, unit: string}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::SETTINGS as $setting) {
            if ($setting['key'] === $key) {
                return $setting;
            }
        }

        return null;
    }
}
