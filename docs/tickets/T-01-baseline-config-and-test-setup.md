# T-01 – Baseline config and test setup


**Goal:** the app runs on MySQL in Philippine time, has no public registration or welcome page, and has a working test suite on its own test database.

**Depends on:** nothing

**Build**

- `.env`: `APP_NAME="WRJ HRIS"`, `APP_URL=http://wrj-hris.test`, MySQL database `wrj_hris` with user `wrj_hris`.
- `config/app.php`: `'timezone' => 'Asia/Manila'`.
- Create a MySQL test database `wrj_hris_test` and grant the `wrj_hris` user access.
- `phpunit.xml`: set `DB_CONNECTION=mysql`, `DB_DATABASE=wrj_hris_test`.
- `tests/Pest.php`: apply `RefreshDatabase` to all Feature tests.
- Routes: `/` redirects guests to `/login` and signed-in users to `/clock`. Remove the default welcome page.
- Make sure no registration route, view or "Register" link exists.

**Acceptance criteria**

- [ ] `php artisan test` passes on a fresh clone.
- [ ] `/register` returns 404.
- [ ] Visiting `/` while logged out lands on the login page.

**Tests**

- Feature: `GET /register` returns 404.
- Feature: guest `GET /` redirects to `/login`.
- Unit: `config('app.timezone')` equals `Asia/Manila`.

**Verify:** `php artisan test` and `php artisan route:list | grep register` (should print nothing).

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
