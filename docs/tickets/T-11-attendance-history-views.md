# T-11 – Attendance history views


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

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
- [ ] Committed with the ticket ID and merged to `main`
