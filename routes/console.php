<?php

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

Schedule::command('devices:prune-pending')->daily();
