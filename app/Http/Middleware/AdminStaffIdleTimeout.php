<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

class AdminStaffIdleTimeout
{
    public const IDLE_MINUTES = 30;

    public const SESSION_KEY = 'auth.last_activity_at';

    /**
     * Log out admin and staff after 30 minutes without a request (PRD section 8).
     * Workers keep the normal session lifetime.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->hasAnyRole(['admin', 'staff'])) {
            return $next($request);
        }

        $lastActivity = $request->session()->get(self::SESSION_KEY);

        // Once the session expires, "Remember me" logs the user back in silently with
        // no recorded activity, so treat that as idle too.
        $restoredFromRememberCookie = $lastActivity === null && Auth::guard('web')->viaRemember();
        $idleTooLong = $lastActivity !== null && now()->getTimestamp() - $lastActivity > self::IDLE_MINUTES * 60;

        if ($restoredFromRememberCookie || $idleTooLong) {
            return $this->logOut($request);
        }

        $request->session()->put(self::SESSION_KEY, now()->getTimestamp());

        return $next($request);
    }

    private function logOut(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Livewire shows its "page expired" prompt for 419; reloading then lands on the login page.
        if (Livewire::isLivewireRequest()) {
            abort(419);
        }

        return redirect()->route('login')
            ->with('status', __('You were logged out after :minutes minutes of inactivity.', ['minutes' => self::IDLE_MINUTES]));
    }
}
