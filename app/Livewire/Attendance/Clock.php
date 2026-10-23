<?php

namespace App\Livewire\Attendance;

use App\Exceptions\Attendance\AttendanceException;
use App\Exceptions\Attendance\ProjectNotOngoingException;
use App\Livewire\Attendance\Concerns\ReadsBrowserLocation;
use App\Livewire\Attendance\Concerns\TakesAttendancePhoto;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\AttendancePhotos;
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
    use ReadsBrowserLocation;
    use TakesAttendancePhoto;

    /**
     * The Ongoing project picked for clock-in, as the select's value. Empty (not null) so the
     * "Select project" placeholder is the selected option; with null the browser shows the first
     * project while this stays empty, and the Clock In button stays disabled.
     */
    public string $projectId = '';

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
        $this->validate(['projectId' => ['required', 'integer'], ...$this->photoRules()], [
            'projectId.required' => __('Choose the project you are working on.'),
            ...$this->photoMessages(),
        ]);

        // Look the project up without trusting the screen: a tampered or stale ID gets a clear message.
        $project = Project::query()->find($this->projectId);

        $this->attempt(function () use ($attendance, $project, $lat, $lng) {
            if ($project === null) {
                throw new ProjectNotOngoingException;
            }

            [$latitude, $longitude] = $this->location($lat, $lng);

            $this->withStoredPhoto($this->user(), 'in', fn (?string $photoPath) => $attendance->clockIn($this->user(), $project, $latitude, $longitude, $photoPath));

            $this->reset('projectId', 'lastClockOut', 'photo');
            Flux::toast(variant: 'success', text: __('Clocked in to :project.', ['project' => $project->name]));
        }, 'clock-in-photo');
    }

    public function clockOut(AttendanceService $attendance, mixed $lat = null, mixed $lng = null): void
    {
        $this->validate($this->photoRules(), $this->photoMessages());

        $this->attempt(function () use ($attendance, $lat, $lng) {
            [$latitude, $longitude] = $this->location($lat, $lng);

            $session = $this->withStoredPhoto($this->user(), 'out', fn (?string $photoPath) => $attendance->clockOut($this->user(), $latitude, $longitude, $photoPath));

            $session->load('project');
            $this->reset('photo');

            $this->lastClockOut = [
                'project' => $session->project->name,
                'start' => $session->clock_in_at->format('g:i A'),
                'end' => $session->clock_out_at?->format('g:i A') ?? '',
                'hours' => (string) $session->hours_worked,
            ];

            Flux::toast(variant: 'success', text: __('Clocked out. :hours hours recorded.', ['hours' => $session->hours_worked]));
        }, 'confirm-clock-out');
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
        return view('livewire.attendance.clock', [
            'photoRequired' => AttendancePhotos::required(),
        ]);
    }

    /**
     * Run a clock action and turn the service's rule violations into a friendly message on screen.
     */
    private function attempt(callable $action, string $modal): void
    {
        $this->resetErrorBag('clock');

        try {
            $action();
        } catch (AttendanceException $e) {
            $this->addError('clock', $e->getMessage());
        }

        // Close the photo/confirm modal either way, so a rule message shows on the page.
        Flux::modal($modal)->close();
        unset($this->openSession, $this->today);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
