# T-10 – Clock in/out screen


**Goal:** a worker on a phone can clock in to a project or clock out in under 30 seconds, and see today's sessions.

**Depends on:** T-09

**Build**

- Livewire page at `/clock` (permission `attendance.clock`), the home page for everyone.
- Not clocked in: a dropdown of Ongoing projects ("CODE · Name"; on phones the device's own picker scrolls, so many projects are fine) and a "Clock In" button.
- Clocked in: the project name, clock-in time, a running elapsed time (Alpine, updates every minute) and a large "Clock Out" button with a confirm step.
- After clock-out: a success message with start time, end time and hours.
- "Today" panel: each of today's sessions (project, in, out, hours) and the total hours for the day.
- Location: request browser geolocation on clock in/out; if denied or unavailable, continue without it.
- Buttons are full width and 48 px tall (same as the kiosk); disable them while saving (`wire:loading`).
- Show friendly messages for the service exceptions from T-09.

**Acceptance criteria**

- [ ] On a 375 px wide screen, clock in and clock out work without zooming or horizontal scrolling.
- [ ] Two sessions in one day show in the Today panel with the correct total.
- [ ] Projects that are not Ongoing are not listed.

**Tests**

- Feature (Livewire): not clocked in → sees Ongoing projects only and a Clock In button.
- Feature: selecting a project and clicking Clock In creates an open session.
- Feature: clocked in → sees Clock Out; clicking it closes the session.
- Feature: submitting a non-Ongoing project ID (tampered request) shows an error and creates nothing.
- Feature: two 270-minute sessions today → Today panel shows total 9.00 hours.

## Definition of done

- [ ] All acceptance criteria met
- [ ] All listed tests written and passing; full suite passes with `php artisan test`
- [ ] Screens work at phone width (375 px) and desktop width
- [ ] No errors in the browser console or `storage/logs/laravel.log`
- [ ] Code formatted with `./vendor/bin/pint`
