<?php

namespace App\Support;

use App\Models\Attendance;
use Illuminate\Support\Collection;

/**
 * One person's attendance on one day, as the attendance tables show it: the records placed in
 * Morning / Afternoon (or across both), and the day's counted hours and clocked time.
 */
final class AttendanceDay
{
    /**
     * @param  Collection<int, Attendance>  $attendances  that day's non-void attendance, by clock-in time
     */
    private function __construct(private Collection $attendances) {}

    /**
     * @param  iterable<int, Attendance>  $attendances
     */
    public static function of(iterable $attendances): self
    {
        return new self(collect($attendances)->values());
    }

    public function isEmpty(): bool
    {
        return $this->attendances->isEmpty();
    }

    /**
     * @return Collection<int, Attendance>
     */
    public function all(): Collection
    {
        return $this->attendances;
    }

    /**
     * True when an attendance runs from the morning into the afternoon (or is a one-shift day):
     * the row then shows all of the day's records in one cell across both columns.
     */
    public function spansBoth(): bool
    {
        return $this->attendances->contains(fn (Attendance $attendance) => $this->slot($attendance) === DaySlots::WHOLE);
    }

    /**
     * @return Collection<int, Attendance>
     */
    public function morning(): Collection
    {
        return $this->attendances->filter(fn (Attendance $attendance) => $this->slot($attendance) === DaySlots::MORNING)->values();
    }

    /**
     * @return Collection<int, Attendance>
     */
    public function afternoon(): Collection
    {
        return $this->attendances->filter(fn (Attendance $attendance) => $this->slot($attendance) === DaySlots::AFTERNOON)->values();
    }

    /**
     * The four time columns of the attendance tables. A morning record fills Morning In / Out, an
     * afternoon record Afternoon In / Out, and one running through lunch (or a one-shift day)
     * Morning In and Afternoon Out.
     *
     * @return array{morning_in: list<array{attendance: Attendance, part: string}>, morning_out: list<array{attendance: Attendance, part: string}>, afternoon_in: list<array{attendance: Attendance, part: string}>, afternoon_out: list<array{attendance: Attendance, part: string}>}
     */
    public function columns(): array
    {
        $columns = ['morning_in' => [], 'morning_out' => [], 'afternoon_in' => [], 'afternoon_out' => []];

        foreach ($this->attendances as $attendance) {
            [$in, $out] = match ($this->slot($attendance)) {
                DaySlots::MORNING => ['morning_in', 'morning_out'],
                DaySlots::AFTERNOON => ['afternoon_in', 'afternoon_out'],
                default => ['morning_in', 'afternoon_out'],
            };

            $columns[$in][] = ['attendance' => $attendance, 'part' => 'in'];
            $columns[$out][] = ['attendance' => $attendance, 'part' => 'out'];
        }

        return $columns;
    }

    /**
     * The attendance still Clocked In that day, if any.
     */
    public function open(): ?Attendance
    {
        return $this->attendances->first(fn (Attendance $attendance) => $attendance->status === Attendance::STATUS_OPEN);
    }

    /**
     * Counted hours of the day's clocked-out attendance, "8.00"; null when none is clocked out yet.
     */
    public function hours(): ?string
    {
        $done = $this->attendances->filter(fn (Attendance $attendance) => $attendance->hours_worked !== null);

        if ($done->isEmpty()) {
            return null;
        }

        return Money::fromCentavos((int) $done->sum(fn (Attendance $attendance) => Money::toCentavos((string) $attendance->hours_worked)));
    }

    /**
     * Minutes clocked outside the shift that day (they do not count).
     */
    public function uncountedMinutes(): int
    {
        return (int) $this->attendances->sum(fn (Attendance $attendance) => $attendance->uncountedMinutes());
    }

    /**
     * The real clocked time of the day, "9h 10m".
     */
    public function clockedLabel(): string
    {
        $minutes = (int) $this->attendances->sum(fn (Attendance $attendance) => (int) $attendance->clocked_minutes);

        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Clocked, but none of it inside the shift (e.g. a rest day).
     */
    public function isOutsideShift(): bool
    {
        $done = $this->attendances->filter(fn (Attendance $attendance) => $attendance->clocked_minutes !== null);

        return $done->isNotEmpty() && $done->every(fn (Attendance $attendance) => $attendance->isOutsideShift());
    }

    /**
     * The shift saved on the day's attendance, for the "Clocked …" tooltip.
     */
    public function shiftLabel(): string
    {
        return ($this->attendances->first(fn (Attendance $attendance) => $attendance->shift_windows !== null) ?? $this->attendances->first())?->shiftLabel() ?? '';
    }

    private function slot(Attendance $attendance): string
    {
        return DaySlots::classify(
            $attendance->clock_in_at,
            $attendance->clock_out_at ?? now()->max($attendance->clock_in_at),
            $attendance->shift_windows,
        );
    }
}
