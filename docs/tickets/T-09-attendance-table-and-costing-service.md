# T-09 – Attendance table and costing service


**Goal:** a tested service that opens and closes sessions safely and computes hours and labor cost to the exact centavo.

**Depends on:** T-06, T-07

**Build**

- `App\Support\Costing\LaborCost::forMinutes(int $minutes, string $hourlyRate): array` returning `['hours' => '4.50', 'cost' => '360.00']`.
  - Convert the rate to centavos; `cost_centavos = intdiv(minutes × rate_centavos + 30, 60)` (half-up).
  - `hours_hundredths = intdiv(minutes × 100 + 30, 60)`.
  - Cost is always computed from exact minutes, never from the rounded hours.
- Migration `attendances`: `user_id`, `project_id` (FKs), `work_date` (date), `clock_in_at`, `clock_out_at` (nullable datetime), `hourly_rate` (decimal 10,2), `minutes_worked` (nullable unsigned int), `hours_worked` (nullable decimal 6,2), `labor_cost` (nullable decimal 12,2), `status` (enum `open`, `closed`, `adjusted`, `flagged`, `void`; default `open`), `clock_in_lat`, `clock_in_lng`, `clock_out_lat`, `clock_out_lng` (nullable decimal 10,7), `adjusted_by` (nullable FK), `adjustment_reason` (nullable string), timestamps. Indexes: `(user_id, status)`, `(user_id, work_date)`, `(project_id, status)`.
- `Attendance` model: relations `user`, `project`, `adjustedBy`; scopes `open()` and `counted()` (status `closed` or `adjusted`).
- `App\Services\AttendanceService`:
  - `clockIn(User $user, Project $project, ?float $lat = null, ?float $lng = null): Attendance` — inside a transaction with `lockForUpdate`; rejects if the user already has an `open` session, if the project is not Ongoing, or if the user is inactive. Saves `work_date = today`, `clock_in_at = now()` (seconds set to 0), and `hourly_rate` copied from the user.
  - `clockOut(User $user, ?float $lat = null, ?float $lng = null): Attendance` — locks the open session, sets `clock_out_at = now()` (seconds 0), computes `minutes_worked`, then `hours_worked` and `labor_cost` via `LaborCost`, status `closed`.
  - Throw clear domain exceptions: `AlreadyClockedInException`, `NotClockedInException`, `ProjectNotOngoingException`.
- `Project::laborTotal(): string` = sum of `labor_cost` of `counted()` sessions.

**Acceptance criteria**

- [ ] All PRD section 10.2 labor cases pass.
- [ ] Two quick clock-in calls create only one session.
- [ ] Changing a user's rate while they are clocked in does not change the open session's rate.

**Tests**

- Unit (data provider): `LaborCost::forMinutes` — 270 min @ 80.00 → 4.50 / 360.00; 260 @ 80.00 → 4.33 / 346.67; 1 @ 75.00 → 0.02 / 1.25; 0 @ 80.00 → 0.00 / 0.00; 480 @ 95.50 → 8.00 / 764.00.
- Feature: clock in at 7:00, clock out at 11:30 (`$this->travelTo(...)`) → 270 minutes, 4.50 hours, ₱360.00, status closed.
- Feature: clock in twice without clocking out throws `AlreadyClockedInException`.
- Feature: clock out with no open session throws `NotClockedInException`.
- Feature: clock in to a Completed project throws `ProjectNotOngoingException`.
- Feature: morning on Project A and afternoon on Project B → each project's `laborTotal()` is ₱360.00.
- Feature: rate changed to 90.00 while clocked in → the session still uses 80.00.
- Feature: overnight 22:00 to 06:00 next day → 480 minutes; `work_date` is the clock-in date.

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
