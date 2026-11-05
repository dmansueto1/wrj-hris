<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Support\Costing\LaborCost;
use App\Support\Costing\ShiftMinutes;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin corrections of attendance (PRD US-14, T-12). A correction never edits the attendance:
 * it archives the original (soft delete, values unchanged) and creates the corrected record(s),
 * which point back to it through corrected_from_id, so the Admin can compare the two. The
 * corrected record's minutes, hours and labor cost are recomputed from the saved hourly rate and
 * shift, and it records who made the change and why. Like clock in/out, each call locks the
 * employee's row so it cannot race a clock-in.
 *
 * Validation errors use the correction form's field names: reason, clock_in, clock_out, split_at,
 * project_id, and attendance for the record itself.
 */
class AttendanceCorrectionService
{
    public const MIN_REASON_LENGTH = 10;

    /** The adjustment_reason column is a VARCHAR(255). */
    public const MAX_REASON_LENGTH = 255;

    /**
     * Set the missing clock-out of an attendance that is still Clocked In or flagged.
     */
    public function setClockOut(Attendance $a, Carbon $clockOut, string $reason, User $admin): Attendance
    {
        return $this->correct($a, $reason, $admin, function (Attendance $original) use ($clockOut) {
            if ($original->clock_out_at !== null) {
                throw ValidationException::withMessages(['clock_out' => __('This attendance already has a clock-out. Use Change times instead.')]);
            }

            return [$this->timed($original, $original->clock_in_at, $clockOut->copy()->startOfMinute())];
        })[0];
    }

    public function changeTimes(Attendance $a, Carbon $in, Carbon $out, string $reason, User $admin): Attendance
    {
        return $this->correct($a, $reason, $admin, fn (Attendance $original) => [
            $this->timed($original, $in->copy()->startOfMinute(), $out->copy()->startOfMinute()),
        ])[0];
    }

    /**
     * Charge the attendance to another project; its labor cost moves with it.
     */
    public function changeProject(Attendance $a, Project $project, string $reason, User $admin): Attendance
    {
        return $this->correct($a, $reason, $admin, function (Attendance $original) use ($project) {
            if ($original->clock_out_at === null) {
                throw ValidationException::withMessages(['project_id' => __('Set the clock-out first.')]);
            }

            if (! Project::query()->whereKey($project->id)->exists()) {
                throw ValidationException::withMessages(['project_id' => __('Choose a project that is not archived.')]);
            }

            $corrected = $this->copy($original, $original->clock_in_at, $original->clock_out_at);
            $corrected->project_id = $project->id;

            return [$corrected];
        })[0];
    }

    /**
     * Replace the attendance with two: one ending at $at and one from $at to the original
     * clock-out, with the same project, rate and shift. The clock-in location and photo stay with
     * the first, the clock-out ones go to the second.
     *
     * @return array{0: Attendance, 1: Attendance}
     */
    public function split(Attendance $a, Carbon $at, string $reason, User $admin): array
    {
        /** @var array{0: Attendance, 1: Attendance} */
        return $this->correct($a, $reason, $admin, function (Attendance $original) use ($at) {
            $at = $at->copy()->startOfMinute();

            if ($original->clock_out_at === null) {
                throw ValidationException::withMessages(['split_at' => __('Set the clock-out first.')]);
            }

            if ($at->lte($original->clock_in_at) || $at->gte($original->clock_out_at)) {
                throw ValidationException::withMessages(['split_at' => __('The split time must be between the clock-in and the clock-out.')]);
            }

            $first = $this->copy($original, $original->clock_in_at, $at);
            $first->forceFill(['clock_out_lat' => null, 'clock_out_lng' => null, 'clock_out_photo_path' => null]);

            $second = $this->copy($original, $at, $original->clock_out_at);
            $second->forceFill(['clock_in_lat' => null, 'clock_in_lng' => null, 'clock_in_photo_path' => null]);

            return [$first, $second];
        });
    }

    /**
     * Replace the attendance with a void copy: it stays on record but no longer counts anywhere.
     */
    public function void(Attendance $a, string $reason, User $admin): Attendance
    {
        return $this->correct($a, $reason, $admin, function (Attendance $original) {
            $voided = $original->replicate();
            $voided->status = Attendance::STATUS_VOID;

            return [$voided];
        })[0];
    }

    /**
     * Every correction that led to this attendance, oldest first: the record before each one
     * (archived), the record(s) it created, who made it, when and why. Empty for an original.
     *
     * @return list<array{before: Attendance, after: Collection<int, Attendance>, by: User|null, reason: string|null, at: Carbon|null}>
     */
    public function history(Attendance $attendance): array
    {
        $steps = [];
        $current = $attendance;

        while ($current->corrected_from_id !== null) {
            $before = Attendance::withTrashed()->with(['project', 'user'])->find($current->corrected_from_id);

            if ($before === null) {
                break;
            }

            $after = Attendance::withTrashed()
                ->with(['project', 'adjustedBy'])
                ->where('corrected_from_id', $before->id)
                ->orderBy('clock_in_at')
                ->get();

            $steps[] = [
                'before' => $before,
                'after' => $after,
                'by' => $after->first()?->adjustedBy,
                'reason' => $after->first()?->adjustment_reason,
                'at' => $before->deleted_at,
            ];

            $current = $before;
        }

        return array_reverse($steps);
    }

    /**
     * Checks the editor and the reason, then, in a transaction on a fresh, locked copy of the
     * attendance: builds the corrected record(s), archives the original and saves them pointing
     * back to it. The given model is refreshed afterwards (it is then archived).
     *
     * @param  callable(Attendance): list<Attendance>  $change  returns the unsaved corrected record(s)
     * @return list<Attendance>
     */
    private function correct(Attendance $a, string $reason, User $admin, callable $change): array
    {
        if (! $admin->can('attendance.correct')) {
            throw new AuthorizationException;
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw ValidationException::withMessages(['reason' => __('Enter a reason of at least :min characters.', ['min' => self::MIN_REASON_LENGTH])]);
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw ValidationException::withMessages(['reason' => __('Keep the reason to :max characters or fewer.', ['max' => self::MAX_REASON_LENGTH])]);
        }

        $records = DB::transaction(function () use ($a, $change, $reason, $admin) {
            User::query()->lockForUpdate()->find($a->user_id);

            $original = Attendance::query()->lockForUpdate()->find($a->id);

            if ($original === null) {
                throw ValidationException::withMessages(['attendance' => __('This attendance was already corrected. Refresh the page and correct the current record.')]);
            }

            if ($original->status === Attendance::STATUS_VOID) {
                throw ValidationException::withMessages(['attendance' => __('This attendance is void and cannot be corrected.')]);
            }

            $records = $change($original);

            $original->delete();

            foreach ($records as $record) {
                $record->forceFill([
                    'corrected_from_id' => $original->id,
                    'adjusted_by' => $admin->id,
                    'adjustment_reason' => $reason,
                ])->save();
            }

            return $records;
        });

        $a->refresh();

        return $records;
    }

    /**
     * A corrected copy with new times, after checking them.
     */
    private function timed(Attendance $original, CarbonInterface $in, CarbonInterface $out): Attendance
    {
        // The saved shift windows are for the original work date; on another day no minute would count.
        if ($original->shift_windows !== null && $in->toDateString() !== $original->work_date->toDateString()) {
            throw ValidationException::withMessages(['clock_in' => __('The clock-in must stay on :date: this attendance is counted against that day\'s shift.', ['date' => $original->work_date->format('M j, Y')])]);
        }

        if ($in->gt(now())) {
            throw ValidationException::withMessages(['clock_in' => __('The clock-in cannot be in the future.')]);
        }

        if ($out->gt(now())) {
            throw ValidationException::withMessages(['clock_out' => __('The clock-out cannot be in the future.')]);
        }

        if ($out->lte($in)) {
            throw ValidationException::withMessages(['clock_out' => __('The clock-out must be after the clock-in.')]);
        }

        // Void and archived records never overlap (both are left out by the query).
        $overlaps = Attendance::query()
            ->where('user_id', $original->user_id)
            ->whereKeyNot($original->id)
            ->where('status', '!=', Attendance::STATUS_VOID)
            ->where('clock_in_at', '<', $out)
            ->where(fn ($query) => $query->whereNull('clock_out_at')->orWhere('clock_out_at', '>', $in))
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['clock_out' => __('These times overlap another attendance of this employee.')]);
        }

        return $this->copy($original, $in, $out);
    }

    /**
     * An unsaved, Adjusted copy of the original with the given times and its minutes, hours and
     * labor cost recomputed from the saved rate, counting only the minutes inside the saved shift
     * (every minute when Flexible). The work date is the clock-in date (PRD 7.4 rule 6).
     */
    private function copy(Attendance $original, CarbonInterface $in, CarbonInterface $out): Attendance
    {
        $minutes = ShiftMinutes::counted($in, $out, $original->shift_windows);
        $labor = LaborCost::forMinutes($minutes, $original->hourly_rate);

        return $original->replicate()->forceFill([
            'work_date' => $in->toDateString(),
            'clock_in_at' => $in,
            'clock_out_at' => $out,
            'clocked_minutes' => (int) $in->diffInMinutes($out),
            'minutes_worked' => $minutes,
            'hours_worked' => $labor['hours'],
            'labor_cost' => $labor['cost'],
            'status' => Attendance::STATUS_ADJUSTED,
        ]);
    }
}
