# T-13 – Flag forgotten clock-outs


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

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
