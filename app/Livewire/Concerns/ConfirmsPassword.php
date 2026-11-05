<?php

namespace App\Livewire\Concerns;

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The Admin's own password, asked on every change to attendance (T-12 corrections, T-12.1
 * imports), with the same rule as Security settings. Five wrong passwords in a minute lock every
 * such action for that minute. The component declares `public string $password` and clears it
 * after each attempt.
 */
trait ConfirmsPassword
{
    use PasswordValidationRules;

    /**
     * @throws ValidationException on a missing or wrong password, or while locked
     */
    protected function confirmPassword(): void
    {
        $key = 'attendance-password:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'password' => __('Too many wrong passwords. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        try {
            $this->validate(['password' => $this->currentPasswordRules()], [], ['password' => __('password')]);
        } catch (ValidationException $e) {
            RateLimiter::hit($key, 60);

            throw $e;
        }

        RateLimiter::clear($key);
    }
}
