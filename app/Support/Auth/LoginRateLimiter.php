<?php

namespace App\Support\Auth;

use App\Support\Settings;
use Illuminate\Http\Request;
use Laravel\Fortify\LoginRateLimiter as FortifyLoginRateLimiter;

/**
 * Locks a username + IP after a number of failed logins, for a number of minutes, both from
 * System Settings (5 and 15 by default, PRD section 8). Fortify's default only blocks for one minute.
 */
class LoginRateLimiter extends FortifyLoginRateLimiter
{
    public function tooManyAttempts(Request $request)
    {
        return $this->limiter->attempts($this->lockKey($request)) > 0;
    }

    public function increment(Request $request)
    {
        $key = $this->throttleKey($request);
        $lockSeconds = Settings::int('login_lockout_minutes') * 60;

        $this->limiter->hit($key, $lockSeconds);

        if ($this->limiter->attempts($key) >= Settings::int('login_max_attempts')) {
            // Start the lock at the last allowed failure so it lasts the full time.
            $this->limiter->hit($this->lockKey($request), $lockSeconds);
            $this->limiter->clear($key);
        }
    }

    public function availableIn(Request $request)
    {
        return $this->limiter->availableIn($this->lockKey($request));
    }

    public function clear(Request $request)
    {
        $this->limiter->clear($this->throttleKey($request));
        $this->limiter->clear($this->lockKey($request));
    }

    private function lockKey(Request $request): string
    {
        return $this->throttleKey($request).':lock';
    }
}
