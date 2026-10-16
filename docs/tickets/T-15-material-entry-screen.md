# T-15 – Material entry screen


**Goal:** Staff record material purchases per project and see the 20% markup and total before saving.

**Depends on:** T-14

**Build**

- `/material-entries` (permission `material-entries.create`): list with filters for project, material and date range; columns date, project, material, quantity + unit, unit price, subtotal, markup, total, entered by; totals row; paginated with rows per page (10 / 25 / 50 / 100, default 10).
- Create form: entry date (default today, no future dates), project (Ongoing and On hold only), material (active only; selecting it fills unit price with the default price, still editable), quantity (more than 0), unit price (0 or more), remarks.
- Live preview below the form, computed on the server with `MaterialCost`: subtotal, markup (20%), total.
- Editing and archiving: Admin can change any entry; Staff can change only their own entries made today.

**Acceptance criteria**

- [ ] Entering hollow blocks, 100 pcs at ₱30.00 previews ₱3,000.00 / ₱600.00 / ₱3,600.00, and saves the same.
- [ ] Inactive materials and Completed projects cannot be chosen.
- [ ] Workers get 403.

**Tests**

- Feature (Livewire): preview shows the three amounts for 100 × ₱30.00.
- Feature: saving creates an entry with `entered_by` = the current user.
- Feature: quantity 0 and a future date fail validation.
- Feature: inactive material ID submitted directly fails validation.
- Feature: staff cannot edit another user's entry or their own entry from yesterday; admin can.
- Feature: worker gets 403 on `/material-entries`.

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
