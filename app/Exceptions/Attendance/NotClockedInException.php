<?php

namespace App\Exceptions\Attendance;

class NotClockedInException extends AttendanceException
{
    public function __construct()
    {
        parent::__construct(__('You are not clocked in.'));
    }
}
