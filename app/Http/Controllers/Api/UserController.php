<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UsersIndexRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\ResolveAuthenticatedUserService;
use App\Services\Users\ListUsersService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(
        UsersIndexRequest $request,
        ListUsersService $listUsersService,
        ResolveAuthenticatedUserService $resolveAuthenticatedUserService,
    ): JsonResponse {
        $authenticatedUser = $resolveAuthenticatedUserService->handle($request);
        $usersWithState = $listUsersService->handle($request->validated(), $authenticatedUser);

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully.',
            'data' => [
                'users' => UserResource::collection($usersWithState)->resolve($request),
            ],
        ], 200);
    }
}
