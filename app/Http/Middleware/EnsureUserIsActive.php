<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Log out a user who was deactivated while signed in (including via "Remember me").
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->status === User::STATUS_ACTIVE) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Livewire shows its "page expired" prompt for 419; reloading then lands on the login page.
        if (Livewire::isLivewireRequest()) {
            abort(419);
        }

        return redirect()->route('login')->withErrors(['username' => __('auth.inactive')]);
    }
}
