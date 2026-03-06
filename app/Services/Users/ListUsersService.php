<?php

namespace App\Services\Users;

use App\Models\User;
use App\Repositories\Users\UserRepository;
use App\Repositories\Users\UserSessionRepository;
use Illuminate\Support\Collection;

/**
 * Retrieves users with optional filters and computed online presence state.
 */
class ListUsersService
{
    /**
     * Create a new service instance.
     *
     * Logic:
     * 1) Inject user repository for directory queries.
     * 2) Inject session repository for online presence resolution.
     *
     * @param  UserRepository  $userRepository
     * @param  UserSessionRepository  $userSessionRepository
     * @return void
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserSessionRepository $userSessionRepository,
    ) {}

    /**
     * Build the users list payload for API responses.
     *
     * Logic:
     * 1) Resolve currently online user IDs from active sessions.
     * 2) Query users selecting identity fields.
     * 3) Apply optional text search on name/email.
     * 4) Sort by name.
     * 5) Map each user into the API shape including computed `is_online`.
     *
     * @param  array<string, mixed>  $filters
     * @param  User  $authenticatedUser
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(array $filters = [], User $authenticatedUser): Collection
    {
        $onlineUserIds = $this->resolveOnlineUserIds($authenticatedUser);

        return $this->userRepository->directory($filters)
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_online' => $onlineUserIds->contains($user->id),
            ]);
    }

    /**
        * Resolve unique IDs of users considered online based on session activity.
        *
        * Logic:
        * 1) If database sessions are enabled and the table exists, query active sessions
        *    using the configured session lifetime threshold.
        * 2) Normalize returned IDs to unique integers.
        * 3) Ensure the currently authenticated user is included.
        *
        * @param  User  $authenticatedUser
     * @return Collection<int, int>
     */
    private function resolveOnlineUserIds(User $authenticatedUser): Collection
    {
        $onlineUserIds = $this->userSessionRepository->activeUserIds();

        return $onlineUserIds
            ->push($authenticatedUser->id)
            ->unique()
            ->values();
    }
}
