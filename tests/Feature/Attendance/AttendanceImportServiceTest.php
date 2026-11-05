<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceImport;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceImportService;
use App\Services\UserRateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Tests\TestCase;

class AttendanceImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private const HEADINGS = ['Employee No.', 'Employee Name', 'Project Code', 'Date', 'Clock In', 'Clock Out'];

    private AttendanceImportService $imports;

    private User $admin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-21 18:00:00');
        Storage::fake('imports');
        $this->imports = app(AttendanceImportService::class);
        $this->admin = User::factory()->admin()->create();
        $this->project = Project::factory()->ongoing()->create(['code' => 'PRJ-001']);
    }

    public function testAFlexibleWorkersMorningAndAfternoonAreImportedAndCosted(): void
    {
        $worker = $this->worker('EMP-00001', '80.00');

        $this->assertSame('0.00', $this->project->laborTotal());

        $import = $this->import([
            ['EMP-00001', 'Juan Cruz', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM'],
            ['EMP-00001', 'Juan Cruz', 'PRJ-001', '2026-10-19', '12:30 PM', '5:00 PM'],
        ]);

        $this->assertSame(2, $import->rows_total);
        $this->assertSame(2, $import->rows_imported);
        $this->assertSame(0, $import->rows_failed);
        $this->assertSame([], $import->failures);
        $this->assertSame('attendance.xlsx', $import->file_name);
        $this->assertSame($this->admin->id, $import->imported_by);

        $records = Attendance::query()->orderBy('clock_in_at')->get();
        $this->assertCount(2, $records);
        $this->assertSame(['2026-10-19 07:00', '2026-10-19 12:30'], $records->map(fn ($a) => $a->clock_in_at->format('Y-m-d H:i'))->all());
        $this->assertSame(['2026-10-19 11:30', '2026-10-19 17:00'], $records->map(fn ($a) => $a->clock_out_at->format('Y-m-d H:i'))->all());

        foreach ($records as $record) {
            $this->assertSame($worker->id, $record->user_id);
            $this->assertSame($this->project->id, $record->project_id);
            $this->assertSame('2026-10-19', $record->work_date->toDateString());
            $this->assertSame('80.00', $record->hourly_rate);
            $this->assertNull($record->shift_windows);
            $this->assertSame(270, $record->clocked_minutes);
            $this->assertSame(270, $record->minutes_worked);
            $this->assertSame('4.50', $record->hours_worked);
            $this->assertSame('360.00', $record->labor_cost);
            $this->assertSame(Attendance::STATUS_CLOSED, $record->status);
            $this->assertSame($import->id, $record->attendance_import_id);
            $this->assertNull($record->clock_in_photo_path);
            $this->assertNull($record->clock_in_lat);
        }

        $this->assertSame('720.00', $this->project->laborTotal());
    }

    public function testTheRateEffectiveOnTheDateIsUsed(): void
    {
        $worker = $this->worker('EMP-00001', '95.00');
        $worker->rateHistories()->delete();
        $worker->rateHistories()->create(['hourly_rate' => '80.00', 'effective_from' => '2026-10-01']);
        $worker->rateHistories()->create(['hourly_rate' => '95.00', 'effective_from' => '2026-10-20']);

        $this->import([
            ['EMP-00001', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM'],
            ['EMP-00001', '', 'PRJ-001', '2026-10-20', '7:00 AM', '11:30 AM'],
        ]);

        $records = Attendance::query()->orderBy('clock_in_at')->get();
        $this->assertSame(['80.00', '95.00'], $records->pluck('hourly_rate')->all());
        $this->assertSame(['360.00', '427.50'], $records->pluck('labor_cost')->all());
    }

    public function testAnOvernightRowEndsTheNextDay(): void
    {
        $this->worker('EMP-00001', '95.50');

        $this->import([['EMP-00001', '', 'PRJ-001', '2026-10-19', '22:00', '06:00']]);

        $record = Attendance::query()->sole();
        $this->assertSame('2026-10-19', $record->work_date->toDateString());
        $this->assertSame('2026-10-19 22:00', $record->clock_in_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-20 06:00', $record->clock_out_at->format('Y-m-d H:i'));
        $this->assertSame(480, $record->minutes_worked);
        $this->assertSame('8.00', $record->hours_worked);
        $this->assertSame('764.00', $record->labor_cost);
    }

    public function testOnlyTheMinutesInsideTheShiftCount(): void
    {
        User::factory()->worker()->twoShifts()->create(['employee_no' => 'EMP-00001', 'hourly_rate' => '80.00']);

        // Monday 2026-10-19: 8:00–12:00 and 13:00–17:00.
        $this->import([['EMP-00001', '', 'PRJ-001', '2026-10-19', '7:50 AM', '12:24 PM']]);

        $record = Attendance::query()->sole();
        $this->assertSame(274, $record->clocked_minutes);
        $this->assertSame(240, $record->minutes_worked);
        $this->assertSame('4.00', $record->hours_worked);
        $this->assertSame('320.00', $record->labor_cost);
        // assertEquals: the JSON column does not keep the key order.
        $this->assertEquals([
            ['start' => '2026-10-19 08:00', 'end' => '2026-10-19 12:00'],
            ['start' => '2026-10-19 13:00', 'end' => '2026-10-19 17:00'],
        ], $record->shift_windows);
    }

    public function testGoodRowsAreSavedAndBadRowsAreListedWithTheirRowNumberAndReason(): void
    {
        $juan = $this->worker('EMP-00001', '80.00');
        $this->worker('EMP-00002', '80.00');
        Attendance::factory()->for($juan)->for($this->project)->onDate('2026-10-20')->create();

        $import = $this->import([
            ['EMP-00001', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM'],   // row 2: good
            ['EMP-99999', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM'],   // row 3
            ['EMP-00001', '', 'PRJ-404', '2026-10-19', '7:00 AM', '11:30 AM'],   // row 4
            ['EMP-00001', '', 'PRJ-001', '2026-10-19', '1:00 PM', '1:00 PM'],    // row 5
            ['EMP-00001', '', 'PRJ-001', '2026-10-22', '7:00 AM', '11:00 AM'],   // row 6
            ['EMP-00001', '', 'PRJ-001', '2026-10-20', '8:00 AM', '9:00 AM'],    // row 7
            ['EMP-00001', '', 'PRJ-001', '2026-10-19', '12:30 PM', '5:00 PM'],   // row 8: good
            ['EMP-00001', '', 'PRJ-001', '2026-10-19', '4:00 PM', '6:00 PM'],    // row 9
            ['EMP-00002', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:00 AM'],   // row 10: good
        ]);

        $this->assertSame(9, $import->rows_total);
        $this->assertSame(3, $import->rows_imported);
        $this->assertSame(6, $import->rows_failed);
        $this->assertSame([
            ['row' => 3, 'employee_no' => 'EMP-99999', 'reason' => 'Unknown employee no.'],
            ['row' => 4, 'employee_no' => 'EMP-00001', 'reason' => 'Unknown project code'],
            ['row' => 5, 'employee_no' => 'EMP-00001', 'reason' => 'Clock-out must be after clock-in'],
            ['row' => 6, 'employee_no' => 'EMP-00001', 'reason' => 'Times cannot be in the future'],
            ['row' => 7, 'employee_no' => 'EMP-00001', 'reason' => 'Overlaps another attendance of this employee (7:00 AM–11:30 AM)'],
            ['row' => 9, 'employee_no' => 'EMP-00001', 'reason' => 'Overlaps another attendance of this employee (12:30 PM–5:00 PM)'],
        ], $import->failures);

        $this->assertSame(3, Attendance::query()->whereNotNull('attendance_import_id')->count());
        $this->assertSame(4, Attendance::query()->count());
    }

    public function testInvalidValuesAndProjectsThatAreNotOngoingAreListed(): void
    {
        $this->worker('EMP-00001', '80.00');
        Project::factory()->create(['code' => 'PRJ-002', 'status' => Project::STATUS_COMPLETED]);

        $import = $this->import([
            ['EMP-00001', '', 'PRJ-002', '2026-10-19', '7:00 AM', '11:30 AM'],
            ['EMP-00001', '', 'PRJ-001', '19/10/2026', '7:00 AM', '11:30 AM'],
            ['EMP-00001', '', 'PRJ-001', '2026-10-19', '25:00', '11:30 AM'],
            ['EMP-00001', '', 'PRJ-001', '2026-10-19', '7:00 AM', 'noon'],
        ]);

        $this->assertSame(
            ['Project is not Ongoing', 'Invalid date', 'Invalid clock-in time', 'Invalid clock-out time'],
            array_column($import->failures, 'reason'),
        );
        $this->assertSame(0, Attendance::query()->count());
    }

    public function testExcelDateAndTimeCellsAreRead(): void
    {
        $this->worker('EMP-00001', '80.00');
        $date = ExcelDate::PHPToExcel(new \DateTime('2026-10-19 00:00:00'));

        // 7:00 = 7/24 of a day, 11:30 = 11.5/24.
        $this->import([['EMP-00001', '', 'PRJ-001', $date, 7 / 24, 11.5 / 24]]);

        $record = Attendance::query()->sole();
        $this->assertSame('2026-10-19 07:00', $record->clock_in_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-19 11:30', $record->clock_out_at->format('Y-m-d H:i'));
        $this->assertSame('360.00', $record->labor_cost);
    }

    public function testInactiveEmployeesCanBeImportedButArchivedAndNonClockingUsersCannot(): void
    {
        $this->worker('EMP-00001', '80.00')->forceFill(['status' => User::STATUS_INACTIVE])->save();
        $this->worker('EMP-00002', '80.00')->delete();
        User::factory()->create(['employee_no' => 'EMP-00003']); // no role, so no attendance.clock

        $import = $this->import([
            ['EMP-00001', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM'],
            ['EMP-00002', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM'],
            ['EMP-00003', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM'],
        ]);

        $this->assertSame(1, $import->rows_imported);
        $this->assertSame(['Unknown employee no.', 'Unknown employee no.'], array_column($import->failures, 'reason'));
    }

    public function testExampleRowsAndBlankRowsAreSkippedAndNotCounted(): void
    {
        $this->worker('EMP-00001', '80.00');

        $import = $this->import([
            ['EMP-00001', 'Juan Cruz (example, delete this row)', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM'],
            ['', '', '', '', '', ''],
            ['EMP-00001', '', 'PRJ-001', '2026-10-19', '12:30 PM', '5:00 PM'],   // row 4
            ['', '', '', '', '', ''],
            ['EMP-00001', '', 'PRJ-404', '2026-10-19', '6:00 PM', '7:00 PM'],    // row 6
        ]);

        $this->assertSame(2, $import->rows_total);
        $this->assertSame(1, $import->rows_imported);
        $this->assertSame(6, $import->failures[0]['row']);
        $this->assertSame('2026-10-19 12:30', Attendance::query()->sole()->clock_in_at->format('Y-m-d H:i'));
    }

    public function testAFileWithMoreThanAThousandRowsIsRefused(): void
    {
        $this->worker('EMP-00001', '80.00');
        $rows = array_fill(0, 1001, ['EMP-00001', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM']);

        $this->assertFileError('The file has more than 1,000 rows. Split it and upload each part.', fn () => $this->import($rows));
        $this->assertSame(0, AttendanceImport::query()->count());
    }

    public function testAFileThatIsNotTheTemplateIsRefused(): void
    {
        $path = $this->store([['Name', 'Hours'], ['Juan', '8']]);

        $this->assertFileError(
            'This file does not have the template columns. Download the template and copy the rows into it.',
            fn () => $this->imports->import($path, 'other.xlsx', $this->admin),
        );
    }

    public function testANumbersFileNamedXlsxIsRefusedWithHowToExportIt(): void
    {
        // A Numbers document is a zip with Index/Document.iwa, not an Excel workbook.
        $path = Storage::disk('imports')->path('numbers.xlsx');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('Index/Document.iwa', 'numbers');
        $zip->close();

        $this->assertFileError(
            'This is a Numbers file, not an Excel file. In Numbers choose File → Export To → Excel, then upload the exported .xlsx file.',
            fn () => $this->imports->import($path, 'attendance-import-template.xlsx', $this->admin),
        );
        $this->assertSame(0, AttendanceImport::query()->count());
    }

    public function testAFileThatCannotBeReadIsRefused(): void
    {
        Storage::disk('imports')->put('broken.xlsx', 'not a spreadsheet');

        $this->assertFileError(
            'This file could not be read. Upload the template saved as Excel (.xlsx) or .csv.',
            fn () => $this->imports->import(Storage::disk('imports')->path('broken.xlsx'), 'broken.xlsx', $this->admin),
        );
    }

    public function testTheAttendanceSheetIsReadEvenWhenItIsNotTheFirstSheet(): void
    {
        $this->worker('EMP-00001', '80.00');

        // Numbers' "Export To → Excel" puts an "Export Summary" sheet first.
        $path = $this->storeSheets([
            'Export Summary' => [[null, null], [null, 'This document was exported from Numbers.']],
            'Attendance' => [self::HEADINGS, ['EMP-00001', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM']],
            'Employees' => [['Employee No.', 'Name', 'Shift']],
        ]);

        $import = $this->imports->import($path, 'attendance-import-template.xlsx', $this->admin);

        $this->assertSame(1, $import->rows_imported);
        $this->assertSame('360.00', Attendance::query()->sole()->labor_cost);
    }

    public function testANumericEmployeeNoMatchesItsLeadingZeros(): void
    {
        // "001" typed into a General cell becomes the number 1 in Excel and Numbers.
        $this->worker('001', '80.00');

        $import = $this->import([[1, '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM']]);

        $this->assertSame(1, $import->rows_imported);
        $this->assertSame([], $import->failures);
    }

    public function testAnExactEmployeeNoWinsOverOneWithLeadingZeros(): void
    {
        $this->worker('001', '80.00');
        $exact = $this->worker('1', '90.00');

        $this->import([['1', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM']]);

        $this->assertSame($exact->id, Attendance::query()->sole()->user_id);
    }

    public function testARowAfterAnAttendanceWithNoClockOutSaysToSetItsClockOutFirst(): void
    {
        $worker = $this->worker('EMP-00001', '80.00');
        Attendance::factory()->for($worker)->for($this->project)->onDate('2026-10-19')->flagged()->create();

        $import = $this->import([['EMP-00001', '', 'PRJ-001', '2026-10-20', '7:00 AM', '11:30 AM']]);

        $this->assertSame(
            'This employee has an attendance from Oct 19, 2026 7:00 AM with no clock-out. Set its clock-out (Correct) first, then upload this row again.',
            $import->failures[0]['reason'],
        );
    }

    public function testCountsAreSavedWhenARowFailsUnexpectedly(): void
    {
        $this->worker('EMP-00001', '80.00');
        $calls = 0;
        $this->mock(UserRateService::class, fn ($mock) => $mock->shouldReceive('rateOn')->andReturnUsing(function () use (&$calls) {
            if (++$calls > 1) {
                throw new RuntimeException('Database went away.');
            }

            return '80.00';
        }));

        try {
            app(AttendanceImportService::class)->import($this->store([
                self::HEADINGS,
                ['EMP-00001', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM'],
                ['EMP-00001', '', 'PRJ-001', '2026-10-19', '12:30 PM', '5:00 PM'],
            ]), 'attendance.xlsx', $this->admin);
            $this->fail('Expected the unexpected error to be thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('Database went away.', $e->getMessage());
        }

        $import = AttendanceImport::query()->sole();
        $this->assertSame(1, $import->rows_imported);
        $this->assertSame(1, $import->rows_failed);
        $this->assertSame(1, Attendance::query()->count());
    }

    public function testAFileWithoutRowsIsRefused(): void
    {
        $this->assertFileError('The file has no attendance rows.', fn () => $this->import([]));
    }

    public function testCsvFilesAreRead(): void
    {
        $this->worker('EMP-00001', '80.00');
        $path = $this->store([self::HEADINGS, ['EMP-00001', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM']], 'attendance.csv');

        $import = $this->imports->import($path, 'attendance.csv', $this->admin);

        $this->assertSame(1, $import->rows_imported);
    }

    public function testOnlyAUserWhoMayCorrectAttendanceCanImport(): void
    {
        $path = $this->store([self::HEADINGS]);

        $this->expectException(AuthorizationException::class);

        $this->imports->import($path, 'attendance.xlsx', User::factory()->staff()->create());
    }

    private function worker(string $employeeNo, string $rate): User
    {
        return User::factory()->worker()->create(['employee_no' => $employeeNo, 'hourly_rate' => $rate]);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function import(array $rows): AttendanceImport
    {
        return $this->imports->import($this->store([self::HEADINGS, ...$rows]), 'attendance.xlsx', $this->admin);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    /**
     * A workbook with several named sheets, in order.
     *
     * @param  array<string, list<list<mixed>>>  $sheets
     */
    private function storeSheets(array $sheets, string $name = 'attendance.xlsx'): string
    {
        $export = new class($sheets) implements Export, WithMultipleSheets
        {
            /** @param array<string, list<list<mixed>>> $sheets */
            public function __construct(private array $sheets) {}

            public function sheets(): array
            {
                return collect($this->sheets)->map(fn (array $rows, string $title) => new class($title, $rows) implements FromArray, WithTitle
                {
                    /** @param list<list<mixed>> $rows */
                    public function __construct(private string $title, private array $rows) {}

                    public function array(): array
                    {
                        return $this->rows;
                    }

                    public function title(): string
                    {
                        return $this->title;
                    }
                })->values()->all();
            }
        };

        Excel::store($export, $name, 'imports');

        return Storage::disk('imports')->path($name);
    }

    private function store(array $rows, string $name = 'attendance.xlsx'): string
    {
        Excel::store(new class($rows) implements FromArray
        {
            /** @param list<list<mixed>> $rows */
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }
        }, $name, 'imports');

        return Storage::disk('imports')->path($name);
    }

    private function assertFileError(string $message, callable $import): void
    {
        try {
            $import();
        } catch (ValidationException $e) {
            $this->assertSame([$message], $e->errors()['file'] ?? null, 'Errors: '.json_encode($e->errors()));

            return;
        }

        $this->fail('Expected a validation error on [file].');
    }
}
