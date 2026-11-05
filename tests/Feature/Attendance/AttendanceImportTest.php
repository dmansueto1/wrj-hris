<?php

namespace Tests\Feature\Attendance;

use App\Exports\AttendanceImportTemplateExport;
use App\Livewire\Attendance\Import;
use App\Livewire\Attendance\Index;
use App\Models\Attendance;
use App\Models\AttendanceImport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADINGS = ['Employee No.', 'Employee Name', 'Project Code', 'Date', 'Clock In', 'Clock Out'];

    /** The factory password of every user. */
    private const PASSWORD = 'password';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-21 18:00:00');
        Storage::fake('imports');
    }

    // ---- Tab and permissions ----------------------------------------------------------------

    public function testAdminSeesTheImportTab(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)
            ->assertSeeHtml('data-test="tab-import"')
            ->set('tab', 'import')
            ->assertSet('tab', 'import')
            ->assertSeeLivewire(Import::class)
            ->assertSee('Import Attendance');
    }

    public function testStaffDoNotSeeTheImportTabAndFallBackToDaily(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        Livewire::withQueryParams(['tab' => 'import'])->test(Index::class)
            ->assertSet('tab', 'daily')
            ->assertDontSeeHtml('data-test="tab-import"')
            ->assertDontSeeLivewire(Import::class)
            ->set('tab', 'import')
            ->assertSet('tab', 'daily');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notAdmins(): array
    {
        return ['staff' => ['staff'], 'worker' => ['worker']];
    }

    #[DataProvider('notAdmins')]
    public function testStaffAndWorkersAreForbidden(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create());

        Livewire::test(Import::class)->assertForbidden();
    }

    public function testStaffCallingTheActionsGets403(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $download = Livewire::test(Import::class);
        $import = Livewire::test(Import::class)->set('password', self::PASSWORD);

        // The same components, now called by a Staff member.
        $admin->syncRoles('staff');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($admin->fresh());

        $download->call('downloadTemplate')->assertForbidden();
        $import->call('import')->assertForbidden();
        $this->assertSame(0, AttendanceImport::query()->count());
    }

    // ---- Template ---------------------------------------------------------------------------

    public function testAdminDownloadsTheTemplate(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Excel::fake();

        Livewire::test(Import::class)->call('downloadTemplate');

        Excel::assertDownloaded('attendance-import-template.xlsx', fn ($export) => $export instanceof AttendanceImportTemplateExport);
    }

    public function testTheTemplateHasTheFourSheetsWithTheirColumns(): void
    {
        User::factory()->worker()->create(['employee_no' => 'EMP-00042', 'first_name' => 'Juan', 'last_name' => 'Cruz']);
        User::factory()->worker()->create(['employee_no' => 'EMP-00043'])->forceFill(['status' => User::STATUS_INACTIVE])->save();
        Project::factory()->ongoing()->create(['code' => 'PRJ-001', 'name' => 'Riverside Townhomes']);
        Project::factory()->create(['code' => 'PRJ-002', 'status' => Project::STATUS_COMPLETED]);

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, Excel::raw(new AttendanceImportTemplateExport, ExcelWriter::XLSX));
        $book = IOFactory::load($path);

        $this->assertSame(['Attendance', 'Employees', 'Projects', 'Instructions'], $book->getSheetNames());

        $attendance = $book->getSheetByName('Attendance')->toArray();
        $this->assertSame(self::HEADINGS, $attendance[0]);
        $this->assertStringContainsString('(example, delete this row)', $attendance[1][1]);
        $this->assertStringContainsString('(example, delete this row)', $attendance[2][1]);
        // Every column is text, so Excel and Numbers keep "001" and "7:00 AM" as typed.
        foreach (['A2', 'C2', 'D2', 'E2', 'F2', 'A1001'] as $cell) {
            $this->assertSame('@', $book->getSheetByName('Attendance')->getStyle($cell)->getNumberFormat()->getFormatCode(), $cell);
        }

        $employees = $book->getSheetByName('Employees')->toArray();
        $this->assertSame(['Employee No.', 'Name', 'Shift'], $employees[0]);
        $this->assertSame(['EMP-00042'], array_values(array_filter(array_column(array_slice($employees, 1), 0))));
        $this->assertSame('Cruz, Juan', $employees[1][1]);

        $projects = $book->getSheetByName('Projects')->toArray();
        $this->assertSame(['Code', 'Name', 'Location'], $projects[0]);
        $this->assertSame(['PRJ-001'], array_values(array_filter(array_column(array_slice($projects, 1), 0))));

        $this->assertNotEmpty($book->getSheetByName('Instructions')->toArray());

        unlink($path);
    }

    // ---- Import -----------------------------------------------------------------------------

    public function testAdminImportsAFileWithTheirPassword(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $project = Project::factory()->ongoing()->create(['code' => 'PRJ-001']);
        User::factory()->worker()->create(['employee_no' => 'EMP-00001', 'hourly_rate' => '80.00']);

        Livewire::test(Import::class)
            ->set('file', $this->upload([
                ['EMP-00001', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM'],
                ['EMP-00001', '', 'PRJ-001', '2026-10-19', '12:30 PM', '5:00 PM'],
                ['EMP-99999', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM'],
            ]))
            ->set('password', self::PASSWORD)
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('password', '')
            ->assertSet('file', null)
            ->assertSet('resultId', AttendanceImport::query()->sole()->id)
            ->assertSee('Rows read')
            ->assertSeeHtml('data-test="failure-row-4"')
            ->assertSee('Unknown employee no.')
            ->assertSee('Fix these rows in the file, delete the rows that were imported, and upload it again.');

        $this->assertSame(2, Attendance::query()->count());
        $this->assertSame('720.00', $project->laborTotal());
        $this->assertSame('attendance.xlsx', AttendanceImport::query()->sole()->file_name);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badPasswords(): array
    {
        return ['missing' => [''], 'wrong' => ['not-my-password']];
    }

    #[DataProvider('badPasswords')]
    public function testAMissingOrWrongPasswordImportsNothing(string $password): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Project::factory()->ongoing()->create(['code' => 'PRJ-001']);
        User::factory()->worker()->create(['employee_no' => 'EMP-00001']);

        Livewire::test(Import::class)
            ->set('file', $this->upload([['EMP-00001', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM']]))
            ->set('password', $password)
            ->call('import')
            ->assertHasErrors(['password'])
            ->assertSet('password', '');

        $this->assertSame(0, Attendance::query()->count());
        $this->assertSame(0, AttendanceImport::query()->count());
    }

    public function testFiveWrongPasswordsLockImportingForAMinute(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Project::factory()->ongoing()->create(['code' => 'PRJ-001']);
        User::factory()->worker()->create(['employee_no' => 'EMP-00001']);
        $rows = [['EMP-00001', '', 'PRJ-001', '2026-10-19', '7:00 AM', '11:30 AM']];

        $component = Livewire::test(Import::class);

        foreach (range(1, 5) as $attempt) {
            $component->set('file', $this->upload($rows))->set('password', 'wrong-'.$attempt)->call('import')->assertHasErrors(['password']);
        }

        $component->set('file', $this->upload($rows))->set('password', self::PASSWORD)->call('import')->assertHasErrors(['password']);
        $this->assertSame(0, Attendance::query()->count());

        $this->travel(61)->seconds();

        $component->set('file', $this->upload($rows))->set('password', self::PASSWORD)->call('import')->assertHasNoErrors();
        $this->assertSame(1, Attendance::query()->count());
    }

    public function testAFileIsRequiredAndMustBeASpreadsheet(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Import::class)
            ->set('password', self::PASSWORD)
            ->call('import')
            ->assertHasErrors(['file' => 'required']);

        Livewire::test(Import::class)
            ->set('file', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
            ->set('password', self::PASSWORD)
            ->call('import')
            ->assertHasErrors(['file']);
    }

    public function testRecentImportsListsEachImportAndShowsItsRowsNotImported(): void
    {
        $admin = User::factory()->admin()->create(['first_name' => 'Ana', 'last_name' => 'Reyes']);
        $this->actingAs($admin);
        $old = AttendanceImport::query()->forceCreate([
            'file_name' => 'site-a-oct-19.xlsx',
            'imported_by' => $admin->id,
            'rows_total' => 5,
            'rows_imported' => 4,
            'rows_failed' => 1,
            'failures' => [['row' => 6, 'employee_no' => 'EMP-77777', 'reason' => 'Unknown employee no.']],
        ]);

        Livewire::test(Import::class)
            ->assertSee('site-a-oct-19.xlsx')
            ->assertSee('Ana Reyes')
            ->assertSeeHtml('data-test="import-row-'.$old->id.'"')
            ->assertDontSee('EMP-77777')
            ->call('showFailures', $old->id)
            ->assertSet('resultId', $old->id)
            ->assertSee('EMP-77777')
            ->assertSeeHtml('data-test="failure-row-6"');
    }

    public function testImportedAttendanceShowsTheImportedBadge(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $import = AttendanceImport::query()->forceCreate([
            'file_name' => 'a.xlsx', 'imported_by' => $admin->id, 'rows_total' => 1, 'rows_imported' => 1, 'rows_failed' => 0, 'failures' => [],
        ]);
        Attendance::factory()->onDate('2026-10-21')->create(['attendance_import_id' => $import->id]);

        Livewire::test(Index::class)->assertSeeHtml('data-test="imported"');
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function upload(array $rows): UploadedFile
    {
        Excel::store(new class([self::HEADINGS, ...$rows]) implements FromArray
        {
            /** @param list<list<mixed>> $rows */
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }
        }, 'upload.xlsx', 'imports');

        return UploadedFile::fake()->createWithContent('attendance.xlsx', Storage::disk('imports')->get('upload.xlsx'));
    }
}
