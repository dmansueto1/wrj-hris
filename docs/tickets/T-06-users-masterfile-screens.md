# T-06 – Users masterfile screens


**Goal:** the Admin can create, edit, deactivate and reset passwords for users, and change hourly rates with a visible history.

**Depends on:** T-05

**Build**

- `/users` list (permission `users.manage`, behind `password.confirm`): columns employee no., name, username, role, position, hourly rate, status; search by name, username or employee no.; filter by role and status; 20 per page.
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

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
