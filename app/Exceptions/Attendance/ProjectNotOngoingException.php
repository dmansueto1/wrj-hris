<?php

namespace App\Exceptions\Attendance;

class ProjectNotOngoingException extends AttendanceException
{
    public function __construct()
    {
        parent::__construct(__('You can only clock in to an Ongoing project.'));
    }
}
