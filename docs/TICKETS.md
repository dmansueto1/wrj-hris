# WRJ HRIS – Phase 1 Build Tickets

Oct 10, 2026 · @Dems Mansueto

## How to Use These Tickets

Work through the tickets in order, one ticket per Git branch. Each ticket can be pasted into an AI coding assistant in VS Code (Copilot Chat, Claude Code, or similar) or followed by hand; it is done only when every acceptance criterion and test passes.

**Workflow per ticket**

1. Create a branch: `git checkout -b T-01-baseline-config`
2. Paste the ticket into your assistant using the prompt template below, or follow it yourself.
3. Review the code it writes, then run `php artisan test` and check the screen in the browser.
4. Commit with the ticket ID: `git commit -m "T-01: Baseline config and test setup"`
5. Merge into `main` and push.

**Prompt template for the AI assistant**

```text
You are working on a Laravel + MySQL project (Livewire starter kit, Flux UI, Pest).
Read the project conventions below, then implement this ticket exactly.
Do not build anything outside the ticket's scope.
Write the listed tests first, then the code, and run `php artisan test` until all pass.
When finished, list every file you created or changed.

[PASTE THE CONVENTIONS SECTION]
[PASTE ONE TICKET]
```

**Project conventions (apply to every ticket)**

- Laravel with the Livewire starter kit; build screens with the Livewire and Flux UI patterns already used in the starter kit.
- Business logic lives in `app/Services` or `app/Support`, never in Livewire components or controllers.
- Money and hours are stored as `DECIMAL(…, 2)`. Calculations use whole centavos (integers) with half-up rounding; never use floats for money.
- Timezone `Asia/Manila`. Currency shown as `₱ 1,234.56`.
- Records are soft-deleted (archived), never hard-deleted.
- Roles: `admin`, `staff`, `worker` (Spatie permission). Every route and Livewire action checks permissions.
- Tests use Pest. Unit tests go in `tests/Unit`, feature tests in `tests/Feature`. Use model factories for test data.
- Out of scope for Phase 1: two-factor authentication, passkeys, registration, email verification, native mobile apps, offline mode, payroll.

**Definition of done (every ticket)**

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
- [ ] Committed with the ticket ID and merged to `main`

**Ticket index**

| ID | Ticket | Epic | Depends on |
| --- | --- | --- | --- |
| T-01 | Baseline config and test setup | Foundation | — |
| T-02 | Roles and permissions | Foundation | T-01 |
| T-03 | Users schema, rate history, admin seeder | Foundation | T-02 |
| T-04 | Login and security hardening | Foundation | T-03 |
| T-05 | App layout and role-based navigation | Foundation | T-04 |
| T-06 | Users masterfile screens | Masterfiles | T-05 |
| T-07 | Projects masterfile | Masterfiles | T-05 |
| T-08 | Materials masterfile | Masterfiles | T-05 |
| T-09 | Attendance table and costing service | Attendance | T-06, T-07 |
| T-10 | Clock in/out screen | Attendance | T-09 |
| T-11 | Attendance history views | Attendance | T-10 |
| T-12 | Attendance corrections | Attendance | T-11 |
| T-13 | Flag forgotten clock-outs | Attendance | T-09 |
| T-14 | Material cost and entries table | Materials & Expenses | T-07, T-08 |
| T-15 | Material entry screen | Materials & Expenses | T-14 |
| T-16 | Project running expenses page | Materials & Expenses | T-10, T-15 |
| T-17 | Audit log | Audit, Reports & Release | T-12, T-15 |
| T-18 | Reports and exports | Audit, Reports & Release | T-16 |
| T-19 | Demo data, QA and release readiness | Audit, Reports & Release | All |

## Epic 1 – Foundation

These five tickets give you a secure, role-aware app shell that every later feature plugs into.

### T-01 – Baseline config and test setup

**Goal:** the app runs on MySQL in Philippine time, has no public registration or welcome page, and has a working test suite on its own test database.

**Depends on:** nothing

**Build**

- `.env`: `APP_NAME="WRJ HRIS"`, `APP_URL=http://wrj-hris.test`, MySQL database `wrj_hris` with user `wrj_hris`.
- `config/app.php`: `'timezone' => 'Asia/Manila'`.
- Create a MySQL test database `wrj_hris_test` and grant the `wrj_hris` user access.
- `phpunit.xml`: set `DB_CONNECTION=mysql`, `DB_DATABASE=wrj_hris_test`.
- `tests/Pest.php`: apply `RefreshDatabase` to all Feature tests.
- Routes: `/` redirects guests to `/login` and signed-in users to `/clock`. Remove the default welcome page.
- Make sure no registration route, view or "Register" link exists.
- GitHub Actions: update the starter kit's test workflow to start a MySQL 8 service and run `php artisan test`.

**Acceptance criteria**

- [ ] `php artisan test` passes on a fresh clone.
- [ ] `/register` returns 404.
- [ ] Visiting `/` while logged out lands on the login page.
- [ ] Pushing to GitHub runs the tests automatically and they pass.

**Tests**

- Feature: `GET /register` returns 404.
- Feature: guest `GET /` redirects to `/login`.
- Unit: `config('app.timezone')` equals `Asia/Manila`.

**Verify:** `php artisan test` and `php artisan route:list | grep register` (should print nothing).

### T-02 – Roles and permissions

**Goal:** three roles exist with exactly the permissions in the PRD, and routes can be protected by permission.

**Depends on:** T-01

**Build**

- Add the `HasRoles` trait to `App\Models\User`; make sure the Spatie migration has run.
- Register the `role`, `permission` and `role_or_permission` middleware aliases in `bootstrap/app.php`.
- `database/seeders/RolesAndPermissionsSeeder.php` creating these permissions and assigning them:

| Permission | admin | staff | worker |
| --- | --- | --- | --- |
| `attendance.clock` | ✓ | ✓ | ✓ |
| `attendance.view-own` | ✓ | ✓ | ✓ |
| `attendance.view-all` | ✓ | ✓ |  |
| `attendance.correct` | ✓ |  |  |
| `users.manage` | ✓ |  |  |
| `projects.manage` | ✓ |  |  |
| `materials.manage` | ✓ | ✓ |  |
| `material-entries.create` | ✓ | ✓ |  |
| `expenses.view` | ✓ | ✓ |  |
| `reports.export` | ✓ | ✓ |  |

- Call the seeder from `DatabaseSeeder`. The seeder must be safe to run twice (`firstOrCreate`).

**Acceptance criteria**

- [ ] After `php artisan db:seed`, the three roles exist with exactly the permissions above.
- [ ] A route using `->middleware('permission:users.manage')` returns 403 for staff and worker.

**Tests**

- Feature (dataset): for each role, the set of permissions equals the table above — no more, no less.
- Feature: running the seeder twice does not create duplicates.

### T-03 – Users schema, rate history and admin seeder

**Goal:** users carry employee number, position, hourly rate and status, and every rate change is saved to a history.

**Depends on:** T-02

**Build**

- Migration on `users`: add `employee_no` (string 30, unique), `position` (nullable), `hourly_rate` (decimal 10,2, default 0), `status` (enum `active`/`inactive`, default `active`), `must_change_password` (boolean, default false), `deleted_at`; make `email` nullable.
- Migration `user_rate_histories`: `user_id` (FK), `hourly_rate` (decimal 10,2), `effective_from` (date), `changed_by` (nullable FK to users), timestamps.
- `User` model: `SoftDeletes`, casts (`hourly_rate` → `decimal:2`), `rateHistories()` relation, `scopeActive()`.
- `App\Services\UserRateService::changeRate(User $user, string $newRate, User $changedBy): void` — in one transaction, updates `hourly_rate` and adds a history row dated today. Does nothing if the rate is unchanged.
- Creating a user also writes the first history row.
- `UserFactory` states: `admin()`, `staff()`, `worker()`, `inactive()`.
- `AdminUserSeeder`: creates employee no. `ADM-0001` using `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env`, role `admin`.

**Acceptance criteria**

- [ ] `php artisan migrate:fresh --seed` creates the roles and one admin who can log in.
- [ ] Changing a rate updates the user and adds exactly one history row.
- [ ] A user can be saved without an email.

**Tests**

- Feature: `changeRate` from 80.00 to 90.00 updates the user and adds one history row with `changed_by` set.
- Feature: `changeRate` to the same rate adds no history row.
- Feature: duplicate `employee_no` is rejected by the database.
- Feature: soft-deleted user still exists with `withTrashed()`.

### T-04 – Login and security hardening

**Goal:** users log in with employee number or email under strong, enforced security rules.

**Depends on:** T-03

**Build**

- Change the starter kit's login (the Livewire login component, or Fortify's `authenticateUsing` if the kit uses Fortify) so one field labeled "Employee no. or email" accepts either: if the value contains `@`, match `email`; otherwise match `employee_no`.
- Reject inactive users with: "Your account is inactive. Please contact the administrator."
- Rate limit: after 5 failed attempts for the same identifier and IP, lock for 15 minutes and show the minutes remaining.
- `AppServiceProvider`: `Password::defaults()` = min 12, mixed case, numbers, symbols; add `uncompromised()` only in production.
- Middleware `EnsurePasswordChanged`: if `must_change_password` is true, redirect every page to the change-password screen until the password is changed; then set it to false.
- Middleware `AdminStaffIdleTimeout`: logs out admin and staff after 30 minutes without a request (store last activity in the session). Workers use the normal session lifetime.

**Acceptance criteria**

- [ ] Login works with `ADM-0001` and with the admin email.
- [ ] The 6th wrong attempt shows a lockout message.
- [ ] An inactive user cannot log in.
- [ ] A user created with `must_change_password` cannot open any page until they set a new password.

**Tests**

- Feature: login by employee number succeeds; login by email succeeds; wrong password fails.
- Feature: inactive user is rejected with the inactive message.
- Feature: 5 failures then a 6th attempt (even with the correct password) is locked.
- Feature: `must_change_password` user is redirected to the change-password page.
- Feature: a password like `password123` fails validation; `Str0ng!Passw0rd` passes.
- Feature: admin is logged out after 31 minutes idle (`$this->travel(31)->minutes()`); a worker is not.

### T-05 – App layout and role-based navigation

**Goal:** after login, each role sees only the menu items it is allowed to use, and every user lands on the clock screen.

**Depends on:** T-04

**Build**

- Use the starter kit's sidebar layout. Menu items, each shown only when the user has the permission:
  - Clock In/Out (`attendance.clock`) → `/clock`
  - My Attendance (`attendance.view-own`) → `/attendance/mine`
  - All Attendance (`attendance.view-all`) → `/attendance`
  - Projects (`expenses.view` or `projects.manage`) → `/projects`
  - Material Entries (`material-entries.create`) → `/material-entries`
  - Materials (`materials.manage`) → `/materials`
  - Users (`users.manage`) → `/users`
  - Reports (`reports.export`) → `/reports`
- Create each route now with a simple placeholder page protected by its permission; later tickets replace the placeholders.
- After login, redirect everyone to `/clock`. Remove the starter kit's dashboard link.
- Show the user's name, employee no. and role in the sidebar user menu.
- `App\Support\Money::format(string $amount): string` → `₱ 1,234.50`.

**Acceptance criteria**

- [ ] A worker sees only Clock In/Out and My Attendance.
- [ ] Staff do not see Users; admin sees everything.
- [ ] Typing a forbidden URL directly returns 403.

**Tests**

- Feature (dataset by role): menu shows and hides the right items.
- Feature: worker `GET /users` returns 403; admin returns 200.
- Feature: after login the user is redirected to `/clock`.
- Unit: `Money::format('1234.5')` returns `₱ 1,234.50`; `Money::format('0')` returns `₱ 0.00`.

## Epic 2 – Masterfiles

These three tickets give the Admin (and Staff, for materials) the screens to manage the people, sites and items everything else refers to.

### T-06 – Users masterfile screens

**Goal:** the Admin can create, edit, deactivate and reset passwords for users, and change hourly rates with a visible history.

**Depends on:** T-05

**Build**

- `/users` list (permission `users.manage`, behind `password.confirm`): columns employee no., name, role, position, hourly rate, status; search by name or employee no.; filter by role and status; 20 per page.
- Create form: employee no., name, email (optional), role, position, hourly rate, temporary password. New users get `must_change_password = true`.
- Edit form: same fields except password. A changed hourly rate goes through `UserRateService::changeRate`.
- Rate history table on the edit page: rate, effective from, changed by.
- Actions: Deactivate / Reactivate, Reset password (sets a new temporary password and `must_change_password = true`), Archive (soft delete).
- An Admin cannot deactivate, archive or demote their own account.
- Validation: employee no. required and unique (ignoring the current user); email unique if given; hourly rate between 0 and 99,999.99; password uses `Password::defaults()`.

**Acceptance criteria**

- [ ] Admin creates a worker; the worker logs in and is forced to change the password.
- [ ] Changing a rate from ₱80 to ₱90 shows both rates in the history.
- [ ] A deactivated user cannot log in; reactivating restores access.
- [ ] Staff and workers get 403 on `/users`.

**Tests**

- Feature (Livewire): admin creates a user with role worker; user has the role and `must_change_password = true`.
- Feature: duplicate employee no. shows a validation error.
- Feature: editing the rate creates one history row.
- Feature: admin cannot deactivate themselves.
- Feature: staff gets 403 on `/users`.

### T-07 – Projects masterfile

**Goal:** the Admin manages projects, and only Ongoing projects can be chosen at clock-in.

**Depends on:** T-05

**Build**

- Migration `projects`: `code` (string 30, unique), `name`, `location`, `client_name` (nullable), `start_date` and `end_date` (nullable dates), `budget` (decimal 14,2, nullable), `status` (enum `planning`, `ongoing`, `on_hold`, `completed`; default `planning`), `latitude`/`longitude` (nullable decimal 10,7), timestamps, soft deletes.
- `Project` model with `scopeOngoing()`, casts, factory with states for each status.
- `/projects` list: Admin (`projects.manage`) sees Create/Edit/Archive; Staff (`expenses.view`) sees the list read-only. Search by code or name; filter by status.
- Create/edit form with validation: code required, unique, saved in uppercase; end date on or after start date; budget 0 or more.
- Status badge colors: planning gray, ongoing green, on hold amber, completed blue.
- Each row links to `/projects/{project}` (placeholder until T-16).

**Acceptance criteria**

- [ ] Admin creates, edits and archives a project.
- [ ] Staff can view the list but see no edit buttons, and get 403 on the create URL.
- [ ] `Project::ongoing()` returns only Ongoing projects.

**Tests**

- Feature: admin creates a project; code `abc-01` is saved as `ABC-01`.
- Feature: end date before start date fails validation.
- Feature: staff gets 403 on create; worker gets 403 on `/projects`.
- Unit/Feature: `ongoing()` scope excludes planning, on hold and completed projects.

### T-08 – Materials masterfile

**Goal:** Admin and Staff maintain a list of materials with units and default prices for faster material entry.

**Depends on:** T-05

**Build**

- Migration `materials`: `code` (string 30, unique), `name`, `unit`, `default_price` (decimal 12,2), `status` (enum `active`/`inactive`), timestamps, soft deletes.
- Units offered in a dropdown: pcs, bags, cu.m, kg, m, L, sheets, sets.
- `Material` model with `scopeActive()` and a factory.
- `/materials` (permission `materials.manage`): list with search, create, edit, deactivate, archive.
- Validation: code required and unique; default price 0 or more.

**Acceptance criteria**

- [ ] Staff and Admin can create "Hollow blocks 4"" with unit pcs and default price ₱30.00.
- [ ] Inactive materials do not appear when recording material entries (checked in T-15).
- [ ] Workers get 403.

**Tests**

- Feature: staff creates a material; it appears in the list.
- Feature: duplicate code fails validation; negative price fails validation.
- Feature: worker gets 403 on `/materials`.

## Epic 3 – Attendance

This is the heart of Phase 1: multiple clock in/out sessions per day, each costed exactly and charged to the project it was worked on.

### T-09 – Attendance table and costing service

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

- Unit (dataset): `LaborCost::forMinutes` — 270 min @ 80.00 → 4.50 / 360.00; 260 @ 80.00 → 4.33 / 346.67; 1 @ 75.00 → 0.02 / 1.25; 0 @ 80.00 → 0.00 / 0.00; 480 @ 95.50 → 8.00 / 764.00.
- Feature: clock in at 7:00, clock out at 11:30 (`$this->travelTo(...)`) → 270 minutes, 4.50 hours, ₱360.00, status closed.
- Feature: clock in twice without clocking out throws `AlreadyClockedInException`.
- Feature: clock out with no open session throws `NotClockedInException`.
- Feature: clock in to a Completed project throws `ProjectNotOngoingException`.
- Feature: morning on Project A and afternoon on Project B → each project's `laborTotal()` is ₱360.00.
- Feature: rate changed to 90.00 while clocked in → the session still uses 80.00.
- Feature: overnight 22:00 to 06:00 next day → 480 minutes; `work_date` is the clock-in date.

### T-10 – Clock in/out screen

**Goal:** a worker on a phone can clock in to a project or clock out in under 30 seconds, and see today's sessions.

**Depends on:** T-09

**Build**

- Livewire page at `/clock` (permission `attendance.clock`), the home page for everyone.
- Not clocked in: a searchable dropdown of Ongoing projects (code + name) and a large "Clock In" button.
- Clocked in: the project name, clock-in time, a running elapsed time (Alpine, updates every minute) and a large "Clock Out" button with a confirm step.
- After clock-out: a success message with start time, end time and hours.
- "Today" panel: each of today's sessions (project, in, out, hours) and the total hours for the day.
- Location: request browser geolocation on clock in/out; if denied or unavailable, continue without it.
- Buttons are full width and at least 56 px tall on phones; disable them while saving (`wire:loading`).
- Show friendly messages for the service exceptions from T-09.

**Acceptance criteria**

- [ ] On a 375 px wide screen, clock in and clock out work without zooming or horizontal scrolling.
- [ ] Two sessions in one day show in the Today panel with the correct total.
- [ ] Projects that are not Ongoing are not listed.

**Tests**

- Feature (Livewire): not clocked in → sees Ongoing projects only and a Clock In button.
- Feature: selecting a project and clicking Clock In creates an open session.
- Feature: clocked in → sees Clock Out; clicking it closes the session.
- Feature: submitting a non-Ongoing project ID (tampered request) shows an error and creates nothing.
- Feature: two 270-minute sessions today → Today panel shows total 9.00 hours.

### T-11 – Attendance history views

**Goal:** workers see their own hours; Admin and Staff see everyone's sessions with labor cost.

**Depends on:** T-10

**Build**

- `/attendance/mine` (permission `attendance.view-own`): date range filter (default: current week, Monday to Sunday), sessions grouped by day with project, in, out, hours and status, plus daily and range total hours. Workers do not see peso amounts.
- `/attendance` (permission `attendance.view-all`): filters for date range, user, project and status; columns date, employee no., name, project, in, out, hours, rate, labor cost, status; a totals row for hours and labor cost; 50 per page.
- Status badges: open blue, closed green, adjusted purple, flagged red, void gray (struck through).

**Acceptance criteria**

- [ ] A worker only ever sees their own sessions.
- [ ] Totals in `/attendance` match the sum of the filtered rows, excluding void sessions.

**Tests**

- Feature: worker A does not see worker B's sessions on `/attendance/mine`.
- Feature: worker gets 403 on `/attendance`; staff gets 200.
- Feature: date filter excludes sessions outside the range.
- Feature: totals row equals the sum of the counted sessions shown.

### T-12 – Attendance corrections

**Goal:** the Admin can fix mistakes, and every fix recalculates labor cost and records who changed it and why.

**Depends on:** T-11

**Build**

- `App\Services\AttendanceCorrectionService` with:
  - `setClockOut(Attendance $a, Carbon $clockOut, string $reason, User $admin)`
  - `changeTimes(Attendance $a, Carbon $in, Carbon $out, string $reason, User $admin)`
  - `changeProject(Attendance $a, Project $project, string $reason, User $admin)`
  - `split(Attendance $a, Carbon $at, string $reason, User $admin)` — ends the session at `$at` and creates a second session from `$at` to the original clock-out with the same project and rate.
  - `void(Attendance $a, string $reason, User $admin)`
- Every change: recompute minutes, hours and labor cost with `LaborCost` using the session's saved `hourly_rate`; set `status = adjusted` (or `void`), `adjusted_by` and `adjustment_reason`.
- Validation: reason required (at least 10 characters); clock-out after clock-in; no time in the future; no overlap with the same user's other non-void sessions.
- UI: a "Correct" button on each `/attendance` row (permission `attendance.correct`, behind `password.confirm`) opening a modal with the five actions.

**Acceptance criteria**

- [ ] Moving a session from Project A to B moves its labor cost between the two project totals.
- [ ] A voided session no longer counts toward any total.
- [ ] Staff and workers cannot open the correction modal or call the actions.

**Tests**

- Feature: setting clock-out 11:30 on a flagged session that started at 7:00 → ₱360.00 at ₱80/hr, status adjusted.
- Feature: change project moves ₱360.00 from Project A's total to Project B's.
- Feature: split a 7:00–17:00 session at 12:00 → two sessions of 300 minutes each.
- Feature: missing reason fails validation; overlapping times fail validation.
- Feature: void excludes the session from `laborTotal()`.
- Feature: staff calling the Livewire action gets 403.

### T-13 – Flag forgotten clock-outs

**Goal:** sessions left open for more than 12 hours are flagged automatically so the Admin can correct them, and they do not block the worker.

**Depends on:** T-09 (the banner uses T-10; correction uses T-12)

**Build**

- Artisan command `attendance:flag-open`: sets `status = flagged` for `open` sessions whose `clock_in_at` is more than 12 hours ago.
- Schedule it hourly in `routes/console.php`.
- A flagged session does not count as open, so the worker can clock in again.
- `/clock` shows a banner when the user has a flagged session: "You have an unclosed session from {date}. Your admin will correct it."
- `/attendance` shows a "Needs correction (N)" link that filters to flagged sessions.
- Note for deployment: the server needs the cron entry `* * * * * php artisan schedule:run`.

**Acceptance criteria**

- [ ] A session open 13 hours is flagged after the command runs; one open 11 hours is not.
- [ ] The worker with a flagged session can clock in again.

**Tests**

- Feature: travel 13 hours, run the command → flagged; travel 11 hours → still open.
- Feature: running the command twice changes nothing the second time.
- Feature: a user with a flagged session can `clockIn` successfully.
- Feature: `/clock` shows the banner for a user with a flagged session.
- Feature: the scheduler lists `attendance:flag-open` as hourly.

## Epic 4 – Materials and Expenses

These tickets add material costs with the 20% markup and bring labor and materials together into each project's running expenses.

### T-14 – Material cost and entries table

**Goal:** a tested calculation for material cost with markup, and material entries that store their amounts and markup rate permanently.

**Depends on:** T-07, T-08

**Build**

- Migration `settings` (`key` string primary, `value` string) and `App\Support\Settings::get(string $key, ?string $default = null)`; seed `material_markup_rate = 0.20`.
- `App\Support\Costing\MaterialCost::compute(string $unitPrice, string $quantity, string $markupRate): array` returning `subtotal`, `markup`, `total` as 2-decimal strings.
  - Work in integers: price in centavos, quantity in hundredths, markup rate in ten-thousandths.
  - `subtotal = intdiv(price_centavos × qty_hundredths + 50, 100)`; `markup = intdiv(subtotal × rate_ten_thousandths + 5000, 10000)`; `total = subtotal + markup`.
- Migration `project_material_entries`: `project_id`, `material_id` (FKs), `entry_date` (date), `quantity` (decimal 12,2), `unit_price` (decimal 12,2), `markup_rate` (decimal 5,4), `subtotal`, `markup_amount`, `total_cost` (decimal 14,2), `remarks` (nullable), `entered_by` (FK users), timestamps, soft deletes. Index `(project_id, entry_date)`.
- `ProjectMaterialEntry` model: on `saving`, if `markup_rate` is empty copy it from the setting, then fill `subtotal`, `markup_amount` and `total_cost` from `MaterialCost`.
- `Project::materialsTotal(): string` and `Project::runningExpenses(): string` (labor total + materials total, added in centavos).

**Acceptance criteria**

- [ ] All PRD section 10.3 material cases pass.
- [ ] Changing the markup setting to 0.25 does not change existing entries.

**Tests**

- Unit (dataset): `MaterialCost::compute` — 30.00 × 100 @ 0.20 → 3,000.00 / 600.00 / 3,600.00; 12.50 × 3.5 → 43.75 / 8.75 / 52.50; 0.33 × 3 → 0.99 / 0.20 / 1.19.
- Feature: saving an entry fills subtotal, markup and total.
- Feature: entry saved at 0.20, setting changed to 0.25, entry still totals ₱3,600.00; a new entry uses 0.25.
- Feature: a project with ₱360.00 labor and ₱3,600.00 materials has `runningExpenses()` = ₱3,960.00.

### T-15 – Material entry screen

**Goal:** Staff record material purchases per project and see the 20% markup and total before saving.

**Depends on:** T-14

**Build**

- `/material-entries` (permission `material-entries.create`): list with filters for project, material and date range; columns date, project, material, quantity + unit, unit price, subtotal, markup, total, entered by; totals row; 50 per page.
- Create form: entry date (default today, no future dates), project (Ongoing and On hold only), material (active only; selecting it fills unit price with the default price, still editable), quantity (more than 0), unit price (0 or more), remarks.
- Live preview below the form, computed on the server with `MaterialCost`: subtotal, markup (20%), total.
- Editing and archiving: Admin can change any entry; Staff can change only their own entries made today.

**Acceptance criteria**

- [ ] Entering hollow blocks, 100 pcs at ₱30.00 previews ₱3,000.00 / ₱600.00 / ₱3,600.00, and saves the same.
- [ ] Inactive materials and Completed projects cannot be chosen.
- [ ] Workers get 403.

**Tests**

- Feature (Livewire): preview shows the three amounts for 100 × ₱30.00.
- Feature: saving creates an entry with `entered_by` = the current user.
- Feature: quantity 0 and a future date fail validation.
- Feature: inactive material ID submitted directly fails validation.
- Feature: staff cannot edit another user's entry or their own entry from yesterday; admin can.
- Feature: worker gets 403 on `/material-entries`.

### T-16 – Project running expenses page

**Goal:** for any project, Admin and Staff see labor, materials and running expenses, with breakdowns and budget usage.

**Depends on:** T-10, T-15

**Build**

- `App\Services\ProjectExpenseService` with aggregate queries (SUM and GROUP BY in SQL, never loading every row): totals, labor by worker, labor by date, materials by item, materials by date — each accepting an optional date range.
- `/projects/{project}` (permission `expenses.view`): summary cards for labor total, materials total and running expenses; if a budget is set, a progress bar showing percentage used (amber at 80% or more, red at 100% or more).
- Tabs: Labor by worker (name, hours, cost), Labor by date, Materials by item (quantity, unit, total), Materials by date.
- A date range filter applies to the cards and all tabs.
- Add a "Running expenses" column to the `/projects` list.
- Only `closed` and `adjusted` sessions count; open, flagged and void sessions are excluded.

**Acceptance criteria**

- [ ] A project with ₱360.00 labor and ₱3,600.00 materials shows running expenses ₱3,960.00.
- [ ] With a budget of ₱10,000.00, the bar shows 39.6% used.
- [ ] Workers get 403.

**Tests**

- Feature: running expenses equal labor + materials exactly.
- Feature: open, flagged and void sessions are not included in labor.
- Feature: budget percentage is 39.6 for ₱3,960.00 of ₱10,000.00.
- Feature: labor by worker shows the right hours and cost per worker.
- Feature: date filter changes the totals.
- Feature: worker gets 403 on `/projects/{project}`.

## Epic 5 – Audit, Reports and Release

The last three tickets make every sensitive change traceable, give the client exportable reports, and get the app ready for go-live.

### T-17 – Audit log

**Goal:** every attendance correction, rate change and material entry change records who did it, when, what changed and why.

**Depends on:** T-12, T-15

**Build**

- Run the spatie/laravel-activitylog migration.
- `AttendanceCorrectionService`: log each action with `activity()->performedOn($attendance)->causedBy($admin)` and properties `old`, `new` and `reason`; the description names the action (e.g. "Changed project").
- `UserRateService::changeRate`: log old rate, new rate and who changed it.
- `ProjectMaterialEntry`: use the `LogsActivity` trait for updates and archives (log only changed fields).
- `User`: log changes to role, status and hourly rate. Never log `password` or `remember_token`.
- A "History" panel (Admin only) on the attendance correction modal, the user edit page and the material entry edit form, listing date/time, user, action, old → new and reason.

**Acceptance criteria**

- [ ] After moving a session from Project A to B, its History shows the Admin's name, the time, A → B and the reason.
- [ ] Passwords never appear in the activity log table.

**Tests**

- Feature: change project creates one activity with the causer, old and new `project_id`, and the reason.
- Feature: rate change from 80.00 to 90.00 is logged with both values.
- Feature: updating a material entry quantity is logged; creating one entry by Staff is attributed to Staff.
- Feature: resetting a user's password writes no password data to `activity_log`.
- Feature: staff cannot see the History panel.

### T-18 – Reports and exports

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

### T-19 – Demo data, QA and release readiness

**Goal:** realistic demo data for the client walkthrough, a fully green test suite, and a checked production setup.

**Depends on:** all previous tickets

**Build**

- `DemoDataSeeder` (refuses to run when `APP_ENV=production`): 1 admin, 2 staff, 10 workers with rates from ₱70 to ₱120, 3 Ongoing projects and 1 Completed, 15 materials, 2 weeks of morning and afternoon sessions (some workers switching projects mid-day), material entries on each project, and one flagged session.
- Coverage: install PCOV (or use Xdebug) and run `php artisan test --coverage --min=70`; add the same check to GitHub Actions with `coverage: pcov`.
- Smoke test: as Admin, every main page returns 200.
- Production checklist in `README.md`: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `php artisan migrate --force`, `php artisan optimize`, `npm run build`, cron `* * * * * php artisan schedule:run`, daily database backup, default admin password changed.

**Acceptance criteria**

- [ ] `php artisan migrate:fresh --seed --seeder=DemoDataSeeder` builds a complete demo in one command.
- [ ] All tests pass and coverage meets PRD section 10.1 (at least 90% for `app/Support/Costing` and `app/Services`, 70% overall).
- [ ] Manual UAT passes: on a phone, a worker clocks in to Project A, out for lunch, in to Project B, out; Staff record hollow blocks 100 × ₱30; Admin corrects a flagged session; project pages and exports show the expected amounts.

**Tests**

- Feature: `DemoDataSeeder` runs on a fresh database without errors.
- Feature: `DemoDataSeeder` throws when the environment is production.
- Feature (dataset of routes): admin gets 200 on `/clock`, `/attendance`, `/attendance/mine`, `/projects`, `/materials`, `/material-entries`, `/users`, `/reports`.
