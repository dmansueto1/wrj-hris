<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Photo on clock in / clock out (T-10.2)
    |--------------------------------------------------------------------------
    |
    | When true, every clock in and clock out on /clock and /kiosk needs a photo
    | of the worker taken with the device camera. Read it through
    | App\Support\AttendancePhotos::required() so a Settings screen can take over later.
    |
    */

    'require_photo' => (bool) env('ATTENDANCE_REQUIRE_PHOTO', true),

    /*
    |--------------------------------------------------------------------------
    | Approved devices only (T-11.1)
    |--------------------------------------------------------------------------
    |
    | When true, /kiosk and the Clock In/Out page work only on devices an Admin
    | approved on the Devices page; other devices see the "Request access" screen.
    | Read it through App\Services\DeviceService::required().
    |
    */

    'require_approved_device' => (bool) env('ATTENDANCE_REQUIRE_APPROVED_DEVICE', true),

];
