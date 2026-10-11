<?php

namespace App\Http\Responses;

use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LockoutResponse as LockoutResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

class LockoutResponse implements LockoutResponseContract
{
    public function __construct(protected LoginRateLimiter $limiter) {}

    /**
     * Show the lockout with the minutes remaining, e.g. "try again in 15 minutes".
     */
    public function toResponse($request)
    {
        $minutes = max(1, (int) ceil($this->limiter->availableIn($request) / 60));

        throw ValidationException::withMessages([
            Fortify::username() => [trans_choice('auth.lockout', $minutes, ['minutes' => $minutes])],
        ])->status(429);
    }
}
