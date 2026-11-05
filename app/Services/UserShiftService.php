<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserShift;
use App\Support\ShiftWindows;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A user's weekly shift (T-11.2): the type (one shift a day, two shifts a day or flexible), the
 * In / Out times per weekday and the rest days. T-11.3 counts attendance against windowsFor().
 *
 * A day is ['is_rest_day' => bool, 'first_in' => '08:00', 'first_out' => …, 'second_in' => …,
 * 'second_out' => …], with '' (or null) for an empty time.
 */
class UserShiftService
{
    /** ISO weekdays: 1 = Monday … 7 = Sunday. */
    public const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    private const SHORT_DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    /** The times a new schedule starts with: Monday to Saturday working, Sunday as rest day. */
    private const DEFAULT_TIMES = [
        User::SHIFT_ONE => ['08:00', '17:00', '', ''],
        User::SHIFT_TWO => ['08:00', '12:00', '13:00', '17:00'],
    ];

    /**
     * The saved week, Monday first; a day without a row is a working day with empty times.
     *
     * @return array<int, array{is_rest_day: bool, first_in: string, first_out: string, second_in: string, second_out: string}>
     */
    public function schedule(User $user): array
    {
        $rows = $user->shifts()->get()->keyBy('weekday');

        $week = [];
        foreach (array_keys(self::WEEKDAYS) as $weekday) {
            /** @var UserShift|null $row */
            $row = $rows->get($weekday);

            $week[$weekday] = [
                'is_rest_day' => (bool) $row?->is_rest_day,
                'first_in' => $row->first_in ?? '',
                'first_out' => $row->first_out ?? '',
                'second_in' => $row->second_in ?? '',
                'second_out' => $row->second_out ?? '',
            ];
        }

        return $week;
    }

    /**
     * A new week for a shift type: Monday to Saturday with the default times, Sunday as rest day.
     *
     * @return array<int, array{is_rest_day: bool, first_in: string, first_out: string, second_in: string, second_out: string}>
     */
    public function defaults(string $type): array
    {
        [$firstIn, $firstOut, $secondIn, $secondOut] = self::DEFAULT_TIMES[$type] ?? ['', '', '', ''];

        $week = [];
        foreach (array_keys(self::WEEKDAYS) as $weekday) {
            $rest = $weekday === 7;

            $week[$weekday] = [
                'is_rest_day' => $rest,
                'first_in' => $rest ? '' : $firstIn,
                'first_out' => $rest ? '' : $firstOut,
                'second_in' => $rest ? '' : $secondIn,
                'second_out' => $rest ? '' : $secondOut,
            ];
        }

        return $week;
    }

    /**
     * Give a new user their shift (Add user). Without a week, the type's defaults are used
     * (Monday to Saturday, Sunday as rest day). Validated like update(); no activity is logged,
     * since creating the user is the event.
     *
     * @param  array<int|string, array<string, mixed>>|null  $days
     */
    public function startWith(User $user, string $type, ?array $days = null): void
    {
        $days ??= $this->defaults($type);

        $this->validateSchedule($type, $days);
        $this->write($user, $type, $days);
    }

    /**
     * Save the user's shift. Flexible removes the week; a rest day keeps no times; One shift a day
     * keeps no afternoon times. Throws a ValidationException with keys like "days.1.first_out".
     *
     * @param  array<int|string, array<string, mixed>>  $days
     */
    public function update(User $user, string $type, array $days, User $admin): void
    {
        $this->validateSchedule($type, $days);

        DB::transaction(function () use ($user, $type, $days, $admin) {
            User::query()->lockForUpdate()->findOrFail($user->id);

            $old = ['type' => $user->shift_type, 'days' => $user->shift_type === User::SHIFT_FLEXIBLE ? [] : $this->schedule($user)];

            $this->write($user, $type, $days);

            activity('shifts')
                ->performedOn($user)
                ->causedBy($admin)
                ->withProperties([
                    'old' => $old,
                    'new' => ['type' => $type, 'days' => $type === User::SHIFT_FLEXIBLE ? [] : $this->schedule($user)],
                ])
                ->log('Changed shift');
        });
    }

    /**
     * Check a shift type and week; throws a ValidationException with keys like "days.1.first_out"
     * (and "days" when there is no working day). Add user runs it together with the other fields.
     *
     * @param  array<int|string, array<string, mixed>>  $days
     */
    public function validateSchedule(string $type, array $days): void
    {
        $this->validate($type, $days);
    }

    /**
     * "Working days: Mon–Sat · Rest day: Sun" (or "Rest days: Sat, Sun"). Runs of three or more
     * days read as a range.
     *
     * @param  array<int|string, array<string, mixed>>  $days
     */
    public function summary(array $days): string
    {
        $rest = array_values(array_filter(array_keys(self::WEEKDAYS), fn (int $weekday) => (bool) ($days[$weekday]['is_rest_day'] ?? false)));
        $working = array_values(array_diff(array_keys(self::WEEKDAYS), $rest));

        $workingText = $working === [] ? __('Working days: none') : __('Working days: :days', ['days' => $this->dayList($working)]);
        $restText = match (count($rest)) {
            0 => __('No rest day'),
            1 => __('Rest day: :days', ['days' => $this->dayList($rest)]),
            default => __('Rest days: :days', ['days' => $this->dayList($rest)]),
        };

        return $workingText.' · '.$restText;
    }

    /**
     * Today's shift as shown on Clock In/Out and the kiosk: "8:00 AM–12:00 PM, 1:00 PM–5:00 PM",
     * "Rest day", or null for a Flexible user (T-11.3).
     */
    public function todayLabel(User $user): ?string
    {
        $windows = $this->windowsFor($user, now());

        if ($windows === null) {
            return null;
        }

        return $windows === [] ? __('Rest day') : ShiftWindows::label($windows);
    }

    /**
     * The user's shift windows on a work date (Asia/Manila), for T-11.3: null for Flexible, []
     * for a rest day.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>|null
     */
    public function windowsFor(User $user, CarbonInterface $workDate): ?array
    {
        $row = $user->shift_type === User::SHIFT_FLEXIBLE
            ? null
            : $user->shifts()->where('weekday', $workDate->isoWeekday())->first();

        return self::windows($user->shift_type, $row?->only(['is_rest_day', 'first_in', 'first_out', 'second_in', 'second_out']), $workDate);
    }

    /**
     * Shift windows for one day of a given type. A One-shift window whose Out is not after its In
     * ends the next day (night shift, 22:00 → 06:00). Pure: no database.
     *
     * @param  array<string, mixed>|null  $day
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>|null
     */
    public static function windows(string $type, ?array $day, CarbonInterface $workDate): ?array
    {
        if ($type === User::SHIFT_FLEXIBLE) {
            return null;
        }

        if ($day === null || ($day['is_rest_day'] ?? false)) {
            return [];
        }

        // In the work date's own timezone (Asia/Manila across the app).
        $date = CarbonImmutable::parse($workDate->toDateString(), $workDate->getTimezone());
        $at = fn (mixed $time) => $date->setTimeFromTimeString(substr((string) $time, 0, 5));

        $pairs = $type === User::SHIFT_TWO
            ? [['first_in', 'first_out'], ['second_in', 'second_out']]
            : [['first_in', 'first_out']];

        $windows = [];
        foreach ($pairs as [$inKey, $outKey]) {
            if (empty($day[$inKey]) || empty($day[$outKey])) {
                continue;
            }

            $start = $at($day[$inKey]);
            $end = $at($day[$outKey]);

            $windows[] = [$start, $end->lte($start) ? $end->addDay() : $end];
        }

        return $windows;
    }

    /**
     * Save the type and the week: Flexible removes the week, a rest day keeps no times, One shift
     * a day keeps no afternoon times.
     *
     * @param  array<int|string, array<string, mixed>>  $days
     */
    private function write(User $user, string $type, array $days): void
    {
        $user->forceFill(['shift_type' => $type])->save();

        if ($type === User::SHIFT_FLEXIBLE) {
            $user->shifts()->delete();

            return;
        }

        foreach (array_keys(self::WEEKDAYS) as $weekday) {
            $day = $days[$weekday] ?? [];
            $rest = (bool) ($day['is_rest_day'] ?? false);
            $time = fn (string $key) => $rest ? null : ($this->text($day[$key] ?? null) ?: null);

            $row = UserShift::query()->where('user_id', $user->id)->where('weekday', $weekday)->first()
                ?? (new UserShift)->forceFill(['user_id' => $user->id, 'weekday' => $weekday]);
            $row->forceFill([
                'is_rest_day' => $rest,
                'first_in' => $time('first_in'),
                'first_out' => $time('first_out'),
                'second_in' => $type === User::SHIFT_TWO ? $time('second_in') : null,
                'second_out' => $type === User::SHIFT_TWO ? $time('second_out') : null,
            ])->save();
        }
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $days
     */
    private function validate(string $type, array $days): void
    {
        $errors = [];

        if (! isset(User::SHIFT_TYPES[$type])) {
            throw ValidationException::withMessages(['type' => __('Choose a shift type.')]);
        }

        if ($type === User::SHIFT_FLEXIBLE) {
            return;
        }

        $keys = $type === User::SHIFT_TWO ? ['first_in', 'first_out', 'second_in', 'second_out'] : ['first_in', 'first_out'];
        $workingDays = 0;

        foreach (self::WEEKDAYS as $weekday => $dayName) {
            $day = $days[$weekday] ?? [];

            if ((bool) ($day['is_rest_day'] ?? false)) {
                continue; // a rest day needs no times
            }

            $workingDays++;
            $times = [];

            foreach ($keys as $key) {
                $value = $this->text($day[$key] ?? null);

                $check = Validator::make([$key => $value], [$key => ['required', 'date_format:H:i']]);
                if ($check->fails()) {
                    $errors["days.{$weekday}.{$key}"] = $value === ''
                        ? __('Enter a time for :day.', ['day' => __($dayName)])
                        : __('Use a time like 08:00 for :day.', ['day' => __($dayName)]);

                    continue;
                }

                $times[$key] = $value;
            }

            if (count($times) !== count($keys)) {
                continue;
            }

            // "H:i" strings compare in time order.
            $firstIn = $times['first_in'] ?? '';
            $firstOut = $times['first_out'] ?? '';
            $secondIn = $times['second_in'] ?? '';
            $secondOut = $times['second_out'] ?? '';

            if ($type === User::SHIFT_ONE && $firstIn === $firstOut) {
                $errors["days.{$weekday}.first_out"] = __('Out must be different from In on :day.', ['day' => __($dayName)]);
            }

            if ($type === User::SHIFT_TWO) {
                if ($firstOut <= $firstIn) {
                    $errors["days.{$weekday}.first_out"] = __('Morning Out must be after Morning In on :day.', ['day' => __($dayName)]);
                } elseif ($secondIn < $firstOut) {
                    $errors["days.{$weekday}.second_in"] = __('Afternoon In must not be before Morning Out on :day.', ['day' => __($dayName)]);
                } elseif ($secondOut <= $secondIn) {
                    $errors["days.{$weekday}.second_out"] = __('Afternoon Out must be after Afternoon In on :day.', ['day' => __($dayName)]);
                }
            }
        }

        if ($workingDays === 0) {
            $errors['days'] = __('Choose at least one working day, or set the shift type to Flexible.');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * "Mon–Sat" for three or more days in a row, otherwise "Sat, Sun".
     *
     * @param  list<int>  $weekdays
     */
    private function dayList(array $weekdays): string
    {
        $runs = [];
        foreach ($weekdays as $weekday) {
            $last = array_key_last($runs);

            if ($last !== null && end($runs[$last]) === $weekday - 1) {
                $runs[$last][] = $weekday;
            } else {
                $runs[] = [$weekday];
            }
        }

        $parts = [];
        foreach ($runs as $run) {
            if (count($run) >= 3) {
                $parts[] = __(self::SHORT_DAYS[$run[0]]).'–'.__(self::SHORT_DAYS[end($run)]);
            } else {
                foreach ($run as $weekday) {
                    $parts[] = __(self::SHORT_DAYS[$weekday]);
                }
            }
        }

        return implode(', ', $parts);
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
