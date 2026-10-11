# T-18 – Reports and exports


**Goal:** Admin and Staff download attendance and project expense reports in Excel and PDF, with totals matching the screens exactly.

**Depends on:** T-16

**Build**

- `/reports` (permission `reports.export`) with two reports:
  1. **Attendance report** — filters: date range (required), user, project. Rows: date, employee no., name, project, in, out, hours, rate, labor cost, status; subtotal per employee per day; grand totals.
  2. **Project expense report** — filters: project (required), date range. Contents: summary (labor, materials, running expenses, budget used), labor by worker, materials list with markup.
- Excel: `App\Exports\AttendanceReportExport` and `ProjectExpenseReportExport` (maatwebsite/excel). Amounts are numeric cells with the `#,##0.00` format, not text.
- PDF: Blade views in `resources/views/reports/pdf` rendered with dompdf; A4 (attendance in landscape); header with company name, report title, filters used and generated date/time (Manila); page numbers.
- File names include the filters, e.g. `attendance_2026-10-01_to_2026-10-31.xlsx`, `project-ABC-01_expenses.pdf`.
- Report numbers come from the same `ProjectExpenseService` and attendance queries used on screen.
- Void sessions are excluded; open and flagged sessions are listed but not totaled.

**Acceptance criteria**

- [ ] Both reports download in both formats and open correctly in Excel and a PDF viewer.
- [ ] Totals in the files equal the totals on `/attendance` and `/projects/{project}` for the same filters.
- [ ] Workers get 403.

**Tests**

- Feature: `Excel::fake()`; requesting the attendance export asserts the download with the expected file name.
- Feature: the export collection has one row per counted session in the date range and none outside it.
- Feature: PDF endpoint responds with `application/pdf`.
- Feature: project report grand total equals `runningExpenses()` for the same project and range.
- Feature: worker gets 403 on `/reports` and on each export URL.

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
