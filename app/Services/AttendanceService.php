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
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Opens and closes clock-in sessions (PRD 6 and 7). Every call runs in a transaction that locks
 * the user's row first, so a double tap waits for the first call and is then rejected.
 */
class AttendanceService
{
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
                'status' => Attendance::STATUS_OPEN,
                'clock_in_lat' => $lat,
                'clock_in_lng' => $lng,
                'clock_in_photo_path' => $photoPath,
            ])->save();

            return $session;
        });
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
            $minutes = (int) $session->clock_in_at->diffInMinutes($clockOut);
            $labor = LaborCost::forMinutes($minutes, $session->hourly_rate);

            $session->forceFill([
                'clock_out_at' => $clockOut,
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
