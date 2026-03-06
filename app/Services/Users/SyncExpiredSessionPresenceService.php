<?php

namespace App\Services\Users;

use App\Events\UserOnlineStatusChanged;
use App\Repositories\Users\OnlineUserRepository;
use App\Repositories\Users\UserSessionRepository;

/**
 * Synchronizes persisted online presence against active database sessions.
 *
 * This service detects users whose tracked online state no longer matches
 * active sessions and emits offline events so connected clients update in realtime.
 */
class SyncExpiredSessionPresenceService
{
    /**
     * Create a new service instance.
    *
    * Logic:
    * 1) Inject online-user repository for tracked presence state.
    * 2) Inject session repository for active session resolution.
     *
     * @param  OnlineUserRepository  $onlineUserRepository  Repository of user IDs currently tracked as online.
     * @param  UserSessionRepository  $userSessionRepository  Repository that resolves active users from sessions.
     * @return void
     */
    public function __construct(
        private readonly OnlineUserRepository $onlineUserRepository,
        private readonly UserSessionRepository $userSessionRepository,
    ) {}

    /**
     * Broadcast offline updates for users whose sessions are no longer active.
     *
     * Flow:
     * 1) Ensure session-based online resolution is available.
     * 2) Load currently active user IDs from the sessions table.
     * 3) Diff tracked-online IDs against active IDs to find expired users.
     * 4) Broadcast offline presence event for each expired user.
    * 5) Replace tracked-online repository state with the currently active IDs.
     *
     * @return int Number of users marked offline during this sync pass.
     */
    public function handle(): int
    {
        if (! $this->userSessionRepository->canResolveActiveUsers()) {
            return 0;
        }

        $activeUserIds = $this->userSessionRepository->activeUserIds();
        $previouslyOnlineUserIds = $this->onlineUserRepository->all();
        $expiredUserIds = $previouslyOnlineUserIds->diff($activeUserIds)->values();

        $expiredUserIds->each(function (int $userId): void {
            event(new UserOnlineStatusChanged($userId, false));
        });

        $this->onlineUserRepository->put($activeUserIds);

        return $expiredUserIds->count();
    }
}
