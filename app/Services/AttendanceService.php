<?php

namespace App\Services;

use App\Exceptions\Attendance\AlreadyClockedInException;
use App\Exceptions\Attendance\InactiveUserException;
use App\Exceptions\Attendance\NotClockedInException;
use App\Exceptions\Attendance\ProjectNotOngoingException;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Support\Costing\LaborCost;
use App\Support\Costing\ShiftMinutes;
use App\Support\Money;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Opens and closes clock-in sessions (PRD 6 and 7). Every call runs in a transaction that locks
 * the user's row first, so a double tap waits for the first call and is then rejected.
 */
class AttendanceService
{
    public function __construct(private UserShiftService $shifts) {}

    public function clockIn(User $user, Project $project, ?float $lat = null, ?float $lng = null, ?string $photoPath = null): Attendance
    {
        return DB::transaction(function () use ($user, $project, $lat, $lng, $photoPath) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->status !== User::STATUS_ACTIVE) {
                throw new InactiveUserException;
            }

            if (Attendance::query()->open()->where('user_id', $lockedUser->id)->exists()) {
                throw new AlreadyClockedInException;
            }

            // Re-read the project: it may have been archived or changed status since the screen loaded.
            $currentProject = Project::query()->ongoing()->find($project->id);

            if ($currentProject === null) {
                throw new ProjectNotOngoingException;
            }

            $clockIn = now()->startOfMinute();

            $session = new Attendance;
            $session->forceFill([
                'user_id' => $lockedUser->id,
                'project_id' => $currentProject->id,
                'work_date' => $clockIn->toDateString(),
                'clock_in_at' => $clockIn,
                'hourly_rate' => $lockedUser->hourly_rate,
                'shift_windows' => $this->shiftWindowsAt($lockedUser, $clockIn),
                'status' => Attendance::STATUS_OPEN,
                'clock_in_lat' => $lat,
                'clock_in_lng' => $lng,
                'clock_in_photo_path' => $photoPath,
            ])->save();

            return $session;
        });
    }

    /**
     * The shift windows to save on an attendance that starts at $clockIn, as [{start, end}] with
     * "Y-m-d H:i" times. The shift is saved like the hourly rate, so a later shift change never
     * alters it (T-11.3). null = Flexible, [] = rest day. A night shift that started the day
     * before and is still running (e.g. clock in at 00:10 on a 22:00–06:00 shift) counts too.
     *
     * @return list<array{start: string, end: string}>|null
     */
    public function shiftWindowsAt(User $user, CarbonInterface $clockIn): ?array
    {
        $windows = $this->shifts->windowsFor($user, $clockIn);

        if ($windows === null) {
            return null;
        }

        $startOfDay = $clockIn->copy()->startOfDay();
        $overnight = array_values(array_filter(
            $this->shifts->windowsFor($user, $clockIn->copy()->subDay()) ?? [],
            fn (array $window) => $window[1]->gt($startOfDay),
        ));

        return array_map(fn (array $window) => [
            'start' => $window[0]->format('Y-m-d H:i'),
            'end' => $window[1]->format('Y-m-d H:i'),
        ], [...$overnight, ...$windows]);
    }

    /**
     * Flag attendance still Clocked In longer than the flag time in System Settings (12 hours by
     * default, PRD 7.4 rule 7) after clock-in, for the Admin to
     * correct (PRD 7.4 rule 7, T-13). A flagged record no longer counts as open, so it never
     * blocks the next clock-in. Returns how many were flagged; a second run flags nothing.
     */
    public function flagOpen(): int
    {
        return Attendance::query()
            ->open()
            ->where('clock_in_at', '<', now()->subHours(Settings::int('attendance_flag_after_hours')))
            ->update(['status' => Attendance::STATUS_FLAGGED]);
    }

    /**
     * The user's flagged attendance (not clocked out, waiting for the Admin), oldest first.
     *
     * @return Collection<int, Attendance>
     */
    public function flaggedFor(User $user): Collection
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->where('status', Attendance::STATUS_FLAGGED)
            ->orderBy('clock_in_at')
            ->get();
    }

    /**
     * The session the user is clocked in to right now, if any.
     */
    public function openSessionFor(User $user): ?Attendance
    {
        return Attendance::query()->open()->with('project')->where('user_id', $user->id)->first();
    }

    /**
     * The user's sessions with today's work date (void ones left out) and the day's total hours:
     * the sum of the hours shown on the counted sessions (PRD US-12), as in the attendance history.
     *
     * @return array{sessions: Collection<int, Attendance>, total_hours: string}
     */
    public function today(User $user): array
    {
        $sessions = Attendance::query()
            ->with('project')
            ->where('user_id', $user->id)
            ->whereDate('work_date', now()->toDateString())
            ->where('status', '!=', Attendance::STATUS_VOID)
            ->orderBy('clock_in_at')
            ->get();

        // Same as the attendance history (T-11): the total is the sum of the hours shown per row.
        $hundredths = (int) $sessions
            ->filter(fn (Attendance $session) => in_array($session->status, Attendance::COUNTED_STATUSES, true))
            ->sum(fn (Attendance $session) => Money::toCentavos((string) $session->hours_worked));

        return ['sessions' => $sessions, 'total_hours' => Money::fromCentavos($hundredths)];
    }

    public function clockOut(User $user, ?float $lat = null, ?float $lng = null, ?string $photoPath = null): Attendance
    {
        return DB::transaction(function () use ($user, $lat, $lng, $photoPath) {
            User::query()->lockForUpdate()->findOrFail($user->id);

            $session = Attendance::query()->open()->where('user_id', $user->id)->lockForUpdate()->first();

            if ($session === null) {
                throw new NotClockedInException;
            }

            $clockOut = now()->startOfMinute();
            $clocked = (int) $session->clock_in_at->diffInMinutes($clockOut);
            // Only the minutes inside the shift saved at clock-in count (T-11.3); every minute when
            // Flexible or when the attendance started before shifts existed.
            $minutes = ShiftMinutes::counted($session->clock_in_at, $clockOut, $session->shift_windows);
            $labor = LaborCost::forMinutes($minutes, $session->hourly_rate);

            $session->forceFill([
                'clock_out_at' => $clockOut,
                'clocked_minutes' => $clocked,
                'minutes_worked' => $minutes,
                'hours_worked' => $labor['hours'],
                'labor_cost' => $labor['cost'],
                'status' => Attendance::STATUS_CLOSED,
                'clock_out_lat' => $lat,
                'clock_out_lng' => $lng,
                'clock_out_photo_path' => $photoPath,
            ])->save();

            return $session;
        });
    }
}
