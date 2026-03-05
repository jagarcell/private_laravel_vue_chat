<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Stores and manages user IDs currently considered online for presence sync.
 *
 * The store persists online IDs in cache so login/logout and scheduled
 * session-expiry sync logic can share a consistent presence snapshot.
 */
class OnlineUsersStore
{
    /**
     * Cache key used to persist online user IDs.
     */
    private const CACHE_KEY = 'presence:online_user_ids';

    /**
     * Get all tracked online user IDs.
        *
        * Logic:
        * 1) Read cached value from the configured cache store.
        * 2) Cast IDs to integers.
        * 3) Remove duplicates and normalize indexes.
     *
     * @return Collection<int, int>
     */
    public function all(): Collection
    {
        return collect(Cache::get(self::CACHE_KEY, []))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Persist a full set of tracked online user IDs.
     *
     * Logic:
     * 1) Normalize input IDs to integers.
     * 2) Remove duplicates and reset indexes.
     * 3) Store the normalized ID list in cache using a stable key.
     *
     * @param  Collection<int, int>|array<int, int>  $userIds
     * @return void
     */
    public function put(Collection|array $userIds): void
    {
        Cache::forever(
            self::CACHE_KEY,
            collect($userIds)
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all()
        );
    }

    /**
     * Mark a user as online in the tracked set.
     *
     * Logic:
     * 1) Load current tracked online IDs.
     * 2) Add the provided user ID.
     * 3) Persist through `put()` for normalization and deduplication.
     *
     * @param  int  $userId
     * @return void
     */
    public function markOnline(int $userId): void
    {
        $this->put($this->all()->push($userId));
    }

    /**
     * Mark a user as offline in the tracked set.
     *
     * Logic:
     * 1) Load current tracked online IDs.
     * 2) Remove entries matching the provided user ID.
     * 3) Persist the updated set through `put()`.
     *
     * @param  int  $userId
     * @return void
     */
    public function markOffline(int $userId): void
    {
        $this->put($this->all()->reject(fn (int $id): bool => $id === $userId));
    }
}
