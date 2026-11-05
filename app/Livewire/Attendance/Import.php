<?php

namespace App\Livewire\Attendance;

use App\Exports\AttendanceImportTemplateExport;
use App\Livewire\Concerns\ConfirmsPassword;
use App\Livewire\Concerns\WithPerPage;
use App\Models\AttendanceImport;
use App\Models\User;
use App\Services\AttendanceImportService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The "Import Attendance" tab of All Attendance (T-12.1), Admin only: download the template,
 * upload the filled-in file with the Admin's password, and see which rows were not imported.
 *
 * @property-read AttendanceImport|null $result
 * @property-read Paginator<int, array{row: int, employee_no: string, reason: string}>|null $failures
 * @property-read LengthAwarePaginator<int, AttendanceImport> $imports
 */
class Import extends Component
{
    use ConfirmsPassword, WithFileUploads, WithPerPage;

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    /** The Admin's own password, asked on every import and never kept after it. */
    public string $password = '';

    /** The import whose result (and rows not imported) is shown. */
    public ?int $resultId = null;

    /**
     * Runs on every request (first render and every action), so the permission is re-checked each time.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('attendance.correct'), 403);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new AttendanceImportTemplateExport, AttendanceImportTemplateExport::FILE_NAME);
    }

    public function import(AttendanceImportService $imports): void
    {
        try {
            $this->validate([
                'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:2048'],
            ], [
                'file.required' => __('Choose the filled-in attendance file.'),
                'file.extensions' => __('Upload an Excel (.xlsx, .xls) or .csv file.'),
                'file.max' => __('The file must be 2 MB or smaller.'),
            ]);

            $this->confirmPassword();

            /** @var User $admin */
            $admin = Auth::user();
            $result = $imports->import($this->file->getRealPath(), $this->file->getClientOriginalName(), $admin);
        } finally {
            $this->reset('password');
        }

        $this->reset('file');
        $this->resultId = $result->id;
        $this->resetPage('failures');
        $this->resetPage('imports');
        unset($this->result, $this->failures, $this->imports);

        Flux::toast(
            variant: $result->rows_failed > 0 ? 'warning' : 'success',
            text: trans_choice(':count attendance record imported.|:count attendance records imported.', $result->rows_imported, ['count' => $result->rows_imported])
                .($result->rows_failed > 0 ? ' '.trans_choice(':count row was not imported.|:count rows were not imported.', $result->rows_failed, ['count' => $result->rows_failed]) : ''),
        );
    }

    /**
     * Show an earlier import's result and its rows not imported ("Recent imports" row menu).
     */
    public function showFailures(int $id): void
    {
        $this->resultId = AttendanceImport::query()->findOrFail($id)->id;
        $this->resetPage('failures');
        unset($this->result, $this->failures);
    }

    #[Computed]
    public function result(): ?AttendanceImport
    {
        return $this->resultId === null ? null : AttendanceImport::query()->with('importedBy')->find($this->resultId);
    }

    /**
     * The shown import's rows not imported, a page at a time.
     *
     * @return Paginator<int, array{row: int, employee_no: string, reason: string}>|null
     */
    #[Computed]
    public function failures(): ?Paginator
    {
        if ($this->result === null || $this->result->failures === []) {
            return null;
        }

        $rows = collect($this->result->failures);
        $page = $this->getPage('failures');

        return new Paginator(
            $rows->forPage($page, $this->perPage())->values(),
            $rows->count(),
            $this->perPage(),
            $page,
            ['pageName' => 'failures'],
        );
    }

    /**
     * @return LengthAwarePaginator<int, AttendanceImport>
     */
    #[Computed]
    public function imports(): LengthAwarePaginator
    {
        return AttendanceImport::query()
            ->with('importedBy')
            ->latest()
            ->orderByDesc('id')
            ->paginate($this->perPage(), pageName: 'imports');
    }

    public function render(): View
    {
        return view('livewire.attendance.import');
    }
}
