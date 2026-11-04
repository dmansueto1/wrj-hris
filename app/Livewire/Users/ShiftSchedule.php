<?php

namespace App\Livewire\Users;

use App\Livewire\Users\Concerns\EditsShift;
use App\Models\User;
use App\Services\UserShiftService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The "Shift" card on the user edit page (T-11.2): the shift type and the Monday-to-Sunday
 * In / Out times and rest days. Admin only (users.manage). The editor is shared with Add user.
 */
class ShiftSchedule extends Component
{
    use EditsShift;

    public User $user;

    /**
     * Runs on every request (first render and every action), so the permission is re-checked each time.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('users.manage'), 403);
    }

    public function mount(User $user, UserShiftService $shifts): void
    {
        $this->user = $user;
        $this->type = $user->shift_type;
        $this->days = $shifts->schedule($user);
    }

    public function save(UserShiftService $shifts): void
    {
        $this->resetErrorBag();

        /** @var User $admin */
        $admin = Auth::user();

        $shifts->update($this->user, $this->type, $this->days, $admin);

        $this->user->refresh();
        $this->days = $shifts->schedule($this->user);

        Flux::toast(variant: 'success', text: __('Shift saved. It applies to attendance clocked in from now on.'));
    }

    public function render(): View
    {
        return view('livewire.users.shift-schedule', $this->shiftViewData());
    }
}
