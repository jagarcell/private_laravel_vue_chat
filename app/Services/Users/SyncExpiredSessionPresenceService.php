<?php

namespace App\Services\Users;

use App\Events\UserOnlineStatusChanged;
use App\Support\OnlineUsersStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     * @return void
     */
    public function __construct(private readonly OnlineUsersStore $onlineUsersStore) {}

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
        if (! $this->canResolveSessionOnlineUsers()) {
            return 0;
        }

        $activeUserIds = $this->resolveActiveUserIds();
        $previouslyOnlineUserIds = $this->onlineUsersStore->all();
        $expiredUserIds = $previouslyOnlineUserIds->diff($activeUserIds)->values();

        $expiredUserIds->each(function (int $userId): void {
            event(new UserOnlineStatusChanged($userId, false));
        });

        $this->onlineUsersStore->put($activeUserIds);

        return $expiredUserIds->count();
    }

    /**
     * Determine whether online users can be resolved from the sessions table.
     *
     * The sync runs only when the session driver is `database` and the
     * `sessions` table exists in the current connection.
     *
     * @return bool
     */
    private function canResolveSessionOnlineUsers(): bool
    {
        return config('session.driver') === 'database' && Schema::hasTable('sessions');
    }

    /**
     * Resolve unique user IDs considered active based on session last activity.
     *
     * Logic:
     * 1) Compute the minimum accepted `last_activity` timestamp from session lifetime.
     * 2) Query non-null `user_id` rows from `sessions` newer than that threshold.
     * 3) Normalize IDs to integers and return a unique, re-indexed collection.
     *
     * @return Collection<int, int>
     */
    private function resolveActiveUserIds(): Collection
    {
        $minimumLastActivity = now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp();

        return DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $minimumLastActivity)
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
    }
}
