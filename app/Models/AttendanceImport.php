<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One uploaded attendance file (T-12.1). Rows are written by AttendanceImportService, never mass-assigned.
 *
 * @property int $id
 * @property string $file_name
 * @property int $imported_by
 * @property int $rows_total
 * @property int $rows_imported
 * @property int $rows_failed
 * @property list<array{row: int, employee_no: string, reason: string}> $failures
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AttendanceImport extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rows_total' => 'integer',
            'rows_imported' => 'integer',
            'rows_failed' => 'integer',
            'failures' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by')->withTrashed();
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
