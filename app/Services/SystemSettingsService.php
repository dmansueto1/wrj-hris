<?php

namespace App\Services;

use App\Models\User;
use App\Support\Settings;
use App\Support\SettingsCatalog;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The System Settings page (T-14.1): read every setting as the form shows it, and save the
 * Admin's changes. Only changed keys are saved, and each save that changes something is logged
 * once with the old and new values (PRD 8 audit). Changes apply to new actions only.
 */
class SystemSettingsService
{
    /**
     * Every setting as the form shows it: bool as true/false, int as "12", percent as "20".
     *
     * @return array<string, bool|string>
     */
    public function values(): array
    {
        $values = [];

        foreach (SettingsCatalog::SETTINGS as $setting) {
            $values[$setting['key']] = match ($setting['type']) {
                'bool' => Settings::bool($setting['key']),
                'percent' => Settings::rateToPercent(Settings::string($setting['key'])),
                default => Settings::string($setting['key']),
            };
        }

        return $values;
    }

    /**
     * Validate and save the given settings (keys from SettingsCatalog; any subset). Errors use the
     * form's keys, e.g. "values.attendance_flag_after_hours".
     *
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException when the user may not manage settings
     * @throws ValidationException on an unknown key or a value outside its range
     */
    public function update(array $input, User $admin): void
    {
        if (! $admin->can('settings.manage')) {
            throw new AuthorizationException;
        }

        $this->validate($input);

        DB::transaction(function () use ($input, $admin) {
            $old = [];
            $new = [];

            foreach ($input as $key => $value) {
                $stored = $this->toStored(SettingsCatalog::find($key), $value);
                $current = $this->normalized(SettingsCatalog::find($key), Settings::string($key));

                if ($stored !== $current) {
                    $old[$key] = $current;
                    $new[$key] = $stored;
                    Settings::set($key, $stored);
                }
            }

            if ($new !== []) {
                activity('settings')
                    ->causedBy($admin)
                    ->withProperties(['old' => $old, 'new' => $new])
                    ->log('Changed settings');
            }
        });

        Settings::forget();
    }

    /**
     * Put every setting back to its catalog default.
     */
    public function restoreDefaults(User $admin): void
    {
        $defaults = [];

        foreach (SettingsCatalog::SETTINGS as $setting) {
            $defaults[$setting['key']] = match ($setting['type']) {
                'bool' => $setting['default'] === '1',
                'percent' => Settings::rateToPercent($setting['default']),
                default => $setting['default'],
            };
        }

        $this->update($defaults, $admin);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function validate(array $input): void
    {
        $rules = [];

        foreach (array_keys($input) as $key) {
            $setting = SettingsCatalog::find((string) $key);

            if ($setting === null) {
                throw ValidationException::withMessages(['values.'.$key => __('Unknown setting.')]);
            }

            $rules['values.'.$key] = match ($setting['type']) {
                'bool' => ['required', 'boolean'],
                'int' => ['required', 'integer', 'between:'.$setting['min'].','.$setting['max']],
                'percent' => ['required', $this->percentRule($setting['min'], $setting['max'])],
            };
        }

        // Shown right under the field, so they do not repeat its label.
        Validator::make(['values' => $input], $rules, [
            'values.*.required' => __('Enter a value.'),
            'values.*.integer' => __('Enter a whole number.'),
            'values.*.between' => __('Enter a number from :min to :max.'),
            'values.*.boolean' => __('Choose on or off.'),
        ])->validate();
    }

    /**
     * A percent with at most 2 decimals between min and max, compared in hundredths (no floats).
     */
    private function percentRule(int $min, int $max): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($min, $max) {
            try {
                $hundredths = (int) str_replace('.', '', Settings::percentToRate((string) $value));
            } catch (InvalidArgumentException) {
                $fail(__('Enter a percent with at most 2 decimals, e.g. 20 or 12.5.'));

                return;
            }

            if ($hundredths < $min * 100 || $hundredths > $max * 100) {
                $fail(__('Enter a percent from :min to :max.', ['min' => $min, 'max' => $max]));
            }
        };
    }

    /**
     * A validated form value as stored: '1'/'0', "12", or a rate "0.2500".
     *
     * @param  array{type: string}  $setting
     */
    private function toStored(array $setting, mixed $value): string
    {
        return match ($setting['type']) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'int' => (string) (int) $value,
            'percent' => Settings::percentToRate((string) $value),
        };
    }

    /**
     * The stored value in the same form as toStored(), so "0.20" and "0.2000" compare equal.
     *
     * @param  array{type: string}  $setting
     */
    private function normalized(array $setting, string $stored): string
    {
        return $setting['type'] === 'percent'
            ? Settings::percentToRate(Settings::rateToPercent($stored))
            : $stored;
    }
}
