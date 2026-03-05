<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UsersIndexRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\ResolveAuthenticatedUserService;
use App\Services\Users\ListUsersService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Handles API endpoints related to user listing and presence data.
 */
class UserController extends Controller
{
    /**
     * Return a filtered list of users with computed presence state.
     *
     * Logic:
     * 1) Resolve the authenticated user from the current request context.
     * 2) Validate and read query filters from the form request.
     * 3) Delegate user retrieval and online-state mapping to the users service.
     * 4) Transform the result through `UserResource`.
     * 5) Return a standardized API success envelope.
     *
     * @param  UsersIndexRequest  $request
     * @param  ListUsersService  $listUsersService
     * @param  ResolveAuthenticatedUserService  $resolveAuthenticatedUserService
     * @return JsonResponse
     */
    public function index(
        UsersIndexRequest $request,
        ListUsersService $listUsersService,
        ResolveAuthenticatedUserService $resolveAuthenticatedUserService,
    ): JsonResponse {
        $authenticatedUser = $resolveAuthenticatedUserService->handle($request);
        $usersWithState = $listUsersService->handle($request->validated(), $authenticatedUser);

        return ApiResponse::success('Users retrieved successfully.', [
            'users' => UserResource::collection($usersWithState)->resolve($request),
        ]);
    }
}
