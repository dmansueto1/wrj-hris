<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public const CHANGE_PASSWORD_COMPONENT = 'auth.change-password';

    /**
     * Send users with a temporary password to the change-password screen until they set a new one.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs('password.change', 'logout') || $this->isChangePasswordComponentRequest($request)) {
            return $next($request);
        }

        if (Livewire::isLivewireRequest()) {
            abort(403, __('You must change your password first.'));
        }

        return redirect()->route('password.change');
    }

    /**
     * Allow Livewire requests only when every component in them is the change-password page.
     */
    private function isChangePasswordComponentRequest(Request $request): bool
    {
        if (! Livewire::isLivewireRequest()) {
            return false;
        }

        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            $snapshot = is_array($component) && is_string($component['snapshot'] ?? null)
                ? json_decode($component['snapshot'], true)
                : null;

            if (! is_array($snapshot) || ($snapshot['memo']['name'] ?? null) !== self::CHANGE_PASSWORD_COMPONENT) {
                return false;
            }
        }

        return true;
    }
}
