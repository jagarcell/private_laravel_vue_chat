<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolves the authenticated user from the current HTTP request context.
 */
class ResolveAuthenticatedUserService
{
    /**
     * Resolve and return the authenticated user instance.
     *
     * Logic:
     * 1) Read the authenticated principal from the request.
     * 2) Assert/cast the value to the application's `User` model.
     * 3) Return the resolved user for downstream service/controller use.
     *
     * @param  Request  $request
     * @return User
     */
    public function handle(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
