<?php

namespace App\Exceptions\Attendance;

class AlreadyClockedInException extends AttendanceException
{
    public function __construct()
    {
        parent::__construct(__('You are already clocked in. Clock out first.'));
    }
}
