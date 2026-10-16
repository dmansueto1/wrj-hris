<?php

namespace App\Livewire\Users\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Validation shared by the create and edit user screens (T-06).
 */
trait UserFormRules
{
    /**
     * @return array<string, mixed>
     */
    protected function userRules(?int $ignoreUserId = null): array
    {
        return [
            // Archived users keep their employee no. and email, so the unique checks include them.
            'employee_no' => ['required', 'string', 'max:30', Rule::unique(User::class)->ignore($ignoreUserId)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($ignoreUserId)],
            'role' => ['required', Rule::in(User::ROLES)],
            'position' => ['nullable', 'string', 'max:255'],
            // 0 to 99,999.99, at most two decimals.
            'hourly_rate' => ['required', 'regex:/^\d{1,5}(\.\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function userMessages(): array
    {
        return [
            'hourly_rate.regex' => __('The hourly rate must be between 0 and 99,999.99 with at most two decimals.'),
        ];
    }
}
