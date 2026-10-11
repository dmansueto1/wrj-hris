# T-07 – Projects masterfile


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

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
