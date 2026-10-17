<?php

namespace App\Livewire\Users;

use App\Livewire\Concerns\WithPerPage;
use App\Models\User;
use App\Services\UserService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Users')]
class Index extends Component
{
    use WithPerPage;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $status = '';

    /**
     * Runs on every request (first render and every action), so the permission is re-checked each time.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('users.manage'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'role', 'status');
        $this->resetPage();
    }

    public function deactivate(int $userId, UserService $users): void
    {
        $this->changeStatus($userId, fn (User $user) => $users->deactivate($user, $this->editor()), __('User deactivated. They can no longer log in.'));
    }

    public function reactivate(int $userId, UserService $users): void
    {
        $this->changeStatus($userId, fn (User $user) => $users->reactivate($user), __('User reactivated.'));
    }

    /**
     * Counts for the stat cards.
     *
     * @return array{total: int, active: int, inactive: int, roles: array<string, int>}
     */
    #[Computed]
    public function summary(): array
    {
        return app(UserService::class)->summary();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return User::query()
            ->with('roles')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('employee_no', 'like', "%{$search}%");
                });
            })
            ->when(in_array($this->role, User::ROLES, true), fn ($query) => $query->role($this->role))
            ->when(in_array($this->status, [User::STATUS_ACTIVE, User::STATUS_INACTIVE], true), fn ($query) => $query->where('status', $this->status))
            ->orderBy('employee_no')
            ->paginate($this->perPage());
    }

    public function render(): View
    {
        return view('livewire.users.index', [
            'roles' => User::ROLES,
            'hasFilters' => trim($this->search) !== '' || $this->role !== '' || $this->status !== '',
        ]);
    }

    /**
     * @param  callable(User): void  $change
     */
    private function changeStatus(int $userId, callable $change, string $message): void
    {
        try {
            $change(User::findOrFail($userId));
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->validator->errors()->first());

            return;
        }

        unset($this->users, $this->summary);
        Flux::toast(variant: 'success', text: $message);
    }

    private function editor(): User
    {
        /** @var User $editor */
        $editor = Auth::user();

        return $editor;
    }
}
