<?php

namespace App\Livewire\Attendance;

use App\Exceptions\Attendance\AttendanceException;
use App\Exceptions\Attendance\ProjectNotOngoingException;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The clock in/out screen at /clock, everyone's home page (T-10).
 */
#[Title('Clock In/Out')]
class Clock extends Component
{
    /** The Ongoing project picked for clock-in. */
    public ?int $projectId = null;

    /**
     * The session just closed, shown as a confirmation: start, end and hours.
     *
     * @var array{project: string, start: string, end: string, hours: string}|null
     */
    #[Locked]
    public ?array $lastClockOut = null;

    /**
     * Runs on every request (first render and every action), so the permission is re-checked each time.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('attendance.clock'), 403);
    }

    public function clockIn(AttendanceService $attendance, mixed $lat = null, mixed $lng = null): void
    {
        $this->validate(['projectId' => ['required', 'integer']], [
            'projectId.required' => __('Choose the project you are working on.'),
        ]);

        // Look the project up without trusting the screen: a tampered or stale ID gets a clear message.
        $project = Project::query()->find($this->projectId);

        $this->attempt(function () use ($attendance, $project, $lat, $lng) {
            if ($project === null) {
                throw new ProjectNotOngoingException;
            }

            $attendance->clockIn($this->user(), $project, ...$this->location($lat, $lng));

            $this->reset('projectId', 'lastClockOut');
            Flux::toast(variant: 'success', text: __('Clocked in to :project.', ['project' => $project->name]));
        });
    }

    public function clockOut(AttendanceService $attendance, mixed $lat = null, mixed $lng = null): void
    {
        $this->attempt(function () use ($attendance, $lat, $lng) {
            $session = $attendance->clockOut($this->user(), ...$this->location($lat, $lng));
            $session->load('project');

            $this->lastClockOut = [
                'project' => $session->project->name,
                'start' => $session->clock_in_at->format('g:i A'),
                'end' => $session->clock_out_at?->format('g:i A') ?? '',
                'hours' => (string) $session->hours_worked,
            ];

            Flux::modal('confirm-clock-out')->close();
            Flux::toast(variant: 'success', text: __('Clocked out. :hours hours recorded.', ['hours' => $session->hours_worked]));
        });
    }

    #[Computed]
    public function openSession(): ?Attendance
    {
        return app(AttendanceService::class)->openSessionFor($this->user());
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Project::query()->ongoing()->orderBy('code')->get(['id', 'code', 'name', 'location']);
    }

    /**
     * @return array{sessions: Collection<int, Attendance>, total_hours: string}
     */
    #[Computed]
    public function today(): array
    {
        return app(AttendanceService::class)->today($this->user());
    }

    public function render(): View
    {
        return view('livewire.attendance.clock');
    }

    /**
     * Run a clock action and turn the service's rule violations into a friendly message on screen.
     */
    private function attempt(callable $action): void
    {
        $this->resetErrorBag('clock');

        try {
            $action();
        } catch (AttendanceException $e) {
            $this->addError('clock', $e->getMessage());
        }

        unset($this->openSession, $this->today);
    }

    /**
     * Browser coordinates, or nulls when location was denied, unavailable or out of range.
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function location(mixed $lat, mixed $lng): array
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return [null, null];
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return [null, null];
        }

        return [$lat, $lng];
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
