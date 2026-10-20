<?php

namespace App\Exceptions\Attendance;

class InactiveUserException extends AttendanceException
{
    public function __construct()
    {
        parent::__construct(__('Your account is inactive. Please contact the administrator.'));
    }
}
