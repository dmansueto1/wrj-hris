# T-11 – Attendance history views


**Goal:** workers see their own hours; Admin and Staff see everyone's attendance with labor cost and the clock-in/out photos.

**Depends on:** T-10, T-10.2 (photo columns and the `attendance.photo` route). Attendance recorded at the kiosk (T-10.1) is stored as ordinary `attendances` rows and needs nothing extra.

**Build**

- On screen, a clock-in-to-clock-out period is an "attendance" (one attendance record), never a "session" (CLAUDE.md wording rule); "Clock In" / "Clock Out" stay for the actions.
- `/attendance/mine` (permission `attendance.view-own`): date range filter (default: current week, Monday to Sunday), attendance grouped by day with project, in, out, hours and status, plus daily and range total hours. Workers do not see peso amounts or photos.
- `/attendance` (permission `attendance.view-all`): filters for date range, user, project and status; columns date, employee no., name, project, in, out, hours, rate, labor cost, photos, status; a totals row for hours and labor cost over all filtered attendance records (not just the current page); paginated with rows per page (10 / 25 / 50 / 100, default 10).
- Photos column: small round thumbnails of the clock-in and clock-out photos loaded from `route('attendance.photo', [$attendance, 'in'|'out'])` (`loading="lazy"`); clicking a thumbnail opens it full size in a modal. An attendance record without a photo shows a muted "No photo" placeholder; a Clocked In row shows only the clock-in photo.
- Status badges with on-screen labels: open "Clocked In" blue, closed "Clocked Out" green, adjusted "Adjusted" purple, flagged "Flagged" red, void "Void" gray (struck through).

**Acceptance criteria**

- [ ] A worker only ever sees their own attendance, and never sees photos or peso amounts.
- [ ] Totals in `/attendance` match the sum of the filtered rows, excluding void attendance records.
- [ ] Admin and Staff see the clock-in and clock-out photo thumbnails for each attendance record, and can open them full size.
- [ ] No screen in this ticket shows the word "session".

**Tests**

- Feature: worker A does not see worker B's attendance records on `/attendance/mine`.
- Feature: worker gets 403 on `/attendance`; staff gets 200; admin gets 200.
- Feature: date filter excludes attendance outside the range.
- Feature: totals row equals the sum of the counted attendance records shown, across all pages of the filtered result.
- Feature: an attendance record with `clock_in_photo_path` and `clock_out_photo_path` set shows both thumbnails linking to `attendance.photo` with `in` and `out`; an attendance record without photos shows "No photo".
- Feature: `/attendance/mine` contains no `attendance.photo` links.
- Feature: open and closed attendance records show "Clocked In" and "Clocked Out"; neither page shows the text "session" (`assertDontSeeText`).

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
