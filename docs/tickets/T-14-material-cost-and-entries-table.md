# T-14 – Material cost and entries table


**Goal:** a tested calculation for material cost with markup, and material entries that store their amounts and markup rate permanently.

**Depends on:** T-07, T-08

**Build**

- Migration `settings` (`key` string primary, `value` string) and `App\Support\Settings::get(string $key, ?string $default = null)`; seed `material_markup_rate = 0.20`.
- `App\Support\Costing\MaterialCost::compute(string $unitPrice, string $quantity, string $markupRate): array` returning `subtotal`, `markup`, `total` as 2-decimal strings.
  - Work in integers: price in centavos, quantity in hundredths, markup rate in ten-thousandths.
  - `subtotal = intdiv(price_centavos × qty_hundredths + 50, 100)`; `markup = intdiv(subtotal × rate_ten_thousandths + 5000, 10000)`; `total = subtotal + markup`.
- Migration `project_material_entries`: `project_id`, `material_id` (FKs), `entry_date` (date), `quantity` (decimal 12,2), `unit_price` (decimal 12,2), `markup_rate` (decimal 5,4), `subtotal`, `markup_amount`, `total_cost` (decimal 14,2), `remarks` (nullable), `entered_by` (FK users), timestamps, soft deletes. Index `(project_id, entry_date)`.
- `ProjectMaterialEntry` model: on `saving`, if `markup_rate` is empty copy it from the setting, then fill `subtotal`, `markup_amount` and `total_cost` from `MaterialCost`.
- `Project::materialsTotal(): string` and `Project::runningExpenses(): string` (labor total + materials total, added in centavos).

**Acceptance criteria**

- [ ] All PRD section 10.3 material cases pass.
- [ ] Changing the markup setting to 0.25 does not change existing entries.

**Tests**

- Unit (data provider): `MaterialCost::compute` — 30.00 × 100 @ 0.20 → 3,000.00 / 600.00 / 3,600.00; 12.50 × 3.5 → 43.75 / 8.75 / 52.50; 0.33 × 3 → 0.99 / 0.20 / 1.19.
- Feature: saving an entry fills subtotal, markup and total.
- Feature: entry saved at 0.20, setting changed to 0.25, entry still totals ₱3,600.00; a new entry uses 0.25.
- Feature: a project with ₱360.00 labor and ₱3,600.00 materials has `runningExpenses()` = ₱3,960.00.

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
