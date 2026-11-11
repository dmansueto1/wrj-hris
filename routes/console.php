<?php

use App\Services\AttendanceService;
use App\Services\DeviceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// T-11.1: archive device requests nobody approved within 30 days.
Artisan::command('devices:prune-pending', function (DeviceService $devices) {
    $this->info(trans_choice(':count pending device archived.|:count pending devices archived.', $count = $devices->prunePending(), ['count' => $count]));
})->purpose('Archive pending device requests not seen for 30 days');

// T-13: flag attendance left Clocked In for more than 12 hours, for the Admin to correct.
Artisan::command('attendance:flag-open', function (AttendanceService $attendance) {
    $this->info(trans_choice(':count attendance record flagged.|:count attendance records flagged.', $count = $attendance->flagOpen(), ['count' => $count]));
})->purpose('Flag attendance records still Clocked In more than 12 hours after clock-in');

// The server needs one cron entry for these: * * * * * php artisan schedule:run
Schedule::command('devices:prune-pending')->daily();
Schedule::command('attendance:flag-open')->hourly();
