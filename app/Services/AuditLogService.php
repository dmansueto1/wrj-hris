<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectMaterialEntry;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * The "History" panels (T-17, Admin only): the activity log of one attendance (with every record
 * it was corrected from), one user or one material entry, newest first, as people read it:
 * when, who, what was done, each changed field "old → new", and the reason.
 *
 * Manual entries keep their changes in `properties` (`old`, `new`, `reason`); entries written by
 * the LogsActivity trait keep them in `attribute_changes` (`old`, `attributes`).
 */
class AuditLogService
{
    /** A History panel lists at most this many entries. */
    public const LIMIT = 50;

    /** Field => label, in the order the changes are listed. */
    public const FIELDS = [
        'project_id' => 'Project',
        'clock_in_at' => 'Clock In',
        'clock_out_at' => 'Clock Out',
        'minutes_worked' => 'Counted minutes',
        'hours_worked' => 'Hours',
        'labor_cost' => 'Labor cost',
        'status' => 'Status',
        'entry_date' => 'Date',
        'material_id' => 'Material',
        'quantity' => 'Quantity',
        'unit_price' => 'Unit price',
        'markup_rate' => 'Markup rate',
        'subtotal' => 'Subtotal',
        'markup_amount' => 'Markup',
        'total_cost' => 'Total',
        'remarks' => 'Remarks',
        'role' => 'Role',
        'hourly_rate' => 'Hourly rate',
        'type' => 'Shift',
        'days' => 'Weekly schedule',
    ];

    private const MONEY = ['labor_cost', 'unit_price', 'subtotal', 'markup_amount', 'total_cost', 'hourly_rate'];

    /**
     * Project and material names already looked up, so a long History asks the database once per id.
     *
     * @var array<string, array<int|string, string>>
     */
    private array $names = [];

    /**
     * The attendance and every record it was corrected from (corrections are logged on the record
     * they replaced).
     *
     * @return Collection<int, array{at: Carbon, by: string, action: string, changes: list<array{label: string, old: string, new: string}>, reason: string|null}>
     */
    public function forAttendance(Attendance $attendance): Collection
    {
        $ids = [$attendance->id];
        $fromId = $attendance->corrected_from_id;

        while ($fromId !== null && ! in_array($fromId, $ids, true)) {
            $ids[] = $fromId;
            $fromId = Attendance::withTrashed()->whereKey($fromId)->value('corrected_from_id');
        }

        return $this->entries(new Attendance, $ids);
    }

    /**
     * Rate, role, status, shift and password-reset entries of a user.
     *
     * @return Collection<int, array{at: Carbon, by: string, action: string, changes: list<array{label: string, old: string, new: string}>, reason: string|null}>
     */
    public function forUser(User $user): Collection
    {
        return $this->entries($user, [$user->id]);
    }

    /**
     * @return Collection<int, array{at: Carbon, by: string, action: string, changes: list<array{label: string, old: string, new: string}>, reason: string|null}>
     */
    public function forMaterialEntry(ProjectMaterialEntry $entry): Collection
    {
        return $this->entries($entry, [$entry->id]);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, array{at: Carbon, by: string, action: string, changes: list<array{label: string, old: string, new: string}>, reason: string|null}>
     */
    private function entries(Model $subject, array $ids): Collection
    {
        $activities = Activity::query()
            ->where('subject_type', $subject->getMorphClass())
            ->whereIn('subject_id', $ids)
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        // One query for the people, archived ones included, so old entries keep their name.
        $causers = User::withTrashed()
            ->whereIn('id', $activities->where('causer_type', (new User)->getMorphClass())->pluck('causer_id')->filter()->unique())
            ->get(['id', 'name'])
            ->keyBy('id');

        return $activities->map(fn (Activity $activity) => [
            'at' => $activity->created_at,
            'by' => $causers[$activity->causer_id]->name ?? __('System'),
            'action' => $activity->description,
            'changes' => $activity->event === 'deleted' ? [] : $this->changes($activity),
            'reason' => $activity->properties?->get('reason'),
        ])->values();
    }

    /**
     * @return list<array{label: string, old: string, new: string}>
     */
    private function changes(Activity $activity): array
    {
        $old = (array) ($activity->properties?->get('old') ?? $activity->attribute_changes?->get('old') ?? []);
        $new = (array) ($activity->properties?->get('new') ?? $activity->attribute_changes?->get('attributes') ?? []);

        $fields = collect(array_keys(self::FIELDS))
            ->filter(fn (string $field) => array_key_exists($field, $old) || array_key_exists($field, $new))
            ->merge(collect(array_keys($new + $old))->diff(array_keys(self::FIELDS)));

        return $fields
            // A new record lists only the fields it was given.
            ->reject(fn (string $field) => $old === [] && ($new[$field] ?? null) === null)
            ->map(fn (string $field) => [
                'label' => __(self::FIELDS[$field] ?? Str::headline($field)),
                'old' => $this->format($field, $old[$field] ?? null),
                'new' => $this->format($field, $new[$field] ?? null),
            ])
            ->values()
            ->all();
    }

    private function format(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_array($value)) {
            return __('updated');
        }

        try {
            return match (true) {
                $field === 'project_id' => $this->names['project'][$value] ??= ($project = Project::withTrashed()->find($value)) ? $project->code.' · '.$project->name : (string) $value,
                $field === 'material_id' => $this->names['material'][$value] ??= Material::withTrashed()->find($value)?->name ?? (string) $value,
                in_array($field, ['clock_in_at', 'clock_out_at'], true) => Carbon::parse($value)->format('M j, Y g:i A'),
                $field === 'entry_date' => Carbon::parse($value)->format('M j, Y'),
                in_array($field, self::MONEY, true) => Money::format((string) $value),
                $field === 'hours_worked' => $value.' h',
                $field === 'minutes_worked' => $value.' min',
                $field === 'markup_rate' => Settings::rateToPercent((string) $value).'%',
                $field === 'status' => __(Attendance::STATUSES[$value][0] ?? Str::headline((string) $value)),
                $field === 'role' => __(Str::headline((string) $value)),
                $field === 'type' => __(User::SHIFT_TYPES[$value] ?? (string) $value),
                default => (string) $value,
            };
        } catch (Throwable) {
            return (string) $value;
        }
    }
}
