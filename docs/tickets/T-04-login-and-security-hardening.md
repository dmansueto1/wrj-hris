# T-04 – Login and security hardening


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

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
