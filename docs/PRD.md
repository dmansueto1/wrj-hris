# WRJ HRIS – Phase 1 PRD

## 1. Summary

Phase 1 replaces paper time in/out sheets with a web-based system where construction workers and staff clock in and out against a project, and every completed session is automatically costed as labor on that project. Combined with staff-recorded material purchases (plus a 20% markup), management sees each project's running expenses in real time.

**Problem today**

- Paper or spreadsheet time sheets are slow to tally and easy to alter.
- Labor cost per project is unknown until payroll is computed, if at all.
- Workers move between projects day to day, and even within a day, so labor is often charged to the wrong project.
- Material spending is tracked separately from labor, so no one sees a project's total cost to date.

**Solution:** a Laravel + MySQL web system, used from any phone, tablet or computer browser, with masterfiles for Users, Projects and Materials.

## 2. Goals and Success Metrics

The goal is accurate, same-day labor and material costs per project, with no manual tallying of time sheets.

**Goals**

1. Every worker and staff member records attendance digitally, per project, per session.
2. Labor cost is computed automatically on every clock-out.
3. Material purchases are recorded per project with the 20% markup applied automatically.
4. Each project shows running expenses (labor + materials) at any time.

**Success metrics (first 2 months after go-live)**

| Metric | Target |
| --- | --- |
| Active users clocking in digitally | 100% of workers and staff |
| Sessions closed without Admin correction | 95% or more |
| Time to clock in or out | Under 30 seconds |
| Material purchases recorded within 2 days of purchase | 90% or more |
| Manual time-sheet tallying | Eliminated |

**Non-goals for Phase 1:** payroll and payslips, overtime and holiday pay, government deductions, leave, inventory, native mobile apps, offline mode, and two-factor authentication.

## 3. Users and Personas

Three roles use the system; workers need the simplest possible screen, while Admin needs full control.

| Role | Who | Main needs | Device |
| --- | --- | --- | --- |
| Worker | Masons, carpenters, laborers, helpers on site | Clock in/out quickly, pick the right project, see own hours | Own phone browser or a shared site tablet |
| Staff | Office clerks, site timekeepers, purchasers | Clock in/out, record material purchases, view project expenses | Computer or tablet browser |
| Admin | Owner, HR, project manager | Manage masterfiles and rates, correct attendance, view all reports | Computer browser |

**Permissions**

| Permission | Admin | Staff | Worker |
| --- | --- | --- | --- |
| Clock in / out (select project) | Yes | Yes | Yes |
| View own attendance | Yes | Yes | Yes |
| View all attendance | Yes | Read-only | No |
| Correct attendance (with reason) | Yes | No | No |
| Manage Users and hourly rates | Yes | No | No |
| Manage Projects | Yes | No | No |
| Manage Materials | Yes | Yes | No |
| Record material purchases | Yes | Yes | No |
| View running expenses and reports | Yes | Yes | No |

## 4. Scope

Phase 1 is a responsive web system only, used through a browser; it requires an internet connection at the point of clock-in.

| In scope | Out of scope |
| --- | --- |
| Login with strong password rules and lockout | Two-factor authentication |
| Clock in/out with project selection, multiple sessions per day | Native mobile apps (Android / iOS) |
| Per-user hourly rate with rate history | Offline mode / offline sync |
| Automatic labor cost per session | Payroll, payslips, overtime, holiday pay |
| Material purchases per project with 20% markup | SSS, PhilHealth, Pag-IBIG, tax deductions |
| Running expenses per project | Leave management |
| Masterfiles: Users, Projects, Materials | Material inventory and suppliers |
| Attendance correction by Admin with audit log | Biometrics / face recognition |
| Reports with Excel and PDF export | Budget alerts and analytics dashboards |

## 5. User Stories and Acceptance Criteria

### 5.1 Authentication

**US-01 – As any user, I want to log in securely so only I can record my attendance.**

- Login uses employee number or email plus password.
- Passwords must be at least 12 characters with upper and lower case letters, a number and a symbol.
- After 5 failed attempts the account is locked for 15 minutes.
- Inactive users cannot log in.
- Users are logged out after 30 minutes of inactivity (Admin and Staff).

**US-02 – As any user, I want to reset my password if I forget it.**

- A reset link is sent by email, or Admin can reset a password manually for workers without email.
- Users must change a password set by Admin on their next login.

### 5.2 Attendance

**US-10 – As a worker, I want to clock in and choose my project so my hours are charged to the right site.**

- The clock-in screen lists only projects with status Ongoing.
- On clock-in, the system saves the time, the project and my current hourly rate.
- I cannot clock in if I already have an open session; the screen shows a Clock Out button instead.

**US-11 – As a worker, I want to clock out so my hours are recorded.**

- On clock-out, the system computes hours worked and labor cost for that session.
- The labor cost is added to that session's project running expenses immediately.
- A confirmation shows the session's start, end and hours.

**US-12 – As a worker, I want to clock in and out several times a day (e.g. morning and afternoon).**

- Each clock-in/clock-out pair is saved as a separate session.
- Each session can be on a different project.
- The time between sessions (e.g. lunch) is not counted.
- My home screen shows today's sessions and total hours.

**US-13 – As a worker, I want to see my attendance history.**

- I can filter by date range and see sessions, projects and total hours per day.

**US-14 – As an Admin, I want to correct attendance so mistakes do not affect project costs.**

- Admin can set a missing clock-out, change the project, split or void a session.
- A reason is required; the original values and the editor are kept in the audit log.
- Labor cost is recomputed using the session's saved hourly rate.

### 5.3 Masterfiles

**US-20 – As an Admin, I want to manage users and their hourly rates.**

- Fields: employee no., name, email/username, role, position, hourly rate, status.
- A rate change records the new rate and its effective date; it applies only to new sessions.

**US-21 – As an Admin, I want to manage projects.**

- Fields: code, name, location, client, start and end date, budget (optional), status.

**US-22 – As Staff or Admin, I want to manage materials.**

- Fields: code, name, unit, default price, status.
- Records are archived instead of deleted so past entries stay intact.

### 5.4 Materials and Expenses

**US-30 – As Staff, I want to record a material purchase for a project.**

- I enter the date, project, material, quantity and unit price (pre-filled from the default price).
- The system shows subtotal, 20% markup and total cost before I save.
- The saved entry keeps the markup rate used.

**US-31 – As Staff or Admin, I want to see a project's running expenses.**

- The project page shows labor total, materials total and running expenses.
- Breakdowns: labor by worker and date; materials by item and date.
- If a budget is set, the page shows the percentage used.

**US-32 – As Staff or Admin, I want to export reports.**

- Attendance report and project expense report can be filtered by date range and exported to Excel and PDF.

## 6. Clock In/Out Flow

```text
Log in → Open session?
           ├─ yes → show Clock Out button ─────────────┐
           └─ no  → Select project (Ongoing only)      │
                     → Clock in (saves time + rate)    │
                     → ...working...                   │
                     → Clock out (closes the session) ◄┘
                     → Compute labor cost (hours × hourly rate)
                     → Add to the project's running expenses
                     → next session (e.g. afternoon) → back to "Open session?"
```

A user can repeat the loop as many times a day as needed; each pass is one session with its own project, hours and labor cost.

## 7. Business Rules and Computations

Labor is costed per session, so morning and afternoon sessions, even on different projects, are computed separately and then summed for the day.

### 7.1 Labor cost per session

```
hours_worked = (clock_out − clock_in in minutes) ÷ 60
labor_cost   = hours_worked × hourly_rate_at_clock_in
```

**Example: one worker, one day, rate ₱80/hr**

| Session | Project | Clock in | Clock out | Hours | Labor cost (₱) |
| --- | --- | --- | --- | --- | --- |
| Morning | Project A | 7:00 AM | 11:30 AM | 4.50 | 360.00 |
| Afternoon | Project B | 12:30 PM | 5:00 PM | 4.50 | 360.00 |
| **Daily total** | | | | **9.00** | **720.00** |

Project A is charged ₱360.00 and Project B ₱360.00; the lunch hour is not paid because no session was open.

### 7.2 Material cost per entry

```
material_cost = (price × qty) + (price × qty × 0.20)
```

Example, hollow blocks: (30 × 100) + (30 × 100 × 0.20) = 3,000 + 600 = ₱3,600.00.

### 7.3 Project running expenses

```
running_expenses = Σ labor_cost (closed sessions) + Σ material_cost
```

### 7.4 Rules

1. Only one open session per user at a time.
2. Sessions of the same user cannot overlap.
3. Only closed sessions count toward labor cost.
4. The hourly rate and the 20% markup are saved on each record, so later changes never alter past costs.
5. Money and hours are stored as decimals and rounded to 2 decimal places.
6. Sessions crossing midnight are valid; the work date is the clock-in date.
7. Sessions still open after 12 hours are flagged for Admin review.
8. All times are in Philippine time (Asia/Manila).

## 8. Non-Functional Requirements

Security in Phase 1 relies on strong passwords, lockout and strict roles; two-factor authentication is deferred.

| Area | Requirement |
| --- | --- |
| Password policy | Minimum 12 characters, mixed case, number, symbol; rejects known breached passwords |
| Password storage | Hashed with bcrypt or argon2id; never stored in plain text |
| Brute-force protection | 5 failed attempts → 15-minute lockout per account and IP |
| Sessions | HTTPS only, secure HttpOnly cookies, 30-minute idle timeout for Admin and Staff |
| Authorization | Every page and action checked against the user's role |
| Web security | CSRF protection, output escaping, parameterized queries (Laravel defaults) |
| Audit | Attendance corrections, rate changes and material entries are logged with user and time |
| Performance | Clock in/out completes in under 2 seconds; reports for one month load in under 5 seconds |
| Availability | 99% uptime during working hours (6 AM – 8 PM) |
| Usability | Mobile-friendly layout; clock-in screen usable with one hand and large buttons; English with simple wording |
| Browser support | Latest Chrome, Safari, Edge and Firefox on phone, tablet and desktop |
| Backups | Automated daily database backup kept off-server for 30 days |
| Data privacy | Personal data handled per the Data Privacy Act of 2012; access limited by role |
| Connectivity | Requires internet at clock-in/out; no offline mode |

## 9. Technical Approach

The system is a single Laravel web application on MySQL; no separate mobile app or API is needed.

| Layer | Technology |
| --- | --- |
| Backend | Laravel (latest stable), PHP 8.3+ |
| Database | MySQL 8 (InnoDB), money and hours as DECIMAL |
| Frontend | Livewire + Alpine.js + Tailwind CSS (Livewire starter kit, Flux UI), responsive for phone, tablet and desktop |
| Authentication | Laravel starter kit built-in authentication (login, password reset, throttling, password confirmation) |
| Roles and permissions | spatie/laravel-permission |
| Audit log | spatie/laravel-activitylog |
| Exports | maatwebsite/excel, barryvdh/laravel-dompdf |
| Scheduled jobs | Laravel Scheduler (flag sessions open over 12 hours) |
| Hosting | Ubuntu VPS, Nginx, PHP-FPM, HTTPS via Let's Encrypt |

**Data model summary**

| Table | Purpose | Key fields |
| --- | --- | --- |
| users | Workers, staff, admins | employee_no, name, role, hourly_rate, status |
| user_rate_histories | Rate changes over time | user_id, hourly_rate, effective_from |
| projects | Project masterfile | code, name, location, budget, status |
| materials | Material masterfile | code, name, unit, default_price |
| attendances | One row per clock in/out session | user_id, project_id, work_date, clock_in_at, clock_out_at, hourly_rate, hours_worked, labor_cost, status |
| project_material_entries | Material purchases per project | project_id, material_id, quantity, unit_price, markup_rate, subtotal, markup_amount, total_cost |
| settings | System values | material_markup_rate = 0.20 |

Clock in/out runs inside a database transaction with row locking, so double-taps cannot create duplicate sessions.

## 10. Testing Requirements

Every feature ships with automated tests, and no change is merged unless the full test suite passes.

### 10.1 Approach

| Test type | What it covers | Tool |
| --- | --- | --- |
| Unit tests | Pure calculations with no database: labor cost, hours, material cost and markup, rounding | Pest |
| Feature tests | Full flows through the app: login, clock in/out, masterfile screens, material entries, running expenses, reports | Pest + Livewire testing helpers |
| Authorization tests | Each role can only see and do what the permissions table in section 3 allows | Pest |
| Manual acceptance testing (UAT) | Real users on a phone, tablet and desktop before go-live | Checklist |

**Standards**

- Tests run with `php artisan test`; the suite must pass with zero failures before each merge.
- Tests use a separate test database and never touch real data.
- Code coverage of at least 90% for calculation and service classes, and at least 70% overall.
- Money is computed in whole centavos with half-up rounding so results are exact; tests check the exact peso amounts.
- Tests run automatically on every push to GitHub (GitHub Actions).

### 10.2 Required unit tests: labor cost

Labor cost is computed from exact minutes, so rounding the displayed hours never changes the amount.

| Case | Minutes | Rate (₱/hr) | Expected hours | Expected labor cost (₱) |
| --- | --- | --- | --- | --- |
| Morning session 7:00–11:30 | 270 | 80.00 | 4.50 | 360.00 |
| Uneven minutes 7:00–11:20 | 260 | 80.00 | 4.33 | 346.67 |
| One-minute session | 1 | 75.00 | 0.02 | 1.25 |
| Zero minutes | 0 | 80.00 | 0.00 | 0.00 |
| Overnight 10:00 PM–6:00 AM | 480 | 95.50 | 8.00 | 764.00 |
| Two sessions in one day | 270 + 270 | 80.00 | 9.00 total | 720.00 total |

### 10.3 Required unit tests: material cost

| Case | Unit price (₱) | Quantity | Subtotal (₱) | Markup 20% (₱) | Total (₱) |
| --- | --- | --- | --- | --- | --- |
| Hollow blocks | 30.00 | 100 | 3,000.00 | 600.00 | 3,600.00 |
| Decimal quantity | 12.50 | 3.5 | 43.75 | 8.75 | 52.50 |
| Markup rounding | 0.33 | 3 | 0.99 | 0.20 | 1.19 |
| Saved markup rate kept | 30.00 | 100 | 3,000.00 | 600.00 (rate saved as 0.20 even if setting later changes to 0.25) | 3,600.00 |

### 10.4 Required feature tests

- **Login:** valid login by employee number and by email; wrong password rejected; lockout after 5 failed attempts; inactive user blocked; registration page does not exist.
- **Clock in:** only Ongoing projects are listed; the hourly rate is saved at clock-in; a second clock-in while a session is open is rejected.
- **Clock out:** hours and labor cost are saved; the project's running expenses increase by exactly that amount.
- **Multiple sessions:** morning on Project A and afternoon on Project B produce two sessions charged to the right projects; the lunch gap is not counted.
- **Rate change:** a new hourly rate applies only to sessions clocked in after the change.
- **Corrections:** only Admin can correct; a reason is required; labor is recomputed; the change is in the audit log.
- **Flagging:** sessions open over 12 hours are flagged by the scheduled job.
- **Materials:** a saved entry stores subtotal, markup and total; Workers cannot create entries.
- **Running expenses:** labor (closed sessions only) + materials equals the total shown on the project page.
- **Reports:** Excel and PDF exports download and contain the filtered rows.
- **Authorization:** every row of the permissions table is tested for each role.
