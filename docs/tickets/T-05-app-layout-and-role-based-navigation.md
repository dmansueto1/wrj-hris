# T-05 – App layout and role-based navigation


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

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
- [ ] Committed with the ticket ID and merged to `main`
