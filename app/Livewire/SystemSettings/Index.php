<?php

namespace App\Livewire\SystemSettings;

use App\Models\User;
use App\Services\SystemSettingsService;
use App\Support\SettingsCatalog;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * System Settings (T-14.1), Admin only: the rules that used to live in .env or in code, on one
 * form. Changes apply to new actions only.
 *
 * @property-read bool $hasChanges
 */
#[Title('System Settings')]
class Index extends Component
{
    /**
     * The form, key => value: bool as true/false, int as "12", percent as "20".
     *
     * @var array<string, bool|string>
     */
    public array $values = [];

    public function boot(): void
    {
        abort_unless(Auth::user()?->can('settings.manage'), 403);
    }

    public function mount(SystemSettingsService $settings): void
    {
        $this->values = $settings->values();
    }

    public function save(SystemSettingsService $settings): void
    {
        $settings->update($this->values, $this->admin());

        $this->values = $settings->values();
        unset($this->hasChanges);

        Flux::toast(variant: 'success', text: __('Settings saved. They apply to new actions from now on.'));
    }

    public function discard(SystemSettingsService $settings): void
    {
        $this->resetValidation();
        $this->values = $settings->values();
    }

    public function restoreDefaults(SystemSettingsService $settings): void
    {
        $this->resetValidation();
        $settings->restoreDefaults($this->admin());

        $this->values = $settings->values();
        unset($this->hasChanges);

        Flux::modal('restore-defaults')->close();
        Flux::toast(variant: 'success', text: __('Every setting is back to its default.'));
    }

    /**
     * True when the form differs from the saved settings.
     */
    #[Computed]
    public function hasChanges(): bool
    {
        $saved = app(SystemSettingsService::class)->values();

        foreach ($saved as $key => $value) {
            if ((string) ($this->values[$key] ?? '') !== (string) $value) {
                return true;
            }
        }

        return false;
    }

    public function render(): View
    {
        return view('livewire.system-settings.index', [
            'groups' => SettingsCatalog::all(),
        ]);
    }

    private function admin(): User
    {
        /** @var User */
        return Auth::user();
    }
}
