<?php

namespace App\Livewire\Users;

use App\Livewire\Users\Concerns\EditsShift;
use App\Livewire\Users\Concerns\UserFormRules;
use App\Models\User;
use App\Services\UsernameService;
use App\Services\UserService;
use App\Services\UserShiftService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Add user')]
class Create extends Component
{
    use EditsShift;
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

    /**
     * The shift editor starts on Two shifts a day: Monday to Saturday, Sunday as rest day.
     */
    public function mount(UserShiftService $shifts): void
    {
        $this->type = UserService::NEW_USER_SHIFT;
        $this->days = $shifts->defaults($this->type);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['first_name', 'last_name'], true)) {
            $this->usernamePreview = trim($this->first_name) === '' && trim($this->last_name) === ''
                ? ''
                : app(UsernameService::class)->generate($this->first_name, $this->last_name);
        }
    }

    public function save(UserService $users, UserShiftService $shifts): void
    {
        // The shift is checked together with the other fields, so every error shows at once.
        $shiftErrors = [];
        try {
            $shifts->validateSchedule($this->type, $this->days);
        } catch (ValidationException $e) {
            $shiftErrors = $e->errors();
        }

        $validated = $this->withValidator(function (Validator $validator) use ($shiftErrors) {
            $validator->after(function (Validator $validator) use ($shiftErrors) {
                foreach ($shiftErrors as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            });
        })->validate(
            [...$this->userRules(), 'password' => ['required', 'string', Password::defaults()]],
            $this->userMessages(),
        );

        $user = $users->create($validated, $this->type, $this->days);

        Flux::toast(
            variant: 'success',
            text: __('User created. Username: :username', ['username' => $user->username]),
        );

        $this->redirectRoute('users.edit', $user, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.users.create', [
            ...$this->shiftViewData(),
            'roles' => User::ROLES,
            'username' => $this->usernamePreview,
            'usernameHint' => __('from first and last name'),
        ]);
    }
}
