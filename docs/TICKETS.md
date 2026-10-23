# WRJ HRIS – Phase 1 Build Tickets

Oct 10, 2026 · @Dems Mansueto

## How to Use These Tickets

Work through the tickets in order, one ticket per Git branch. Each ticket can be pasted into an AI coding assistant in VS Code (Copilot Chat, Claude Code, or similar) or followed by hand; it is done only when every acceptance criterion and test passes.

**Workflow per ticket**

1. Create the branch from the latest `develop`: `git checkout develop && git pull && git checkout -b T-01-baseline-config`
2. Paste the ticket into your assistant using the prompt template below, or follow it yourself.
3. Review the code it writes, then run `php artisan test` and check the screen in the browser.
4. Run `/review` in a new conversation and fix findings until the result is CLEAN or NITS ONLY.

Committing, pushing and merging are done manually by the developer and are not part of any ticket.

**Prompt template for the AI assistant**

```text
You are working on a Laravel + MySQL project (Livewire starter kit, Flux UI, PHPUnit test classes).
Read the project conventions below, then implement this ticket exactly.
Do not build anything outside the ticket's scope.
Write the listed tests first, then the code, and run `php artisan test` until all pass.
When finished, list every file you created or changed.

[PASTE THE CONVENTIONS SECTION]
[PASTE ONE TICKET]
```

**Project conventions (apply to every ticket)**

- Laravel with the Livewire starter kit; build screens with the Livewire and Flux UI patterns already used in the starter kit.
- Livewire components are class-based, never single-file (`⚡*.blade.php`): the class (properties, attributes such as `#[Title]`/`#[Layout]`, actions, `render()`) goes in `app/Livewire/<Area>/<Name>.php`, and its view in `resources/views/livewire/<area>/<name>.blade.php` holds only the markup and fields. Route with `Route::livewire('url', Name::class)`, embed with `<livewire:area.name />`, test with `Livewire::test(Name::class)`.
- Every table is paginated with a "Rows per page" choice of 10 / 25 / 50 / 100, default 10 (`App\Support\Pagination`). In the Livewire component `use App\Livewire\Concerns\WithPerPage;` and `->paginate($this->perPage())`; under the table render `<x-table-footer :paginator="…" />` (rows-per-page select + page links). Do not pass `:paginate` to `<flux:table>`.
- Page design (all authenticated pages; the sign-in pages and the kiosk share the navy `bg-hero-blueprint` band, live greeting/clock and floating card): start with `<x-page-header>` (title, one-line description, actions on the right; the one key action uses `class="btn-accent"`); list pages add a row of `<x-stat-card>`s, a filter toolbar card with `<x-filter-chip>`s and a "Clear filters" link, and the list inside `<x-data-card>` with `<x-empty-state>` when empty; people show `<x-avatar>`; statuses and roles use `<x-status-badge>`; tables get stacked cards on phones (`md:hidden`); per-row actions go in a `flux:dropdown` menu. Menu items carry a `group` (Time / Operations / Admin) in `App\Support\Navigation`.
- Wording on screen: never show "session" or "duty". One clock-in-to-clock-out period is an "attendance" (an attendance record); its states are "Clocked In" (not yet out) and "Clocked Out". Actions stay "Clock In" / "Clock Out". Code and database names (`attendances`, `$session`, `$duty`, `KioskDuties`, `recentDuties`, PRD wording) stay as they are.
- Business logic lives in `app/Services` or `app/Support`, never in Livewire components or controllers.
- Money and hours are stored as `DECIMAL(…, 2)`. Calculations use whole centavos (integers) with half-up rounding; never use floats for money.
- Timezone `Asia/Manila`. Currency shown as `₱ 1,234.56`.
- Records are soft-deleted (archived), never hard-deleted.
- Roles: `admin`, `staff`, `worker` (Spatie permission). Every route and Livewire action checks permissions.
- Tests are PHPUnit test classes (`class XxxTest extends Tests\TestCase`) with camelCase methods such as `public function testFieldMustReturnNullOrEmpty(): void`, run by `php artisan test` (Pest is only the runner; do not write Pest-style `test()` / `it()` / `expect()` tests). Feature tests `use RefreshDatabase;`. Use `#[DataProvider]` for repeated cases. Unit tests go in `tests/Unit`, feature tests in `tests/Feature`. Use model factories for test data.
- Out of scope for Phase 1: two-factor authentication, passkeys, registration, email verification, native mobile apps, offline mode, payroll.

**Definition of done (every ticket)**

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`

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
| T-10.1 | Kiosk clock in/out | Additional | T-10, T-09, T-04 |
| T-10.2 | Photo capture on clock in/out | Additional | T-10.1, T-10 |
| T-11 | Attendance history views | Attendance | T-10, T-10.2 |
| T-12 | Attendance corrections | Attendance | T-11 |
| T-13 | Flag forgotten clock-outs | Attendance | T-09, T-10, T-10.1 |
| T-14 | Material cost and entries table | Materials & Expenses | T-07, T-08 |
| T-15 | Material entry screen | Materials & Expenses | T-14 |
| T-16 | Project running expenses page | Materials & Expenses | T-10, T-15 |
| T-17 | Audit log | Audit, Reports & Release | T-12, T-15 |
| T-18 | Reports and exports | Audit, Reports & Release | T-16 |
| T-19 | Demo data, QA and release readiness | Audit, Reports & Release | All |
| T-20 | Roles and permissions screen | Additional | T-02, T-05, T-06 |

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
- Feature test classes `use RefreshDatabase;`.
- Routes: `/` redirects guests to `/login` and signed-in users to `/clock`. Remove the default welcome page.
- Make sure no registration route, view or "Register" link exists.

**Acceptance criteria**

- [ ] `php artisan test` passes on a fresh clone.
- [ ] `/register` returns 404.
- [ ] Visiting `/` while logged out lands on the login page.

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

- Feature (data provider): for each role, the set of permissions equals the table above — no more, no less.
- Feature: running the seeder twice does not create duplicates.

### T-03 – Users schema, rate history and admin seeder

**Goal:** users carry employee number, position, hourly rate and status, and every rate change is saved to a history.

**Depends on:** T-02

**Build**

- Migration on `users`: add `employee_no` (string 30, unique), `first_name` and `last_name` (string 100), `username` (string 50, unique), `position` (nullable), `hourly_rate` (decimal 10,2, default 0), `status` (enum `active`/`inactive`, default `active`), `must_change_password` (boolean, default false), `deleted_at`; make `email` nullable.
- Migration `user_rate_histories`: `user_id` (FK), `hourly_rate` (decimal 10,2), `effective_from` (date), `changed_by` (nullable FK to users), timestamps.
- `User` model: `SoftDeletes`, casts (`hourly_rate` → `decimal:2`), `rateHistories()` relation, `scopeActive()`.
- `App\Services\UserRateService::changeRate(User $user, string $newRate, User $changedBy): void` — in one transaction, updates `hourly_rate` and adds a history row dated today. Does nothing if the rate is unchanged.
- Creating a user also writes the first history row.
- `UserFactory` states: `admin()`, `staff()`, `worker()`, `inactive()`.
- `App\Services\UsernameService`: on create, generates the username from the first letter of the first name plus the last name, lowercase letters only (John Doe → `jdoe`, Juan dela Cruz → `jdelacruz`). If taken (archived users included), append 2, 3, … (`jdoe2`). `name` is kept filled as "First Last".
- `AdminUserSeeder`: creates employee no. `ADM-0001`, username `admin`, role `admin`, with a random strong password printed once in the console and `must_change_password = true`. Does nothing if `ADM-0001` already exists. No password is stored in code or `.env`.

**Acceptance criteria**

- [ ] `php artisan migrate:fresh --seed` creates the roles and one admin `ADM-0001` (username `admin`, role `admin`, `must_change_password` true). Logging in with it is covered by T-04.
- [ ] Changing a rate updates the user and adds exactly one history row.
- [ ] A user can be saved without an email.
- [ ] New users get a username like `jdoe`; a second John Doe gets `jdoe2`.

**Tests**

- Feature: `changeRate` from 80.00 to 90.00 updates the user and adds one history row with `changed_by` set.
- Feature: `changeRate` to the same rate adds no history row.
- Feature: duplicate `employee_no` is rejected by the database.
- Feature: soft-deleted user still exists with `withTrashed()`.
- Feature: John Doe gets `jdoe`; a second John Doe gets `jdoe2`.
- Feature: running `AdminUserSeeder` twice creates one admin and does not change its password.

### T-04 – Login and security hardening

**Goal:** users log in with their username under strong, enforced security rules.

**Depends on:** T-03

**Build**

- Change the starter kit's login (the Livewire login component, or Fortify's `authenticateUsing` if the kit uses Fortify) so one field labeled "Username" matches the `username` column only (not email or employee no.).
- Reject inactive users with: "Your account is inactive. Please contact the administrator."
- Rate limit: after 5 failed attempts for the same identifier and IP, lock for 15 minutes and show the minutes remaining.
- `AppServiceProvider`: `Password::defaults()` = min 12, mixed case, numbers, symbols; add `uncompromised()` only in production.
- Middleware `EnsurePasswordChanged`: if `must_change_password` is true, redirect every page to the change-password screen until the password is changed; then set it to false.
- Middleware `AdminStaffIdleTimeout`: logs out admin and staff after 30 minutes without a request (store last activity in the session). Workers use the normal session lifetime.

**Acceptance criteria**

- [ ] Login works with username `admin` and the password printed by `AdminUserSeeder`.
- [ ] The 6th wrong attempt shows a lockout message.
- [ ] An inactive user cannot log in.
- [ ] A user created with `must_change_password` cannot open any page until they set a new password.

**Tests**

- Feature: login by username succeeds; login by email or employee number fails; wrong password fails.
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

- Feature (data provider by role): menu shows and hides the right items.
- Feature: worker `GET /users` returns 403; admin returns 200.
- Feature: after login the user is redirected to `/clock`.
- Unit: `Money::format('1234.5')` returns `₱ 1,234.50`; `Money::format('0')` returns `₱ 0.00`.

## Epic 2 – Masterfiles

These three tickets give the Admin (and Staff, for materials) the screens to manage the people, sites and items everything else refers to.

### T-06 – Users masterfile screens

**Goal:** the Admin can create, edit, deactivate and reset passwords for users, and change hourly rates with a visible history.

**Depends on:** T-05

**Build**

- `/users` list (permission `users.manage`, behind `password.confirm`): columns employee no., name, username, role, position, hourly rate, status; search by name, username or employee no.; filter by role and status; paginated with rows per page (10 / 25 / 50 / 100, default 10).
- Create form: employee no., first name, last name, email (optional), role, position, hourly rate, temporary password. The username is generated (see T-03) and shown after saving. New users get `must_change_password = true`.
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

- Unit (data provider): `LaborCost::forMinutes` — 270 min @ 80.00 → 4.50 / 360.00; 260 @ 80.00 → 4.33 / 346.67; 1 @ 75.00 → 0.02 / 1.25; 0 @ 80.00 → 0.00 / 0.00; 480 @ 95.50 → 8.00 / 764.00.
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
- Not clocked in: a dropdown of Ongoing projects ("CODE · Name"; on phones the device's own picker scrolls, so many projects are fine) and a "Clock In" button.
- Clocked in: the project name, clock-in time, a running elapsed time (Alpine, updates every minute) and a large "Clock Out" button with a confirm step.
- After clock-out: a success message with start time, end time and hours.
- "Today" panel: each of today's sessions (project, in, out, hours) and the total hours for the day.
- Location: request browser geolocation on clock in/out; if denied or unavailable, continue without it.
- Buttons are full width and 48 px tall (same as the kiosk); disable them while saving (`wire:loading`).
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


**Goal:** workers see their own hours; Admin and Staff see everyone's attendance with labor cost and the clock-in/out photos.

**Depends on:** T-10, T-10.2 (photo columns and the `attendance.photo` route). Attendance recorded at the kiosk (T-10.1) is stored as ordinary `attendances` rows and needs nothing extra.

**Build**

- On screen, a clock-in-to-clock-out period is an "attendance" (one attendance record), never a "session" (CLAUDE.md wording rule); "Clock In" / "Clock Out" stay for the actions.
- `/attendance/mine` (permission `attendance.view-own`): date range filter (default: current week, Monday to Sunday), attendance grouped by day with project, in, out, worked time and status, plus a "Total" row with the range's total hours under the Worked time column. Workers do not see peso amounts or photos.
- `/attendance` (permission `attendance.view-all`): filters for date range, user, project and status; columns date, name, project, in, out, worked time, rate, labor cost, photos, status; a "Total" row with the labor cost over all filtered attendance records (not just the current page), and the total hours in the stat cards; paginated with rows per page (10 / 25 / 50 / 100, default 10).
- Photos column: small round thumbnails of the clock-in and clock-out photos loaded from `route('attendance.photo', [$attendance, 'in'|'out'])` (`loading="lazy"`); clicking a thumbnail opens it full size in a modal. An attendance record without a photo shows a muted "No photo" placeholder; a Clocked In row shows only the clock-in photo.
- Worked time: completed attendance shows its hours with the unit (`4.50 h`); a Clocked In record shows a live running time from its clock-in, to the second (`2h 05m 09s`).
- Status badges with on-screen labels: open "Clocked In" green, closed "Clocked Out" gray (the same colors as the clock screens and kiosk), adjusted "Adjusted" purple, flagged "Flagged" red, void "Void" gray (struck through).

**Acceptance criteria**

- [ ] A worker only ever sees their own attendance, and never sees photos or peso amounts.
- [ ] Totals in `/attendance` match the sum of the filtered rows, excluding void attendance records.
- [ ] Admin and Staff see the clock-in and clock-out photo thumbnails for each attendance record, and can open them full size.
- [ ] No screen in this ticket shows the word "session".

**Tests**

- Feature: worker A does not see worker B's attendance records on `/attendance/mine`.
- Feature: worker gets 403 on `/attendance`; staff gets 200; admin gets 200.
- Feature: date filter excludes attendance outside the range.
- Feature: totals row equals the sum of the counted attendance records shown, across all pages of the filtered result.
- Feature: an attendance record with `clock_in_photo_path` and `clock_out_photo_path` set shows both thumbnails linking to `attendance.photo` with `in` and `out`; an attendance record without photos shows "No photo".
- Feature: `/attendance/mine` contains no `attendance.photo` links.
- Feature: open and closed attendance records show "Clocked In" and "Clocked Out"; neither page shows the text "session" (`assertDontSeeText`).

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

**Goal:** attendance records left open (Clocked In) for more than 12 hours are flagged automatically so the Admin can correct them, and they do not block the worker.

**Depends on:** T-09, T-10, T-10.1 (the kiosk notice)

**Build**

- Artisan command `attendance:flag-open`: sets `status = flagged` for `open` attendance records whose `clock_in_at` is more than 12 hours ago.
- Schedule it hourly in `routes/console.php`.
- A flagged attendance record does not count as open, so the worker can clock in again.
- On screen, never say "session" (CLAUDE.md wording rule): a clock-in-to-clock-out period is an "attendance" (one attendance record).
- `/clock` shows an amber banner when the user has a flagged attendance record: "You have an attendance from {date} that was not clocked out. Your admin will correct it."
- The kiosk (`/kiosk`, T-10.1) shows the same notice in step 2, after the worker's username and password are verified.
- `/attendance` shows a "Needs correction (N)" link that filters to flagged attendance records.
- Note for deployment: the server needs the cron entry `* * * * * php artisan schedule:run`.

**Acceptance criteria**

- [ ] An attendance record open 13 hours is flagged after the command runs; one open 11 hours is not.
- [ ] The worker with a flagged attendance record can clock in again, on `/clock` and at the kiosk.

**Tests**

- Feature: travel 13 hours, run the command → flagged; travel 11 hours → still open.
- Feature: running the command twice changes nothing the second time.
- Feature: a user with a flagged attendance record can `clockIn` successfully.
- Feature: `/clock` shows "You have an attendance from {date} that was not clocked out. Your admin will correct it." for a user with a flagged attendance record, and no visible "session" text (`assertDontSeeText`).
- Feature: the kiosk shows the same notice in step 2 for a verified worker with a flagged attendance record.
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

- Unit (data provider): `MaterialCost::compute` — 30.00 × 100 @ 0.20 → 3,000.00 / 600.00 / 3,600.00; 12.50 × 3.5 → 43.75 / 8.75 / 52.50; 0.33 × 3 → 0.99 / 0.20 / 1.19.
- Feature: saving an entry fills subtotal, markup and total.
- Feature: entry saved at 0.20, setting changed to 0.25, entry still totals ₱3,600.00; a new entry uses 0.25.
- Feature: a project with ₱360.00 labor and ₱3,600.00 materials has `runningExpenses()` = ₱3,960.00.

### T-15 – Material entry screen

**Goal:** Staff record material purchases per project and see the 20% markup and total before saving.

**Depends on:** T-14

**Build**

- `/material-entries` (permission `material-entries.create`): list with filters for project, material and date range; columns date, project, material, quantity + unit, unit price, subtotal, markup, total, entered by; totals row; paginated with rows per page (10 / 25 / 50 / 100, default 10).
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
- Coverage: install PCOV (or use Xdebug) and run `php artisan test --coverage --min=70`.
- Smoke test: as Admin, every main page returns 200.
- Production checklist in `README.md`: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `php artisan migrate --force`, `php artisan optimize`, `npm run build`, cron `* * * * * php artisan schedule:run`, daily database backup, default admin password changed.

**Acceptance criteria**

- [ ] `php artisan migrate:fresh --seed --seeder=DemoDataSeeder` builds a complete demo in one command.
- [ ] All tests pass and coverage meets PRD section 10.1 (at least 90% for `app/Support/Costing` and `app/Services`, 70% overall).
- [ ] Manual UAT passes: on a phone, a worker clocks in to Project A, out for lunch, in to Project B, out; Staff record hollow blocks 100 × ₱30; Admin corrects a flagged session; project pages and exports show the expected amounts.

**Tests**

- Feature: `DemoDataSeeder` runs on a fresh database without errors.
- Feature: `DemoDataSeeder` throws when the environment is production.
- Feature (data provider of routes): admin gets 200 on `/clock`, `/attendance`, `/attendance/mine`, `/projects`, `/materials`, `/material-entries`, `/users`, `/reports`.

## Additional Tickets

### T-10.1 – Kiosk clock in/out

**Goal:** workers clock in and out every day on a shared site tablet at `/kiosk`, without logging in to the system, by entering their username and password; the screen clears for the next worker right after each success, and a live list of today's latest attendance lets each worker confirm their own entry.

**Depends on:** T-10 (built on branch `feat/attendance`, not yet on `develop`), T-09, T-04

**Current implementation**

- `App\Services\AttendanceService::clockIn(User $user, Project $project, ?float $lat, ?float $lng)` and `clockOut(User $user, ?float $lat, ?float $lng)` open and close an attendance record in a transaction that locks the user's row, and throw `AlreadyClockedInException`, `NotClockedInException`, `ProjectNotOngoingException` and `InactiveUserException` (all extend `App\Exceptions\Attendance\AttendanceException` with user-safe messages). `openSessionFor(User $user)` returns the open attendance. Reuse all of them unchanged.
- `App\Actions\Fortify\AuthenticateUser` checks a username (lowercased) and password and returns the `User`, returns `null` for wrong credentials, and throws a `ValidationException` with `auth.inactive` for an inactive user. Reuse it to verify kiosk credentials.
- `App\Support\Auth\LoginRateLimiter` (bound in `FortifyServiceProvider`) locks a username + IP for 15 minutes after 5 failures; its key is built from the `username` input and the IP, so kiosk failures and login-page failures share one lockout. The lockout message is `auth.lockout` (`App\Http\Responses\LockoutResponse`).
- `App\Livewire\Attendance\Clock` + `resources/views/livewire/attendance/clock.blade.php` (T-10) hold the patterns to reuse: the Ongoing project picker (search box + large radio cards, `has-checked:` highlight), the 56 px full-width buttons, and the browser-location helper (`locate()` with a 5 s timeout, nulls when denied).
- `Attendance` model: `open()` and `counted()` scopes, `project()` relation including archived projects, `STATUS_*` constants. `User::roleLabel()`, `User::initials()`, `<x-avatar>`.
- Every role has `attendance.clock` (`RolesAndPermissionsSeeder`). No new permission is needed.
- Wording rule (CLAUDE.md): on screen a clock-in-to-clock-out period is an "attendance" ("Clocked In" / "Clocked Out"), never a "session"; "Clock In" / "Clock Out" stay for the actions.

**Build**

- Route `GET /kiosk` (name `kiosk`) → `App\Livewire\Attendance\Kiosk`, in the `web` group but **without** `auth`; it works whether or not someone is logged in on the device and never logs anyone in. Not added to the sidebar menu.
- New layout `resources/views/layouts/kiosk.blade.php`: full-screen, light theme, navy top bar with the WRJ88 logo (`<x-app-logo-icon />`), "Attendance Kiosk", today's date and a large live clock (Asia/Manila, Alpine, every second). No sidebar, no user menu.
- `App\Services\KioskService` (business logic stays out of the component):
  - `verify(Request $request, string $username, string $password): User` — uses `LoginRateLimiter` (`tooManyAttempts` → throw with the `auth.lockout` message and minutes remaining; `increment` on a wrong password; `clear` on success) and `AuthenticateUser`; throws for wrong credentials (`auth.failed`), an inactive user (`auth.inactive`) and a user without `attendance.clock`. A user with `must_change_password = true` **may** use the kiosk.
  - `recentDuties(int $limit = 10): Collection` — today's latest clock events across all users, newest first: one row per clock-in (`clock_in_at`, type "Clocked In") and one per clock-out (`clock_out_at`, type "Clocked Out") of non-void attendances, counting only events whose time is today (so an overnight attendance's morning clock-out is listed even though its work_date is yesterday); each row has the display name, project name, type and time.
  - `displayName(User $user): string` — the full name, first and last ("Juan Dela Cruz").
- `App\Livewire\Attendance\Kiosk` + `resources/views/livewire/attendance/kiosk.blade.php`, a two-step flow:
  1. **Identify:** large Username and Password fields and a "Continue" button. On submit, call `KioskService::verify`. Errors show in the red callout above the fields (wrong credentials, lockout with minutes remaining, inactive). The password property is cleared immediately after every attempt, success or not.
  2. **Act:** show "Hi, Juan Dela Cruz" and the user's current state from `AttendanceService::openSessionFor`:
     - not clocked in → a dropdown of Ongoing projects ("CODE · Name"; the phone's own picker scrolls, so many projects are fine) and an amber **Clock In** button;
     - clocked in → the project, "Clocked In since 7:00 AM" with a running timer, and a red **Clock Out** button (no extra confirm step; the worker just verified with their password).
     - A "Not you? Start over" link returns to step 1 and clears everything.
  - The verified user is kept only as a `#[Locked]` user ID plus a verified-at time; the Clock In/Out action re-checks that the ID is set and was verified less than 2 minutes ago, otherwise it returns to step 1 with "Please enter your username and password again."
  - Clock In/Out calls `AttendanceService::clockIn` / `clockOut` with the browser location (reuse the `locate()` helper). Rule violations (`AttendanceException`) show in the callout.
  - **Right after a successful clock in/out**, reset every field and the verified user, return to step 1, and show a toast ("Juan Dela Cruz — Clocked In at 7:02 AM" / "Juan Dela Cruz — Clocked Out at 11:30 AM"). The worker confirms through the live list.
- Live attendance list on the right (below the form on phones): heading "Today's attendance", up to **10** rows from `KioskService::recentDuties(10)`, each with `<x-avatar>`, "Juan Dela Cruz", an `<x-status-badge>` "Clocked In" (green) or "Clocked Out" (zinc), the project name and the time (`g:i A`). Refreshes with `wire:poll.10s` and immediately after the kiosk's own clock in/out; the newest row is highlighted for a few seconds. Empty state: "No one has clocked in yet today." Never show rates, hours, costs or employee numbers.
- Phone and tablet layout: standard-size fields and full-width buttons 48 px tall (comfortable touch targets, not oversized); on tablet landscape and desktop the form is on the left and the live list on the right.
- Look and feel ("alive"): navy hero band with a blueprint grid and gold line like the login page, a large live clock with a time-of-day greeting, a Sign in → Clock step indicator, a pulsing live dot and an "N clocked in now" counter (`KioskService::onDutyCount()`), new rows sliding in; all motion respects reduced-motion.

**Acceptance criteria**

- [ ] `/kiosk` opens without logging in, on phone (375 px) and tablet widths.
- [ ] Wrong username or password shows "These credentials do not match our records." and no button.
- [ ] After 5 wrong passwords for the same username from the same device, the 6th attempt shows the 15-minute lockout message (the same lockout as the login page).
- [ ] A worker who is not clocked in sees Clock In and the Ongoing projects only; after clocking in, the fields clear and "Juan Dela Cruz · Clocked In · Riverside Townhomes · 7:02 AM" is the top row of the live list.
- [ ] The same worker entering their credentials again sees Clock Out; after clocking out, the fields clear and a "Clocked Out" row for them is on top.
- [ ] An inactive user gets the inactive message; a user with a temporary password (`must_change_password`) can still clock in.
- [ ] The live list shows at most 10 rows, only today's, newest first, with no money or employee numbers.
- [ ] Nobody is logged in to the web app by using the kiosk.

**Tests**

- Feature: guest `GET /kiosk` returns 200 and shows the Username and Password fields; a logged-in user can also open it.
- Feature (Livewire): correct credentials of a worker not clocked in → step 2 with Clock In and only Ongoing projects; the password property is empty after verifying.
- Feature: wrong password → `auth.failed` error, stays on step 1, no attendance created; unknown username → same message.
- Feature: 5 wrong passwords then a 6th attempt (even correct) → lockout message with minutes remaining; the login page is locked for that username too.
- Feature: inactive user → `auth.inactive` message; user without a role (no `attendance.clock`) → refused.
- Feature: `must_change_password` user → can clock in.
- Feature: clock in → open attendance with the chosen project and the user's rate; all fields and the verified user are reset; `auth()->check()` is false.
- Feature: verified user already clocked in → Clock Out shown; clocking out at 11:30 after 7:00 (`$this->travelTo()`) → closed attendance with 270 minutes, 4.50 hours, ₱360.00; fields reset.
- Feature: tampered request — calling `clockIn` without verifying, or with a verification older than 2 minutes (`$this->travel(3)->minutes()`) → back to step 1, nothing created; a non-Ongoing project ID → `ProjectNotOngoingException` message, nothing created.
- Feature: "Start over" clears the verified user and fields.
- Feature: `recentDuties(10)` with 12 clock events today and 2 yesterday → 10 rows, newest first, none from yesterday; an attendance record clocked in and out today gives both a "Clocked In" and a "Clocked Out" row; void attendances are left out.
- Unit: `displayName` — "Juan" + "Dela Cruz" → "Juan Dela Cruz"; "Maria" + "Santos" → "Maria Santos".
- Feature (data provider): admin, staff and worker can each clock in and out at the kiosk.

**Verify:** open http://wrj-hris.test/kiosk in a private window (not logged in), clock a worker in and out, and check the live list; try 6 wrong passwords; repeat at 375 px width.

#### Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`

### T-10.2 – Photo capture on clock in/out

**Goal:** every clock in and clock out, at `/kiosk` and on `/clock`, requires a photo of the worker taken with the device camera, stored privately with the attendance so Admin and Staff can check who actually clocked in.

**Depends on:** T-10.1, T-10

**Current implementation**

- `attendances` table (T-09) has no photo columns. `AttendanceService::clockIn(User, Project, ?lat, ?lng)` and `clockOut(User, ?lat, ?lng)` save the attendance inside a transaction with a user-row lock.
- `/clock` (`App\Livewire\Attendance\Clock`, T-10) and `/kiosk` (`App\Livewire\Attendance\Kiosk`, T-10.1) call those methods from an Alpine `clock(action)` helper that first gets the browser location, then calls the Livewire action.
- The `local` disk (`config/filesystems.php`) stores files under `storage/app/private`, which is not publicly reachable. It has `'serve' => true`, which only allows signed temporary URLs; do not generate `temporaryUrl()` links for photos, so the permission-checked route below stays the only way to view them.
- `attendance.view-all` is held by Admin and Staff (`RolesAndPermissionsSeeder`); there is no settings table yet (it arrives with T-14).
- PRD section 4 lists "Biometrics / face recognition" as out of scope: the photo is only stored as proof; nothing compares faces.

**Build**

- `config/attendance.php` with `'require_photo' => (bool) env('ATTENDANCE_REQUIRE_PHOTO', true)`. Read it through `App\Support\AttendancePhotos::required(): bool` so a later Settings screen can replace the source in one place.
- New migration (new file; do not edit the T-09 migration): add `clock_in_photo_path` and `clock_out_photo_path` (nullable string 255) to `attendances`, with a working `down()`.
- `App\Support\AttendancePhotos`:
  - `required(): bool`.
  - `store(UploadedFile $photo, User $user, string $type): string` — saves to the `local` disk at `attendance-photos/{Y}/{m}/{user_id}-{type}-{uuid}.jpg` and returns the path; `$type` is `in` or `out`.
  - `rules(): array` — `required` when `required()` is true, otherwise `nullable`; plus `image`, `mimes:jpeg,png`, `max:2048` (KB).
- `AttendanceService::clockIn` and `clockOut` get an optional `?string $photoPath = null` parameter, saved to `clock_in_photo_path` / `clock_out_photo_path`. Existing calls without a photo keep working.
- Camera step, shared by both screens as a Blade component `resources/views/components/camera-capture.blade.php` (Alpine):
  - Opens the front camera with `navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })` in a live preview, with a large "Take photo" button, then shows the snapshot with "Retake" and "Use photo".
  - Draws the frame to a canvas, scales it to at most 720 px on the long side, encodes JPEG at quality 0.8, and uploads it with Livewire's file upload (`WithFileUploads`, `$wire.upload('photo', blob)`) before the clock action is sent.
  - Stops the camera stream after the photo is taken or the step is cancelled.
  - If the camera is denied or missing and a photo is required, show "Allow camera access to clock in or out. Ask your admin if this device has no camera." and keep the Clock In/Out button disabled.
- `/clock` and `/kiosk`: when `AttendancePhotos::required()` is true, the Clock In / Clock Out button opens the camera step first; the action validates the `photo` property with `AttendancePhotos::rules()`, stores it with `AttendancePhotos::store`, and passes the path to `AttendanceService`. After success the photo property is reset (the kiosk already resets everything). When not required, the flow is unchanged.
- Viewing: route `GET /attendance/{attendance}/photo/{type}` (name `attendance.photo`, middleware `auth` and `permission:attendance.view-all`, `type` constrained to `in|out`) streams the file from the `local` disk with `Content-Type: image/jpeg`; 404 when the path is empty or the file is missing. Photos are never served from `public/`. (T-11 will show thumbnails in the attendance history using this route.)

**Acceptance criteria**

- [ ] On `/clock` and `/kiosk`, Clock In and Clock Out cannot complete without a photo while `require_photo` is true.
- [ ] After clocking in with a photo, the attendance has `clock_in_photo_path` set and the file exists under `storage/app/private/attendance-photos/…`; clocking out sets `clock_out_photo_path`.
- [ ] Admin and Staff can open the photo at `/attendance/{id}/photo/in`; a worker gets 403; a guest is sent to the login page.
- [ ] With `ATTENDANCE_REQUIRE_PHOTO=false`, clock in/out works without the camera step, exactly as before.
- [ ] On a phone (375 px) the preview and buttons fit without horizontal scrolling.

**Tests**

- Feature (`Storage::fake('local')`, `UploadedFile::fake()->image('me.jpg', 720, 960)`): `/clock` clock in with a photo → open attendance, `clock_in_photo_path` set, file exists on the fake disk; clock out with a photo → `clock_out_photo_path` set.
- Feature: `/clock` clock in without a photo while required → validation error on `photo`, no attendance created.
- Feature: `/kiosk` (verified worker) clock in and clock out with a photo → both paths set; without a photo → error, nothing created.
- Feature: a non-image upload (`UploadedFile::fake()->create('a.pdf', 10)`) and an image over 2 MB → validation error.
- Feature: `config(['attendance.require_photo' => false])` → clock in and out without a photo work on both screens.
- Feature (data provider): `GET /attendance/{id}/photo/in` — admin 200 with `image/jpeg`, staff 200, worker 403, guest redirected to login; missing file or `type` other than `in|out` → 404.
- Feature: existing `AttendanceService` calls without a photo still work (T-09 tests stay green).
- Unit: `AttendancePhotos::rules()` is `required` when required and `nullable` when not.

**Verify:** on a phone or tablet, clock in at `/kiosk` and `/clock`, allow the camera, take the photo, then open `/attendance/{id}/photo/in` as Admin; deny the camera once and confirm the button stays disabled with the message.

#### Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`

### T-20 – Roles and permissions screen

**Goal:** an Admin opens Admin → Roles & Permissions, ticks which permissions the Staff and Worker roles have, and saves; the menus, pages and actions of every user with that role follow the change at once.

**Depends on:** T-02, T-05, T-06

**Current implementation**

- `database/seeders/RolesAndPermissionsSeeder.php` (T-02) holds the PRD section 3 table in `ROLE_PERMISSIONS` (roles `admin`, `staff`, `worker`; 10 permissions: `attendance.clock`, `attendance.view-own`, `attendance.view-all`, `attendance.correct`, `users.manage`, `projects.manage`, `materials.manage`, `material-entries.create`, `expenses.view`, `reports.export`). `run()` calls `Role::firstOrCreate(...)->syncPermissions($permissions)`, so **every run resets each role to the defaults** — it would wipe out changes made on the new screen and must change.
- `UserFactory::withRole()` runs the seeder when the role does not exist yet; `tests/Feature/RolesAndPermissionsTest.php` checks each role has exactly the table above after seeding (still true for a fresh seed).
- Every route and Livewire action already checks permissions, not roles: menu items and their route middleware come from `App\Support\Navigation::items()` / `middlewareFor()` (`permission:…`), and components check `Auth::user()->can('…')` in `boot()`. So changing a role's permissions changes what its users can see and do, with no other code change.
- `App\Models\User::ROLES` = `['admin', 'staff', 'worker']`; T-06 lets the Admin pick a user's role on `/users/create` and `/users/{user}/edit` (`users.manage`, behind `password.confirm`). Roles themselves are not created or renamed in this ticket.
- `spatie/laravel-activitylog` is installed and its `activity_log` table is migrated, but nothing writes to it yet (T-17 adds the History panels).
- Page building blocks to reuse: `<x-page-header>`, `<x-stat-card>`, `<x-data-card>`, `<x-status-badge>`, `btn-accent`, `flux:checkbox`, `flux:modal`, `Flux::toast()`.

**Build**

- New permission `roles.manage`, Admin only:
  - Add it to `RolesAndPermissionsSeeder::ROLE_PERMISSIONS['admin']`.
  - Change `run()` so it never overwrites saved changes: create missing permissions and roles with `firstOrCreate`; give a role its default permissions only when the role was just created (`wasRecentlyCreated`), and give a permission to its default roles only when the permission was just created. Running the seeder again on an edited database changes nothing; a permission added by a later ticket still reaches its default roles once.
- `App\Support\PermissionCatalog` (labels only, no logic): for each permission name, a short label, a one-line description in simple English, and a group, in display order:
  - Time: `attendance.clock` "Clock in and out", `attendance.view-own` "See own attendance", `attendance.view-all` "See everyone's attendance", `attendance.correct` "Correct attendance".
  - Operations: `projects.manage` "Manage projects", `materials.manage` "Manage materials", `material-entries.create` "Record material purchases", `expenses.view` "See project expenses".
  - Admin: `users.manage` "Manage users and hourly rates", `reports.export` "Export reports", `roles.manage` "Manage roles and permissions".
  - `PermissionCatalog::all()` returns them grouped; a unit test fails if a permission in the seeder has no catalog entry.
- `App\Services\RolePermissionService`:
  - `matrix(): array<string, list<string>>` — current permission names per role (`admin`, `staff`, `worker`).
  - `update(array $grants, User $admin): void` — `$grants` is `['staff' => [...], 'worker' => [...]]`. Inside a DB transaction: reject unknown roles or permission names (`ValidationException`); the `admin` role is never changed (it always keeps every permission); `roles.manage` can never be given to `staff` or `worker`; `syncPermissions()` only on roles whose set actually changed; then `app(PermissionRegistrar::class)->forgetCachedPermissions()`.
  - `restoreDefaults(User $admin): void` — sets `staff` and `worker` back to `RolesAndPermissionsSeeder::ROLE_PERMISSIONS` through `update()`.
  - Each changed role writes one activity: `activity('roles')->performedOn($role)->causedBy($admin)->withProperties(['old' => [...], 'new' => [...]])->log('Changed permissions')` (PRD 8 audit; T-17 shows it later).
- Livewire `App\Livewire\Roles\Index` (class in `app/Livewire/Roles/Index.php`, view `resources/views/livewire/roles/index.blade.php`), `#[Title('Roles & Permissions')]`:
  - `boot()`: `abort_unless(Auth::user()?->can('roles.manage'), 403)`; `save()` and `restoreDefaults()` also run behind that check.
  - `public array $grants` (role → permission → bool) loaded from `matrix()`; `#[Computed] hasChanges` compares it with the saved matrix; `save()` calls `update()`, reloads `$grants`, and toasts "Permissions saved. Users see the change on their next page load."; `discard()` reloads the saved matrix.
- Menu and route: add to `Navigation::items()` `['label' => 'Roles & Permissions', 'url' => 'roles', 'route' => 'roles.index', 'group' => 'Admin', 'icon' => 'shield-check', 'permissions' => ['roles.manage'], 'component' => Roles\Index::class, 'middleware' => ['password.confirm']]` (after Users, before Reports). The route is registered by the existing loop in `routes/web.php`.
- Page design (standard pattern, light theme, brand tokens only):
  - `<x-page-header>` "Roles & Permissions", description "Choose what each role can see and do. Admin always has every permission." Actions on the right: "Restore defaults" (secondary button, opens a confirm `flux:modal`: "Set Staff and Worker back to the standard permissions?") and "Save changes" (`btn-accent`, disabled until `hasChanges`).
  - A row of three `<x-stat-card>`s, one per role: label "Admin" / "Staff" / "Worker", value ":count of :total permissions", note ":count users" (users with that role, archived users not counted).
  - The matrix inside `<x-data-card>`: a `flux:table` with columns Permission (label in bold, description below in `text-zinc-500`), Admin, Staff, Worker (centered `flux:checkbox`), with a group heading row per group (Time / Operations / Admin, small uppercase, `bg-brand-page`). The Admin column is always checked and disabled with a lock icon and the tooltip "Admin always has every permission"; the Staff and Worker boxes on the `roles.manage` row are unchecked and disabled ("Only Admin can manage roles"). This is a fixed settings grid of 11 rows, so it is not paginated (exception to the rows-per-page rule).
  - Phone (`md:hidden`): one card per permission with its label, description and three labeled checkboxes (Admin / Staff / Worker) in a row.
  - While `hasChanges`, a sticky bar at the bottom of the card: "You have unsaved changes" with "Discard" and "Save changes".
- Update existing tests that list every menu route or every permission so they include `roles.index` / `roles.manage` (e.g. `NavigationTest`, `RouteAuthorizationTest`, `RolesAndPermissionsTest`).

**Acceptance criteria**

- [ ] Admin sees "Roles & Permissions" under Admin in the sidebar; Staff and Worker do not, and get 403 on `/roles`.
- [ ] Ticking "Manage materials" for Worker and saving lets a worker open `/materials` (200) and shows Materials in their menu; unticking it and saving gives 403 again.
- [ ] The Admin column cannot be changed, and `roles.manage` cannot be given to Staff or Worker, even with a tampered Livewire request.
- [ ] "Restore defaults" puts Staff and Worker back to exactly the PRD section 3 table.
- [ ] Running `php artisan db:seed --class=RolesAndPermissionsSeeder` after a change keeps the change.
- [ ] Each saved change to a role is in `activity_log` with the Admin as causer and the old and new permission lists.

**Tests**

- Unit: every permission in `RolesAndPermissionsSeeder::ROLE_PERMISSIONS` has a `PermissionCatalog` entry with a label, description and group, and the catalog has no extra names.
- Feature (data provider): `/roles` — admin 200 (after password confirmation), staff 403, worker 403; guest redirected to login.
- Feature: after a fresh seed, admin has all 11 permissions including `roles.manage`; staff and worker match the PRD table (update `RolesAndPermissionsTest`).
- Feature: give `staff` an extra permission with `RolePermissionService::update()`, run the seeder again, and the extra permission is still there.
- Feature: Livewire — tick `grants.worker.materials.manage`, `save()` → worker can open `/materials`; untick and save → 403.
- Feature: Livewire — setting `grants.admin.users.manage` to false or `grants.staff.roles.manage` to true and calling `save()` leaves both unchanged (admin keeps it, staff never gets it).
- Feature: `update()` with an unknown permission name throws a validation error and changes nothing.
- Feature: `restoreDefaults()` after changes gives Staff and Worker exactly the PRD table.
- Feature: one save that changes Staff writes one `activity_log` row with `causer_id` = the admin and properties `old` and `new`.
- Feature: "Save changes" is disabled with no changes, enabled after ticking a box, and `discard()` restores the saved state.
- Feature: a staff user calling the component's `save()` directly gets 403.

**Verify:** log in as Admin → Admin → Roles & Permissions; tick "Manage materials" for Worker and save; log in as a worker in another browser and see Materials in the menu; untick, save, refresh and confirm it is gone; check a phone width of 375 px.

#### Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
