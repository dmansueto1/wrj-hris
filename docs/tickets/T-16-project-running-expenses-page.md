# T-16 – Project running expenses page


**Goal:** for any project, Admin and Staff see labor, materials and running expenses, with breakdowns and budget usage.

**Depends on:** T-10, T-15

**Build**

- `App\Services\ProjectExpenseService` with aggregate queries (SUM and GROUP BY in SQL, never loading every row): totals, labor by worker, labor by date, materials by item, materials by date — each accepting an optional date range.
- `/projects/{project}` (permission `expenses.view`): summary cards for labor total, materials total and running expenses; if a budget is set, a progress bar showing percentage used (amber at 80% or more, red at 100% or more).
- Tabs: Labor by worker (name, hours, cost), Labor by date, Materials by item (quantity, unit, total), Materials by date.
- A date range filter applies to the cards and all tabs.
- Add a "Running expenses" column to the `/projects` list.
- Only `closed` and `adjusted` sessions count; open, flagged and void sessions are excluded.

**Acceptance criteria**

- [ ] A project with ₱360.00 labor and ₱3,600.00 materials shows running expenses ₱3,960.00.
- [ ] With a budget of ₱10,000.00, the bar shows 39.6% used.
- [ ] Workers get 403.

**Tests**

- Feature: running expenses equal labor + materials exactly.
- Feature: open, flagged and void sessions are not included in labor.
- Feature: budget percentage is 39.6 for ₱3,960.00 of ₱10,000.00.
- Feature: labor by worker shows the right hours and cost per worker.
- Feature: date filter changes the totals.
- Feature: worker gets 403 on `/projects/{project}`.

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
- [ ] Committed with the ticket ID and merged to `main`
