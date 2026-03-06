<?php

namespace App\Services\Users;

use App\Events\UserOnlineStatusChanged;
use App\Repositories\Users\UserSessionRepository;
use App\Support\OnlineUsersStore;

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
     * @param  OnlineUsersStore  $onlineUsersStore  Store of user IDs currently tracked as online.
     * @param  UserSessionRepository  $userSessionRepository  Repository that resolves active users from sessions.
     * @return void
     */
    public function __construct(
        private readonly OnlineUsersStore $onlineUsersStore,
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
     * 5) Replace tracked-online store with the currently active IDs.
     *
     * @return int Number of users marked offline during this sync pass.
     */
    public function handle(): int
    {
        if (! $this->userSessionRepository->canResolveActiveUsers()) {
            return 0;
        }

        $activeUserIds = $this->userSessionRepository->activeUserIds();
        $previouslyOnlineUserIds = $this->onlineUsersStore->all();
        $expiredUserIds = $previouslyOnlineUserIds->diff($activeUserIds)->values();

        $expiredUserIds->each(function (int $userId): void {
            event(new UserOnlineStatusChanged($userId, false));
        });

        $this->onlineUsersStore->put($activeUserIds);

        return $expiredUserIds->count();
    }
}
