<?php

namespace App\Livewire\Attendance;

use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceCorrectionService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The "Correct attendance" and "Compare with original" modals on All Attendance (T-12), Admin
 * only. One of each for every row: the row's "Correct" menu dispatches correct-attendance or
 * compare-attendance with the attendance id. Corrections need a recent password confirmation,
 * like the Users pages.
 *
 * @property-read Attendance|null $attendance
 * @property-read Collection<int, Project> $projects
 * @property-read list<array{before: Attendance, after: EloquentCollection<int, Attendance>, by: User|null, reason: string|null, at: Carbon|null}> $history
 */
class Correction extends Component
{
    public const SET_CLOCK_OUT = 'set_clock_out';

    public const CHANGE_TIMES = 'change_times';

    public const CHANGE_PROJECT = 'change_project';

    public const SPLIT = 'split';

    public const VOID = 'void';

    public const ACTIONS = [
        self::SET_CLOCK_OUT => 'Set clock-out',
        self::CHANGE_TIMES => 'Change times',
        self::CHANGE_PROJECT => 'Change project',
        self::SPLIT => 'Split',
        self::VOID => 'Void',
    ];

    private const INPUT_FORMAT = 'Y-m-d\TH:i';

    public ?int $attendanceId = null;

    public string $action = self::CHANGE_TIMES;

    public string $clock_in = '';

    public string $clock_out = '';

    public string $split_at = '';

    public string $project_id = '';

    public string $reason = '';

    /** The corrected record shown in the comparison modal. */
    public ?int $compareId = null;

    /**
     * Runs on every request (first render and every action), so the permission is re-checked each time.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('attendance.correct'), 403);
    }

    #[On('correct-attendance')]
    public function open(int $id): void
    {
        if (! $this->passwordConfirmed()) {
            return;
        }

        $attendance = Attendance::query()->where('status', '!=', Attendance::STATUS_VOID)->findOrFail($id);

        $this->resetValidation();
        $this->attendanceId = $attendance->id;
        $this->action = $attendance->clock_out_at === null ? self::SET_CLOCK_OUT : self::CHANGE_TIMES;
        $this->clock_in = $attendance->clock_in_at->format(self::INPUT_FORMAT);
        $this->clock_out = $attendance->clock_out_at?->format(self::INPUT_FORMAT) ?? '';
        $this->split_at = '';
        $this->project_id = (string) $attendance->project_id;
        $this->reason = '';
        unset($this->attendance);

        Flux::modal('attendance-correction')->show();
    }

    public function save(AttendanceCorrectionService $corrections): void
    {
        if (! $this->passwordConfirmed()) {
            return;
        }

        $attendance = $this->attendance;

        // Archived since the modal opened, e.g. another Admin corrected it first.
        if ($attendance === null) {
            Flux::modal('attendance-correction')->close();
            Flux::toast(variant: 'danger', text: __('This attendance was already corrected. Correct the current record instead.'));
            $this->dispatch('attendance-corrected');

            return;
        }

        $this->validate([
            'action' => ['required', 'in:'.implode(',', array_keys($this->availableActions()))],
            'clock_in' => [$this->action === self::CHANGE_TIMES ? 'required' : 'nullable', 'date_format:'.self::INPUT_FORMAT],
            'clock_out' => [in_array($this->action, [self::SET_CLOCK_OUT, self::CHANGE_TIMES], true) ? 'required' : 'nullable', 'date_format:'.self::INPUT_FORMAT],
            'split_at' => [$this->action === self::SPLIT ? 'required' : 'nullable', 'date_format:'.self::INPUT_FORMAT],
            'project_id' => [$this->action === self::CHANGE_PROJECT ? 'required' : 'nullable', 'integer', Rule::exists('projects', 'id')->withoutTrashed()],
            'reason' => ['required', 'string', 'min:'.AttendanceCorrectionService::MIN_REASON_LENGTH, 'max:'.AttendanceCorrectionService::MAX_REASON_LENGTH],
        ], [], [
            'clock_in' => __('clock-in'),
            'clock_out' => __('clock-out'),
            'split_at' => __('split time'),
            'project_id' => __('project'),
        ]);

        $reason = trim($this->reason);
        $admin = $this->admin();

        match ($this->action) {
            self::SET_CLOCK_OUT => $corrections->setClockOut($attendance, $this->time($this->clock_out), $reason, $admin),
            self::CHANGE_TIMES => $corrections->changeTimes($attendance, $this->time($this->clock_in), $this->time($this->clock_out), $reason, $admin),
            self::CHANGE_PROJECT => $corrections->changeProject($attendance, Project::query()->findOrFail((int) $this->project_id), $reason, $admin),
            self::SPLIT => $corrections->split($attendance, $this->time($this->split_at), $reason, $admin),
            self::VOID => $corrections->void($attendance, $reason, $admin),
        };

        Flux::modal('attendance-correction')->close();
        Flux::toast(variant: 'success', text: __('Attendance corrected. The original is kept for comparison.'));
        $this->reset('attendanceId', 'reason');
        $this->dispatch('attendance-corrected');
    }

    /**
     * Show a corrected record next to its original (and any corrections in between).
     */
    #[On('compare-attendance')]
    public function compare(int $id): void
    {
        $this->compareId = Attendance::query()->whereNotNull('corrected_from_id')->findOrFail($id)->id;
        unset($this->history);

        Flux::modal('attendance-original')->show();
    }

    /**
     * @return list<array{before: Attendance, after: EloquentCollection<int, Attendance>, by: User|null, reason: string|null, at: Carbon|null}>
     */
    #[Computed]
    public function history(): array
    {
        $attendance = $this->compareId === null ? null : Attendance::withTrashed()->find($this->compareId);

        return $attendance === null ? [] : app(AttendanceCorrectionService::class)->history($attendance);
    }

    /**
     * The actions that fit the attendance: Set clock-out only without a clock-out; Change project
     * and Split only with one.
     *
     * @return array<string, string>
     */
    public function availableActions(): array
    {
        $hasClockOut = $this->attendance?->clock_out_at !== null;

        return array_filter(
            self::ACTIONS,
            fn (string $action) => match ($action) {
                self::SET_CLOCK_OUT => ! $hasClockOut,
                self::CHANGE_PROJECT, self::SPLIT => $hasClockOut,
                default => true,
            },
            ARRAY_FILTER_USE_KEY,
        );
    }

    #[Computed]
    public function attendance(): ?Attendance
    {
        return $this->attendanceId === null
            ? null
            : Attendance::query()->with(['user', 'project'])->find($this->attendanceId);
    }

    /**
     * Projects to move an attendance to: every project that is not archived, A–Z.
     *
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Project::query()->orderBy('name')->get(['id', 'code', 'name']);
    }

    public function render(): View
    {
        return view('livewire.attendance.correction', [
            'actions' => $this->availableActions(),
        ]);
    }

    /**
     * Same check as the password.confirm middleware; when the confirmation is missing or too old,
     * send the Admin to confirm it and back to this page.
     */
    private function passwordConfirmed(): bool
    {
        $confirmedAt = (int) session('auth.password_confirmed_at', 0);

        if (time() - $confirmedAt <= (int) config('auth.password_timeout', 10800)) {
            return true;
        }

        redirect()->setIntendedUrl(url()->previous(route('attendance.index')));
        $this->redirectRoute('password.confirm');

        return false;
    }

    private function time(string $value): Carbon
    {
        return Carbon::createFromFormat(self::INPUT_FORMAT, $value);
    }

    private function admin(): User
    {
        /** @var User $admin */
        $admin = Auth::user();

        return $admin;
    }
}
