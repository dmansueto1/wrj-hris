# T-03 – Users schema, rate history and admin seeder


**Goal:** users carry employee number, position, hourly rate and status, and every rate change is saved to a history.

**Depends on:** T-02

**Build**

- Migration on `users`: add `employee_no` (string 30, unique), `position` (nullable), `hourly_rate` (decimal 10,2, default 0), `status` (enum `active`/`inactive`, default `active`), `must_change_password` (boolean, default false), `deleted_at`; make `email` nullable.
- Migration `user_rate_histories`: `user_id` (FK), `hourly_rate` (decimal 10,2), `effective_from` (date), `changed_by` (nullable FK to users), timestamps.
- `User` model: `SoftDeletes`, casts (`hourly_rate` → `decimal:2`), `rateHistories()` relation, `scopeActive()`.
- `App\Services\UserRateService::changeRate(User $user, string $newRate, User $changedBy): void` — in one transaction, updates `hourly_rate` and adds a history row dated today. Does nothing if the rate is unchanged.
- Creating a user also writes the first history row.
- `UserFactory` states: `admin()`, `staff()`, `worker()`, `inactive()`.
- `AdminUserSeeder`: creates employee no. `ADM-0001` using `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env`, role `admin`.

**Acceptance criteria**

- [ ] `php artisan migrate:fresh --seed` creates the roles and one admin who can log in.
- [ ] Changing a rate updates the user and adds exactly one history row.
- [ ] A user can be saved without an email.

**Tests**

- Feature: `changeRate` from 80.00 to 90.00 updates the user and adds one history row with `changed_by` set.
- Feature: `changeRate` to the same rate adds no history row.
- Feature: duplicate `employee_no` is rejected by the database.
- Feature: soft-deleted user still exists with `withTrashed()`.

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
