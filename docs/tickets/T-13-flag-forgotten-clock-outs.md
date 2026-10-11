# T-13 – Flag forgotten clock-outs


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

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
