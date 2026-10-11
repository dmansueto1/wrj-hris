# T-19 – Demo data, QA and release readiness


**Goal:** realistic demo data for the client walkthrough, a fully green test suite, and a checked production setup.

**Depends on:** all previous tickets

**Build**

- `DemoDataSeeder` (refuses to run when `APP_ENV=production`): 1 admin, 2 staff, 10 workers with rates from ₱70 to ₱120, 3 Ongoing projects and 1 Completed, 15 materials, 2 weeks of morning and afternoon sessions (some workers switching projects mid-day), material entries on each project, and one flagged session.
- Coverage: install PCOV (or use Xdebug) and run `php artisan test --coverage --min=70`.
- Smoke test: as Admin, every main page returns 200.
- Production checklist in `README.md`: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `php artisan migrate --force`, `php artisan optimize`, `npm run build`, cron `* * * * * php artisan schedule:run`, daily database backup, default admin password changed.

**Acceptance criteria**

- [ ] `php artisan migrate:fresh --seed --seeder=DemoDataSeeder` builds a complete demo in one command.
- [ ] All tests pass and coverage meets PRD section 10.1 (at least 90% for `app/Support/Costing` and `app/Services`, 70% overall).
- [ ] Manual UAT passes: on a phone, a worker clocks in to Project A, out for lunch, in to Project B, out; Staff record hollow blocks 100 × ₱30; Admin corrects a flagged session; project pages and exports show the expected amounts.

**Tests**

- Feature: `DemoDataSeeder` runs on a fresh database without errors.
- Feature: `DemoDataSeeder` throws when the environment is production.
- Feature (dataset of routes): admin gets 200 on `/clock`, `/attendance`, `/attendance/mine`, `/projects`, `/materials`, `/material-entries`, `/users`, `/reports`.

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
