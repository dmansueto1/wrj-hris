<?php

namespace App\Livewire\Users\Concerns;

use App\Models\User;
use App\Services\UserShiftService;
use Livewire\Attributes\Computed;

/**
 * The shift editor (T-11.2) shared by Add user and the user's Shift card: the shift type and the
 * Monday-to-Sunday In / Out times and rest days. Render it with the
 * `livewire.users.partials.shift-fields` partial and pass shiftViewData() to the view.
 *
 * @property-read string $summary
 */
trait EditsShift
{
    public string $type = User::SHIFT_FLEXIBLE;

    /**
     * The week being edited, keyed 1 (Monday) to 7 (Sunday).
     *
     * @var array<int, array{is_rest_day: bool, first_in: string, first_out: string, second_in: string, second_out: string}>
     */
    public array $days = [];

    /** The type before the change, to tell whether the week still holds that type's defaults. */
    private string $previousType = User::SHIFT_FLEXIBLE;

    public function updatingType(): void
    {
        $this->previousType = $this->type;
    }

    /**
     * A week with no times, or still the untouched defaults of the previous type, starts again from
     * the new type's defaults (Monday to Saturday, Sunday as rest day). Times already edited are kept.
     */
    public function updatedType(UserShiftService $shifts): void
    {
        $this->type = isset(User::SHIFT_TYPES[$this->type]) ? $this->type : User::SHIFT_FLEXIBLE;
        $this->resetErrorBag();

        if ($this->type === User::SHIFT_FLEXIBLE) {
            return;
        }

        if ($this->hasNoTimes() || $this->days === $shifts->defaults($this->previousType)) {
            $this->days = $shifts->defaults($this->type);
        }
    }

    /**
     * "Copy Monday to all working days": rest days are left alone.
     */
    public function copyMonday(): void
    {
        $monday = $this->days[1] ?? null;

        if ($monday === null) {
            return;
        }

        foreach (range(2, 7) as $weekday) {
            if (! ($this->days[$weekday]['is_rest_day'] ?? false)) {
                $this->days[$weekday] = [...$monday, 'is_rest_day' => false];
            }
        }
    }

    #[Computed]
    public function summary(): string
    {
        return app(UserShiftService::class)->summary($this->days);
    }

    /**
     * @return array{weekdays: array<int, string>, columns: array<string, string>}
     */
    protected function shiftViewData(): array
    {
        return [
            'weekdays' => UserShiftService::WEEKDAYS,
            'columns' => $this->type === User::SHIFT_TWO
                ? ['first_in' => __('Morning In'), 'first_out' => __('Morning Out'), 'second_in' => __('Afternoon In'), 'second_out' => __('Afternoon Out')]
                : ['first_in' => __('In'), 'first_out' => __('Out')],
        ];
    }

    private function hasNoTimes(): bool
    {
        foreach ($this->days as $day) {
            foreach (['first_in', 'first_out', 'second_in', 'second_out'] as $key) {
                if ($day[$key] !== '') {
                    return false;
                }
            }
        }

        return true;
    }
}
