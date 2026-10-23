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

];
