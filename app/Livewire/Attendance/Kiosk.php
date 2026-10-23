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
use App\Services\KioskService;
use App\Support\AttendancePhotos;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Shared site kiosk at /kiosk (T-10.1). No login: a worker enters username and password, then clocks
 * in or out. Nobody is logged in to the web app, and the screen clears right after each success.
 *
 * @property-read User|null $user
 * @property-read Attendance|null $openDuty
 * @property-read Collection<int, Project> $projects
 */
#[Layout('layouts::kiosk')]
#[Title('Attendance Kiosk')]
class Kiosk extends Component
{
    use ReadsBrowserLocation;
    use TakesAttendancePhoto;

    /** How long a verified worker has to press Clock In / Clock Out. */
    public const VERIFIED_FOR_SECONDS = 120;

    public string $username = '';

    public string $password = '';

    /**
     * The Ongoing project picked for clock-in, as the select's value. Empty (not null) so the
     * "Select project" placeholder is the selected option; with null the browser shows the first
     * project while this stays empty, and the Clock In button stays disabled.
     */
    public string $projectId = '';

    /** The worker whose credentials were just checked. Locked: the browser cannot change it. */
    #[Locked]
    public ?int $verifiedUserId = null;

    #[Locked]
    public ?int $verifiedAt = null;

    /**
     * Step 1: check the username and password. The password is cleared after every attempt.
     */
    public function verify(KioskService $kiosk): void
    {
        $this->resetErrorBag();

        try {
            $this->validate([
                'username' => ['required', 'string', 'max:50'],
                'password' => ['required', 'string', 'max:255'],
            ]);

            $user = $kiosk->verify($this->username, $this->password, (string) request()->ip());
        } finally {
            $this->password = '';
        }

        $this->verifiedUserId = $user->id;
        $this->verifiedAt = now()->getTimestamp();
        $this->projectId = '';
    }

    public function clockIn(AttendanceService $attendance, mixed $lat = null, mixed $lng = null): void
    {
        $user = $this->verifiedUser();

        if ($user === null) {
            return;
        }

        $this->validate(['projectId' => ['required', 'integer'], ...$this->photoRules()], [
            'projectId.required' => __('Choose the project you are working on.'),
            ...$this->photoMessages(),
        ]);

        $project = Project::query()->find($this->projectId);

        $this->attempt(function () use ($attendance, $user, $project, $lat, $lng) {
            if ($project === null) {
                throw new ProjectNotOngoingException;
            }

            [$latitude, $longitude] = $this->location($lat, $lng);

            $duty = $this->withStoredPhoto($user, 'in', fn (?string $photoPath) => $attendance->clockIn($user, $project, $latitude, $longitude, $photoPath));

            $this->finish(__(':name — Clocked In at :time', [
                'name' => KioskService::nameFor($user->first_name, $user->last_name),
                'time' => $duty->clock_in_at->format('g:i A'),
            ]));
        });
    }

    public function clockOut(AttendanceService $attendance, mixed $lat = null, mixed $lng = null): void
    {
        $user = $this->verifiedUser();

        if ($user === null) {
            return;
        }

        $this->validate($this->photoRules(), $this->photoMessages());

        $this->attempt(function () use ($attendance, $user, $lat, $lng) {
            [$latitude, $longitude] = $this->location($lat, $lng);

            $duty = $this->withStoredPhoto($user, 'out', fn (?string $photoPath) => $attendance->clockOut($user, $latitude, $longitude, $photoPath));

            $this->finish(__(':name — Clocked Out at :time', [
                'name' => KioskService::nameFor($user->first_name, $user->last_name),
                'time' => $duty->clock_out_at?->format('g:i A') ?? '',
            ]));
        });
    }

    /**
     * "Not you? Start over": clear everything and go back to step 1.
     */
    public function startOver(): void
    {
        $this->reset();
        $this->resetErrorBag();
    }

    #[Computed]
    public function user(): ?User
    {
        return $this->verifiedUserId ? User::query()->find($this->verifiedUserId) : null;
    }

    #[Computed]
    public function openDuty(): ?Attendance
    {
        return $this->user ? app(AttendanceService::class)->openSessionFor($this->user) : null;
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Project::query()->ongoing()->orderBy('code')->get(['id', 'code', 'name', 'location']);
    }

    public function render(): View
    {
        return view('livewire.attendance.kiosk', [
            'displayName' => $this->user ? KioskService::nameFor($this->user->first_name, $this->user->last_name) : null,
            'photoRequired' => AttendancePhotos::required(),
        ]);
    }

    /**
     * The verified worker, or null (back to step 1 with a message) when nobody was verified,
     * the verification is older than two minutes, or the account is gone.
     */
    private function verifiedUser(): ?User
    {
        $fresh = $this->verifiedAt !== null && now()->getTimestamp() - $this->verifiedAt <= self::VERIFIED_FOR_SECONDS;
        $user = $fresh ? $this->user : null;

        // Re-check on every action: the role may have been removed since the credentials were verified.
        if ($user !== null && ! $user->can('attendance.clock')) {
            $user = null;
        }

        if ($user === null) {
            $this->startOver();
            $this->addError('username', __('Please enter your username and password again.'));
        }

        return $user;
    }

    /**
     * Run a clock action and show the service's rule violations as a friendly message.
     */
    private function attempt(callable $action): void
    {
        $this->resetErrorBag('kiosk');

        try {
            $action();
        } catch (AttendanceException $e) {
            $this->addError('kiosk', $e->getMessage());
        } catch (ValidationException $e) {
            $this->addError('kiosk', $e->validator->errors()->first());
        }

        // Close the photo modal either way, so a rule message shows on the page.
        Flux::modal('kiosk-photo-in')->close();
        Flux::modal('kiosk-photo-out')->close();
    }

    /**
     * A clock in/out succeeded: clear the screen for the next worker and refresh the live list.
     */
    private function finish(string $message): void
    {
        $this->reset();
        $this->resetErrorBag();
        unset($this->user, $this->openDuty);

        $this->dispatch('kiosk-duty-recorded');
        Flux::toast(variant: 'success', text: $message, duration: 6000);
    }
}
