<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * A closed 4.5-hour morning session at ₱80.00/hr (PRD 7.1 example).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clockIn = now()->startOfDay()->setTime(7, 0);

        return [
            'user_id' => User::factory(),
            'project_id' => Project::factory()->ongoing(),
            'work_date' => $clockIn->toDateString(),
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockIn->addMinutes(270),
            'hourly_rate' => '80.00',
            'minutes_worked' => 270,
            'hours_worked' => '4.50',
            'labor_cost' => '360.00',
            'status' => Attendance::STATUS_CLOSED,
        ];
    }

    /**
     * The same 7:00 to 11:30 session on another work date ("2026-10-19").
     */
    public function onDate(string $date): static
    {
        return $this->state(function (array $attributes) use ($date) {
            $clockIn = Carbon::parse($date.' 07:00:00');

            return [
                'work_date' => $date,
                'clock_in_at' => $clockIn,
                'clock_out_at' => $attributes['clock_out_at'] === null ? null : $clockIn->copy()->addMinutes(270),
            ];
        });
    }

    public function void(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Attendance::STATUS_VOID]);
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'clock_out_at' => null,
            'minutes_worked' => null,
            'hours_worked' => null,
            'labor_cost' => null,
            'status' => Attendance::STATUS_OPEN,
        ]);
    }
}
