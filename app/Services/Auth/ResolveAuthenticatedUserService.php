<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;

class ResolveAuthenticatedUserService
{
    public function handle(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
