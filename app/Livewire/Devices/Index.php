<?php

namespace App\Livewire\Devices;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Device;
use App\Models\User;
use App\Services\DeviceService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Devices (T-11.1): approve, rename, block or archive the devices that asked to use the kiosk or
 * Clock In/Out. Admin only.
 *
 * @property-read LengthAwarePaginator<int, Device> $devices
 * @property-read array{pending: int, approved: int, blocked: int} $summary
 */
#[Title('Devices')]
class Index extends Component
{
    use WithPerPage;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    /** The device in the name modal, and whether the modal approves or renames it. */
    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public string $mode = 'approve';

    public string $deviceName = '';

    public function boot(): void
    {
        abort_unless(Auth::user()?->can('devices.manage'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    public function openApprove(int $deviceId): void
    {
        $this->openNameModal($deviceId, 'approve');
    }

    public function openRename(int $deviceId): void
    {
        $this->openNameModal($deviceId, 'rename');
    }

    public function saveName(DeviceService $devices): void
    {
        $this->validate(
            ['deviceName' => ['required', 'string', 'max:100']],
            ['deviceName.required' => __('Give the device a name, like "Site A tablet".')],
        );

        $device = Device::query()->findOrFail($this->editingId);
        $name = trim($this->deviceName);

        if ($this->mode === 'approve') {
            $devices->approve($device, $name, $this->admin());
            $message = __(':name approved. It can now use the kiosk and Clock In/Out.', ['name' => $name]);
        } else {
            $devices->rename($device, $name, $this->admin());
            $message = __('Device renamed.');
        }

        Flux::modal('device-name')->close();
        $this->reset('editingId', 'deviceName');
        $this->refreshList($message);
    }

    public function block(int $deviceId, DeviceService $devices): void
    {
        $devices->block(Device::query()->findOrFail($deviceId), $this->admin());

        $this->refreshList(__('Device blocked. It can no longer use the kiosk or Clock In/Out.'));
    }

    public function archive(int $deviceId, DeviceService $devices): void
    {
        $devices->archive(Device::query()->findOrFail($deviceId), $this->admin());

        $this->refreshList(__('Device archived. It has to request access again.'));
    }

    /**
     * @return array{pending: int, approved: int, blocked: int}
     */
    #[Computed]
    public function summary(): array
    {
        $counts = Device::query()->toBase()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return [
            'pending' => (int) ($counts[Device::STATUS_PENDING] ?? 0),
            'approved' => (int) ($counts[Device::STATUS_APPROVED] ?? 0),
            'blocked' => (int) ($counts[Device::STATUS_BLOCKED] ?? 0),
        ];
    }

    /**
     * Pending requests first, then the most recently seen.
     *
     * @return LengthAwarePaginator<int, Device>
     */
    #[Computed]
    public function devices(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Device::query()
            ->with(['requestedBy', 'lastUser'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->when(isset(Device::STATUSES[$this->status]), fn ($query) => $query->where('status', $this->status))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Device::STATUS_PENDING])
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->paginate($this->perPage());
    }

    public function render(DeviceService $devices): View
    {
        return view('livewire.devices.index', [
            'currentDeviceId' => $devices->find(request())?->id,
            'hasFilters' => trim($this->search) !== '' || $this->status !== '',
            'editing' => $this->editingId ? Device::query()->find($this->editingId) : null,
        ]);
    }

    private function openNameModal(int $deviceId, string $mode): void
    {
        $device = Device::query()->findOrFail($deviceId);

        $this->resetErrorBag();
        $this->editingId = $device->id;
        $this->mode = $mode;
        $this->deviceName = (string) $device->name;

        Flux::modal('device-name')->show();
    }

    private function refreshList(string $message): void
    {
        unset($this->devices, $this->summary);
        Flux::toast(variant: 'success', text: $message);
    }

    private function admin(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
