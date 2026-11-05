<?php

namespace App\Services;

use App\Imports\AttendanceSheetImport;
use App\Models\Attendance;
use App\Models\AttendanceImport;
use App\Models\Project;
use App\Models\User;
use App\Support\Costing\LaborCost;
use App\Support\Costing\ShiftMinutes;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel as ExcelReader;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use ZipArchive;

/**
 * Imports attendance from the Excel template when the system was down (T-12.1). Every valid row
 * becomes a Clocked Out attendance, costed like a clock-out: the rate in effect on its date and
 * only the minutes inside the user's shift. Rows with a problem are skipped and listed with their
 * spreadsheet row number and reason. Each row is saved in its own transaction that locks the
 * user's row, like clock-in.
 */
class AttendanceImportService
{
    public const HEADINGS = ['Employee No.', 'Employee Name', 'Project Code', 'Date', 'Clock In', 'Clock Out'];

    /** Rows whose Employee Name contains this are the template's examples and are skipped. */
    public const EXAMPLE_MARKER = '(example, delete this row)';

    public const MAX_ROWS = 1000;

    /** The slugged headings every row must have (Employee Name is only for reading). */
    private const REQUIRED_KEYS = ['employee_no', 'project_code', 'date', 'clock_in', 'clock_out'];

    private const READERS = ['xlsx' => ExcelReader::XLSX, 'xls' => ExcelReader::XLS, 'csv' => ExcelReader::CSV];

    public function __construct(private AttendanceService $attendance, private UserRateService $rates) {}

    /**
     * @param  string  $path  the uploaded file on disk
     * @param  string  $fileName  the name it had on the Admin's computer (its extension picks the reader)
     */
    public function import(string $path, string $fileName, User $admin): AttendanceImport
    {
        if (! $admin->can('attendance.correct')) {
            throw new AuthorizationException;
        }

        $rows = $this->read($path, $fileName);

        $findUser = $this->employeeFinder();
        $projects = Project::query()
            ->whereIn('code', $rows->pluck('project_code')->unique()->values())
            ->get()
            ->keyBy(fn (Project $project) => mb_strtoupper($project->code));

        $import = new AttendanceImport;
        $import->forceFill([
            'file_name' => mb_substr($fileName, 0, 255),
            'imported_by' => $admin->id,
            'rows_total' => $rows->count(),
            'rows_imported' => 0,
            'rows_failed' => 0,
            'failures' => [],
        ])->save();

        $failures = [];
        /** @var array<int, list<array{0: CarbonInterface, 1: CarbonInterface}>> $saved this file's saved times per user */
        $saved = [];

        // The counts are saved even when a row throws, so they always match the saved attendance.
        try {
            foreach ($rows as $row) {
                $reason = $this->importRow($row, $findUser, $projects, $import, $saved);

                if ($reason !== null) {
                    $failures[] = ['row' => $row['row'], 'employee_no' => $row['employee_no'], 'reason' => $reason];
                }
            }
        } finally {
            $imported = $import->attendances()->count();

            $import->forceFill([
                'rows_imported' => $imported,
                'rows_failed' => $rows->count() - $imported,
                'failures' => $failures,
            ])->save();
        }

        return $import;
    }

    /**
     * The file's attendance rows: blank and example rows left out, values trimmed, each with its
     * spreadsheet row number (the header is row 1).
     *
     * @return Collection<int, array{row: int, employee_no: string, project_code: string, date: mixed, clock_in: mixed, clock_out: mixed}>
     */
    private function read(string $path, string $fileName): Collection
    {
        $extension = mb_strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $reader = self::READERS[$extension] ?? null;

        try {
            // The "Attendance" sheet; the first sheet when there is none (a CSV, or the Admin's own file).
            $sheet = new AttendanceSheetImport('Attendance');
            Excel::import($sheet, $path, null, $reader);

            if (! $sheet->found) {
                $sheet = new AttendanceSheetImport(0);
                Excel::import($sheet, $path, null, $reader);
            }
        } catch (SpreadsheetException) {
            // A Mac's Numbers app keeps the .xlsx name when it saves in its own format.
            throw ValidationException::withMessages(['file' => self::isNumbersFile($path)
                ? __('This is a Numbers file, not an Excel file. In Numbers choose File → Export To → Excel, then upload the exported .xlsx file.')
                : __('This file could not be read. Upload the template saved as Excel (.xlsx) or .csv.')]);
        }

        $first = $sheet->rows->first();

        if ($first !== null && array_diff(self::REQUIRED_KEYS, array_keys($first->all())) !== []) {
            throw ValidationException::withMessages(['file' => __('This file does not have the template columns. Download the template and copy the rows into it.')]);
        }

        $rows = $sheet->rows
            ->map(function (Collection $row, int $index) {
                $value = fn (string $key) => is_string($row[$key] ?? null) ? trim($row[$key]) : ($row[$key] ?? null);

                return [
                    'row' => $index + 2,
                    'employee_no' => (string) $value('employee_no'),
                    'employee_name' => (string) $value('employee_name'),
                    'project_code' => (string) $value('project_code'),
                    'date' => $value('date'),
                    'clock_in' => $value('clock_in'),
                    'clock_out' => $value('clock_out'),
                ];
            })
            ->reject(fn (array $row) => collect(self::REQUIRED_KEYS)->every(fn (string $key) => $row[$key] === null || $row[$key] === ''))
            ->reject(fn (array $row) => str_contains($row['employee_name'], self::EXAMPLE_MARKER))
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['file' => __('The file has no attendance rows.')]);
        }

        if ($rows->count() > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => __('The file has more than :max rows. Split it and upload each part.', ['max' => number_format(self::MAX_ROWS)])]);
        }

        return $rows;
    }

    /**
     * Check one row and save it. Returns why it was not imported, or null when it was.
     *
     * @param  array{row: int, employee_no: string, project_code: string, date: mixed, clock_in: mixed, clock_out: mixed}  $row
     * @param  callable(string): ?User  $findUser
     * @param  Collection<string, Project>  $projects
     * @param  array<int, list<array{0: CarbonInterface, 1: CarbonInterface}>>  $saved
     */
    private function importRow(array $row, callable $findUser, Collection $projects, AttendanceImport $import, array &$saved): ?string
    {
        $user = $findUser($row['employee_no']);

        if ($user === null) {
            return __('Unknown employee no.');
        }

        $project = $projects->get(mb_strtoupper($row['project_code']));

        if ($project === null) {
            return __('Unknown project code');
        }

        if ($project->status !== Project::STATUS_ONGOING) {
            return __('Project is not Ongoing');
        }

        $date = $this->date($row['date']);

        if ($date === null) {
            return __('Invalid date');
        }

        $inMinutes = $this->minutes($row['clock_in']);

        if ($inMinutes === null) {
            return __('Invalid clock-in time');
        }

        $outMinutes = $this->minutes($row['clock_out']);

        if ($outMinutes === null) {
            return __('Invalid clock-out time');
        }

        if ($inMinutes === $outMinutes) {
            return __('Clock-out must be after clock-in');
        }

        // A clock-out earlier than the clock-in is the next day (PRD 7.4 rule 6).
        $in = $date->copy()->addMinutes($inMinutes);
        $out = $date->copy()->addMinutes($outMinutes);

        if ($out->lt($in)) {
            $out = $out->copy()->addDay();
        }

        if ($out->gt(now())) {
            return __('Times cannot be in the future');
        }

        foreach ($saved[$user->id] ?? [] as [$otherIn, $otherOut]) {
            if ($otherIn->lt($out) && $otherOut->gt($in)) {
                return $this->overlapReason($otherIn, $otherOut);
            }
        }

        return DB::transaction(function () use ($user, $project, $in, $out, $import, &$saved) {
            User::query()->lockForUpdate()->find($user->id);

            $overlap = Attendance::query()
                ->where('user_id', $user->id)
                ->where('status', '!=', Attendance::STATUS_VOID)
                ->where('clock_in_at', '<', $out)
                ->where(fn ($query) => $query->whereNull('clock_out_at')->orWhere('clock_out_at', '>', $in))
                ->orderBy('clock_in_at')
                ->first();

            if ($overlap !== null) {
                return $this->overlapReason($overlap->clock_in_at, $overlap->clock_out_at);
            }

            $hourlyRate = $this->rates->rateOn($user, $in);
            $windows = $this->attendance->shiftWindowsAt($user, $in);
            $minutes = ShiftMinutes::counted($in, $out, $windows);
            $labor = LaborCost::forMinutes($minutes, $hourlyRate);

            $attendance = new Attendance;
            $attendance->forceFill([
                'user_id' => $user->id,
                'project_id' => $project->id,
                'work_date' => $in->toDateString(),
                'clock_in_at' => $in,
                'clock_out_at' => $out,
                'hourly_rate' => $hourlyRate,
                'shift_windows' => $windows,
                'clocked_minutes' => (int) $in->diffInMinutes($out),
                'minutes_worked' => $minutes,
                'hours_worked' => $labor['hours'],
                'labor_cost' => $labor['cost'],
                'status' => Attendance::STATUS_CLOSED,
                'attendance_import_id' => $import->id,
            ])->save();

            $saved[$user->id][] = [$in, $out];

            return null;
        });
    }

    /**
     * Finds an employee who clocks in by employee no.: the exact value first (ignoring case), then,
     * for a number, the same number with leading zeros (Excel and Numbers turn "001" typed into a
     * General cell into 1). Archived users are never found; inactive ones are.
     *
     * @return callable(string): ?User
     */
    private function employeeFinder(): callable
    {
        $users = User::query()->permission('attendance.clock')->get();
        $exact = $users->keyBy(fn (User $user) => mb_strtoupper($user->employee_no));
        $byNumber = $users
            ->filter(fn (User $user) => ctype_digit($user->employee_no))
            ->keyBy(fn (User $user) => ltrim($user->employee_no, '0') ?: '0');

        return function (string $employeeNo) use ($exact, $byNumber): ?User {
            return $exact->get(mb_strtoupper($employeeNo))
                ?? (ctype_digit($employeeNo) ? $byNumber->get(ltrim($employeeNo, '0') ?: '0') : null);
        };
    }

    /**
     * A date typed as 2026-10-21, or an Excel date cell (a day number). Midnight in Asia/Manila.
     */
    private function date(mixed $value): ?CarbonInterface
    {
        if (is_int($value) || is_float($value)) {
            return Carbon::createFromFormat('!Y-m-d', ExcelDate::excelToDateTimeObject($value)->format('Y-m-d'));
        }

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date !== null && $date->format('Y-m-d') === $value ? $date : null;
    }

    /**
     * Minutes after midnight for a time typed as 7:00 AM, 07:00 or 19:30 (seconds ignored), or an
     * Excel time cell (a fraction of a day).
     */
    private function minutes(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            $minutes = (int) round(($value - floor($value)) * 1440);

            return $minutes < 1440 ? $minutes : null;
        }

        if (! is_string($value) || ! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?\s*([AP]\.?M\.?)?$/i', $value, $parts)) {
            return null;
        }

        [$hour, $minute] = [(int) $parts[1], (int) $parts[2]];
        $meridiem = strtoupper(str_replace('.', '', $parts[3] ?? ''));

        if ($minute > 59) {
            return null;
        }

        if ($meridiem === '') {
            return $hour <= 23 ? $hour * 60 + $minute : null;
        }

        if ($hour < 1 || $hour > 12) {
            return null;
        }

        return ($hour % 12 + ($meridiem === 'PM' ? 12 : 0)) * 60 + $minute;
    }

    /**
     * A Numbers document is a zip archive with Index/Document.iwa inside.
     */
    private static function isNumbersFile(string $path): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return false;
        }

        $isNumbers = $zip->locateName('Index/Document.iwa') !== false;
        $zip->close();

        return $isNumbers;
    }

    /**
     * Why a row overlaps. An attendance with no clock-out (still Clocked In, or flagged) has no
     * end, so it blocks every later row until its clock-out is set.
     */
    private function overlapReason(CarbonInterface $in, ?CarbonInterface $out): string
    {
        if ($out === null) {
            return __('This employee has an attendance from :date :in with no clock-out. Set its clock-out (Correct) first, then upload this row again.', [
                'date' => $in->format('M j, Y'),
                'in' => $in->format('g:i A'),
            ]);
        }

        return __('Overlaps another attendance of this employee (:in–:out)', [
            'in' => $in->format('g:i A'),
            'out' => $out->format('g:i A'),
        ]);
    }
}
