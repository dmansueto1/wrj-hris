<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class AuthenticateUser
{
    /**
     * Find the user by username only and check the password. Inactive users are
     * told so, but only after the password is correct, so the message never
     * reveals whether an account exists.
     */
    public function __invoke(Request $request): ?User
    {
        $user = User::where('username', $request->string(Fortify::username())->lower()->value())->first();

        if (! $user || ! Hash::check($request->string('password')->value(), $user->password)) {
            return null;
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                Fortify::username() => [__('auth.inactive')],
            ]);
        }

        return $user;
    }
}
