# T-12 – Attendance corrections


**Goal:** the Admin can fix mistakes, and every fix recalculates labor cost and records who changed it and why.

**Depends on:** T-11

**Build**

- `App\Services\AttendanceCorrectionService` with:
  - `setClockOut(Attendance $a, Carbon $clockOut, string $reason, User $admin)`
  - `changeTimes(Attendance $a, Carbon $in, Carbon $out, string $reason, User $admin)`
  - `changeProject(Attendance $a, Project $project, string $reason, User $admin)`
  - `split(Attendance $a, Carbon $at, string $reason, User $admin)` — ends the session at `$at` and creates a second session from `$at` to the original clock-out with the same project and rate.
  - `void(Attendance $a, string $reason, User $admin)`
- Every change: recompute minutes, hours and labor cost with `LaborCost` using the session's saved `hourly_rate`; set `status = adjusted` (or `void`), `adjusted_by` and `adjustment_reason`.
- Validation: reason required (at least 10 characters); clock-out after clock-in; no time in the future; no overlap with the same user's other non-void sessions.
- UI: a "Correct" button on each `/attendance` row (permission `attendance.correct`, behind `password.confirm`) opening a modal with the five actions.

**Acceptance criteria**

- [ ] Moving a session from Project A to B moves its labor cost between the two project totals.
- [ ] A voided session no longer counts toward any total.
- [ ] Staff and workers cannot open the correction modal or call the actions.

**Tests**

- Feature: setting clock-out 11:30 on a flagged session that started at 7:00 → ₱360.00 at ₱80/hr, status adjusted.
- Feature: change project moves ₱360.00 from Project A's total to Project B's.
- Feature: split a 7:00–17:00 session at 12:00 → two sessions of 300 minutes each.
- Feature: missing reason fails validation; overlapping times fail validation.
- Feature: void excludes the session from `laborTotal()`.
- Feature: staff calling the Livewire action gets 403.

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
