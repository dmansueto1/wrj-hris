# T-02 – Roles and permissions


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

- Feature (dataset): for each role, the set of permissions equals the table above — no more, no less.
- Feature: running the seeder twice does not create duplicates.

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
