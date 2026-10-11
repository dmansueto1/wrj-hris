<?php

namespace App\Livewire\Auth;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::auth')]
#[Title('Change password')]
class ChangePassword extends Component
{
    use PasswordValidationRules;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Set a new password and clear the "must change password" flag.
     */
    public function changePassword(): void
    {
        $validated = $this->validate([
            'current_password' => $this->currentPasswordRules(),
            'password' => [...$this->passwordRules(), 'different:current_password'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        $this->redirect(config('fortify.home'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.change-password');
    }
}
