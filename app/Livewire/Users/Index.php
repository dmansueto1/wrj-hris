<?php

namespace App\Livewire\Users;

use App\Livewire\Concerns\WithPerPage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
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
        ]);
    }
}
