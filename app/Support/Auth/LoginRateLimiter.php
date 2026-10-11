<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;
use Laravel\Fortify\LoginRateLimiter as FortifyLoginRateLimiter;

/**
 * Locks a username + IP for 15 minutes after 5 failed logins (PRD section 8).
 * Fortify's default only blocks for one minute.
 */
class LoginRateLimiter extends FortifyLoginRateLimiter
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_SECONDS = 15 * 60;

    public function tooManyAttempts(Request $request)
    {
        return $this->limiter->attempts($this->lockKey($request)) > 0;
    }

    public function increment(Request $request)
    {
        $key = $this->throttleKey($request);

        $this->limiter->hit($key, self::LOCK_SECONDS);

        if ($this->limiter->attempts($key) >= self::MAX_ATTEMPTS) {
            // Start the lock at the 5th failure so it lasts the full 15 minutes.
            $this->limiter->hit($this->lockKey($request), self::LOCK_SECONDS);
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
