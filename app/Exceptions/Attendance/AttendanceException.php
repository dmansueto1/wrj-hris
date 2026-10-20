<?php

namespace App\Exceptions\Attendance;

use DomainException;

/**
 * Base class for clock in/out rule violations. The message is safe to show to the user.
 */
abstract class AttendanceException extends DomainException {}
