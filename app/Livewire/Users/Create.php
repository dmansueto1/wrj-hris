<?php

namespace App\Livewire\Users;

use App\Livewire\Users\Concerns\UserFormRules;
use App\Models\User;
use App\Services\UsernameService;
use App\Services\UserService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Add user')]
class Create extends Component
{
    use UserFormRules;

    public string $employee_no = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $role = 'worker';

    public string $position = '';

    public string $hourly_rate = '';

    public string $password = '';

    /**
     * The username this user will get, shown read-only while the admin types the names.
     * Display only: the real username is generated when the user is saved.
     */
    #[Locked]
    public string $usernamePreview = '';

    /**
     * Runs on every request (first render and every action), so the permission is re-checked each time.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('users.manage'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['first_name', 'last_name'], true)) {
            $this->usernamePreview = trim($this->first_name) === '' && trim($this->last_name) === ''
                ? ''
                : app(UsernameService::class)->generate($this->first_name, $this->last_name);
        }
    }

    public function save(UserService $users): void
    {
        $validated = $this->validate(
            [...$this->userRules(), 'password' => ['required', 'string', Password::defaults()]],
            $this->userMessages(),
        );

        $user = $users->create($validated);

        Flux::toast(
            variant: 'success',
            text: __('User created. Username: :username', ['username' => $user->username]),
        );

        $this->redirectRoute('users.edit', $user, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.users.create', [
            'roles' => User::ROLES,
            'username' => $this->usernamePreview,
            'usernameHint' => __('from first and last name'),
        ]);
    }
}
