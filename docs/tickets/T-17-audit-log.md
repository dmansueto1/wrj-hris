# T-17 – Audit log


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

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
- [ ] Committed with the ticket ID and merged to `main`
