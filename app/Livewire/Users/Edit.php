<?php

namespace App\Livewire\Users;

use App\Livewire\Concerns\WithPerPage;
use App\Livewire\Users\Concerns\UserFormRules;
use App\Models\User;
use App\Models\UserRateHistory;
use App\Services\UserService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Edit user')]
class Edit extends Component
{
    use UserFormRules;
    use WithPerPage;

    public User $user;

    public string $employee_no = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $role = '';

    public string $position = '';

    public string $hourly_rate = '';

    public string $new_password = '';

    /**
     * Runs on every request (first render and every action), so the permission is re-checked each time.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('users.manage'), 403);
    }

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->fillFromUser();
    }

    public function save(UserService $users): void
    {
        $validated = $this->validate($this->userRules($this->user->id), $this->userMessages());

        $users->update($this->user, $validated, $this->editor());

        $this->user->refresh();
        $this->fillFromUser();
        unset($this->rateHistories);

        Flux::toast(variant: 'success', text: __('User updated.'));
    }

    public function deactivate(UserService $users): void
    {
        $users->deactivate($this->user, $this->editor());

        Flux::toast(text: __('User deactivated. They can no longer log in.'));
    }

    public function reactivate(UserService $users): void
    {
        $users->reactivate($this->user);

        Flux::toast(variant: 'success', text: __('User reactivated.'));
    }

    public function resetPassword(UserService $users): void
    {
        $this->validate(['new_password' => ['required', 'string', Password::defaults()]]);

        $users->resetPassword($this->user, $this->new_password);

        $this->reset('new_password');
        Flux::modal('reset-password')->close();
        Flux::toast(variant: 'success', text: __('Temporary password set. The user must change it at next login.'));
    }

    public function archive(UserService $users): void
    {
        $users->archive($this->user, $this->editor());

        Flux::toast(text: __('User archived.'));

        $this->redirectRoute('users.index', navigate: true);
    }

    /**
     * Rate changes, newest first.
     *
     * @return LengthAwarePaginator<int, UserRateHistory>
     */
    #[Computed]
    public function rateHistories(): LengthAwarePaginator
    {
        return $this->user->rateHistories()
            ->with('changedBy')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->paginate($this->perPage());
    }

    public function render(): View
    {
        return view('livewire.users.edit', [
            'roles' => User::ROLES,
            'isSelf' => $this->user->is(Auth::user()),
            'username' => $this->user->username,
            'usernameHint' => __('does not change when the name is edited'),
        ]);
    }

    private function fillFromUser(): void
    {
        $this->employee_no = $this->user->employee_no;
        $this->first_name = $this->user->first_name;
        $this->last_name = $this->user->last_name;
        $this->email = $this->user->email ?? '';
        $this->role = (string) $this->user->getRoleNames()->first();
        $this->position = $this->user->position ?? '';
        $this->hourly_rate = $this->user->hourly_rate;
    }

    private function editor(): User
    {
        /** @var User $editor */
        $editor = Auth::user();

        return $editor;
    }
}
