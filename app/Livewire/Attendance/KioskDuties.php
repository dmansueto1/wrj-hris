<?php

namespace App\Livewire\Attendance;

use App\Services\KioskService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Today's duties" on the kiosk: the 10 newest clock events, refreshed every 10 seconds and right
 * after a clock in/out on this kiosk. A separate component so polling never re-sends the form.
 */
class KioskDuties extends Component
{
    public const ROWS = 10;

    /**
     * @return Collection<int, array{key: string, direction: string, name: string, initials: string, type: string, project: string, time: string}>
     */
    #[Computed]
    public function duties(): Collection
    {
        return app(KioskService::class)->recentDuties(self::ROWS);
    }

    #[Computed]
    public function onDutyCount(): int
    {
        return app(KioskService::class)->onDutyCount();
    }

    #[On('kiosk-duty-recorded')]
    public function refresh(): void
    {
        unset($this->duties, $this->onDutyCount);
    }

    public function render(): View
    {
        return view('livewire.attendance.kiosk-duties');
    }
}
