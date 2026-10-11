# T-08 – Materials masterfile


**Goal:** Admin and Staff maintain a list of materials with units and default prices for faster material entry.

**Depends on:** T-05

**Build**

- Migration `materials`: `code` (string 30, unique), `name`, `unit`, `default_price` (decimal 12,2), `status` (enum `active`/`inactive`), timestamps, soft deletes.
- Units offered in a dropdown: pcs, bags, cu.m, kg, m, L, sheets, sets.
- `Material` model with `scopeActive()` and a factory.
- `/materials` (permission `materials.manage`): list with search, create, edit, deactivate, archive.
- Validation: code required and unique; default price 0 or more.

**Acceptance criteria**

- [ ] Staff and Admin can create "Hollow blocks 4"" with unit pcs and default price ₱30.00.
- [ ] Inactive materials do not appear when recording material entries (checked in T-15).
- [ ] Workers get 403.

**Tests**

- Feature: staff creates a material; it appears in the list.
- Feature: duplicate code fails validation; negative price fails validation.
- Feature: worker gets 403 on `/materials`.

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
