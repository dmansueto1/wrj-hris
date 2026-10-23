# T-20 – Roles and permissions screen

**Goal:** an Admin opens Admin → Roles & Permissions, ticks which permissions the Staff and Worker roles have, and saves; the menus, pages and actions of every user with that role follow the change at once.

**Depends on:** T-02, T-05, T-06

**Current implementation**

- `database/seeders/RolesAndPermissionsSeeder.php` (T-02) holds the PRD section 3 table in `ROLE_PERMISSIONS` (roles `admin`, `staff`, `worker`; 10 permissions: `attendance.clock`, `attendance.view-own`, `attendance.view-all`, `attendance.correct`, `users.manage`, `projects.manage`, `materials.manage`, `material-entries.create`, `expenses.view`, `reports.export`). `run()` calls `Role::firstOrCreate(...)->syncPermissions($permissions)`, so **every run resets each role to the defaults** — it would wipe out changes made on the new screen and must change.
- `UserFactory::withRole()` runs the seeder when the role does not exist yet; `tests/Feature/RolesAndPermissionsTest.php` checks each role has exactly the table above after seeding (still true for a fresh seed).
- Every route and Livewire action already checks permissions, not roles: menu items and their route middleware come from `App\Support\Navigation::items()` / `middlewareFor()` (`permission:…`), and components check `Auth::user()->can('…')` in `boot()`. So changing a role's permissions changes what its users can see and do, with no other code change.
- `App\Models\User::ROLES` = `['admin', 'staff', 'worker']`; T-06 lets the Admin pick a user's role on `/users/create` and `/users/{user}/edit` (`users.manage`, behind `password.confirm`). Roles themselves are not created or renamed in this ticket.
- `spatie/laravel-activitylog` is installed and its `activity_log` table is migrated, but nothing writes to it yet (T-17 adds the History panels).
- Page building blocks to reuse: `<x-page-header>`, `<x-stat-card>`, `<x-data-card>`, `<x-status-badge>`, `btn-accent`, `flux:checkbox`, `flux:modal`, `Flux::toast()`.

**Build**

- New permission `roles.manage`, Admin only:
  - Add it to `RolesAndPermissionsSeeder::ROLE_PERMISSIONS['admin']`.
  - Change `run()` so it never overwrites saved changes: create missing permissions and roles with `firstOrCreate`; give a role its default permissions only when the role was just created (`wasRecentlyCreated`), and give a permission to its default roles only when the permission was just created. Running the seeder again on an edited database changes nothing; a permission added by a later ticket still reaches its default roles once.
- `App\Support\PermissionCatalog` (labels only, no logic): for each permission name, a short label, a one-line description in simple English, and a group, in display order:
  - Time: `attendance.clock` "Clock in and out", `attendance.view-own` "See own attendance", `attendance.view-all` "See everyone's attendance", `attendance.correct` "Correct attendance".
  - Operations: `projects.manage` "Manage projects", `materials.manage` "Manage materials", `material-entries.create` "Record material purchases", `expenses.view` "See project expenses".
  - Admin: `users.manage` "Manage users and hourly rates", `reports.export` "Export reports", `roles.manage` "Manage roles and permissions".
  - `PermissionCatalog::all()` returns them grouped; a unit test fails if a permission in the seeder has no catalog entry.
- `App\Services\RolePermissionService`:
  - `matrix(): array<string, list<string>>` — current permission names per role (`admin`, `staff`, `worker`).
  - `update(array $grants, User $admin): void` — `$grants` is `['staff' => [...], 'worker' => [...]]`. Inside a DB transaction: reject unknown roles or permission names (`ValidationException`); the `admin` role is never changed (it always keeps every permission); `roles.manage` can never be given to `staff` or `worker`; `syncPermissions()` only on roles whose set actually changed; then `app(PermissionRegistrar::class)->forgetCachedPermissions()`.
  - `restoreDefaults(User $admin): void` — sets `staff` and `worker` back to `RolesAndPermissionsSeeder::ROLE_PERMISSIONS` through `update()`.
  - Each changed role writes one activity: `activity('roles')->performedOn($role)->causedBy($admin)->withProperties(['old' => [...], 'new' => [...]])->log('Changed permissions')` (PRD 8 audit; T-17 shows it later).
- Livewire `App\Livewire\Roles\Index` (class in `app/Livewire/Roles/Index.php`, view `resources/views/livewire/roles/index.blade.php`), `#[Title('Roles & Permissions')]`:
  - `boot()`: `abort_unless(Auth::user()?->can('roles.manage'), 403)`; `save()` and `restoreDefaults()` also run behind that check.
  - `public array $grants` (role → permission → bool) loaded from `matrix()`; `#[Computed] hasChanges` compares it with the saved matrix; `save()` calls `update()`, reloads `$grants`, and toasts "Permissions saved. Users see the change on their next page load."; `discard()` reloads the saved matrix.
- Menu and route: add to `Navigation::items()` `['label' => 'Roles & Permissions', 'url' => 'roles', 'route' => 'roles.index', 'group' => 'Admin', 'icon' => 'shield-check', 'permissions' => ['roles.manage'], 'component' => Roles\Index::class, 'middleware' => ['password.confirm']]` (after Users, before Reports). The route is registered by the existing loop in `routes/web.php`.
- Page design (standard pattern, light theme, brand tokens only):
  - `<x-page-header>` "Roles & Permissions", description "Choose what each role can see and do. Admin always has every permission." Actions on the right: "Restore defaults" (secondary button, opens a confirm `flux:modal`: "Set Staff and Worker back to the standard permissions?") and "Save changes" (`btn-accent`, disabled until `hasChanges`).
  - A row of three `<x-stat-card>`s, one per role: label "Admin" / "Staff" / "Worker", value ":count of :total permissions", note ":count users" (users with that role, archived users not counted).
  - The matrix inside `<x-data-card>`: a `flux:table` with columns Permission (label in bold, description below in `text-zinc-500`), Admin, Staff, Worker (centered `flux:checkbox`), with a group heading row per group (Time / Operations / Admin, small uppercase, `bg-brand-page`). The Admin column is always checked and disabled with a lock icon and the tooltip "Admin always has every permission"; the Staff and Worker boxes on the `roles.manage` row are unchecked and disabled ("Only Admin can manage roles"). This is a fixed settings grid of 11 rows, so it is not paginated (exception to the rows-per-page rule).
  - Phone (`md:hidden`): one card per permission with its label, description and three labeled checkboxes (Admin / Staff / Worker) in a row.
  - While `hasChanges`, a sticky bar at the bottom of the card: "You have unsaved changes" with "Discard" and "Save changes".
- Update existing tests that list every menu route or every permission so they include `roles.index` / `roles.manage` (e.g. `NavigationTest`, `RouteAuthorizationTest`, `RolesAndPermissionsTest`).

**Acceptance criteria**

- [ ] Admin sees "Roles & Permissions" under Admin in the sidebar; Staff and Worker do not, and get 403 on `/roles`.
- [ ] Ticking "Manage materials" for Worker and saving lets a worker open `/materials` (200) and shows Materials in their menu; unticking it and saving gives 403 again.
- [ ] The Admin column cannot be changed, and `roles.manage` cannot be given to Staff or Worker, even with a tampered Livewire request.
- [ ] "Restore defaults" puts Staff and Worker back to exactly the PRD section 3 table.
- [ ] Running `php artisan db:seed --class=RolesAndPermissionsSeeder` after a change keeps the change.
- [ ] Each saved change to a role is in `activity_log` with the Admin as causer and the old and new permission lists.

**Tests**

- Unit: every permission in `RolesAndPermissionsSeeder::ROLE_PERMISSIONS` has a `PermissionCatalog` entry with a label, description and group, and the catalog has no extra names.
- Feature (data provider): `/roles` — admin 200 (after password confirmation), staff 403, worker 403; guest redirected to login.
- Feature: after a fresh seed, admin has all 11 permissions including `roles.manage`; staff and worker match the PRD table (update `RolesAndPermissionsTest`).
- Feature: give `staff` an extra permission with `RolePermissionService::update()`, run the seeder again, and the extra permission is still there.
- Feature: Livewire — tick `grants.worker.materials.manage`, `save()` → worker can open `/materials`; untick and save → 403.
- Feature: Livewire — setting `grants.admin.users.manage` to false or `grants.staff.roles.manage` to true and calling `save()` leaves both unchanged (admin keeps it, staff never gets it).
- Feature: `update()` with an unknown permission name throws a validation error and changes nothing.
- Feature: `restoreDefaults()` after changes gives Staff and Worker exactly the PRD table.
- Feature: one save that changes Staff writes one `activity_log` row with `causer_id` = the admin and properties `old` and `new`.
- Feature: "Save changes" is disabled with no changes, enabled after ticking a box, and `discard()` restores the saved state.
- Feature: a staff user calling the component's `save()` directly gets 403.

**Verify:** log in as Admin → Admin → Roles & Permissions; tick "Manage materials" for Worker and save; log in as a worker in another browser and see Materials in the menu; untick, save, refresh and confirm it is gone; check a phone width of 375 px.

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
