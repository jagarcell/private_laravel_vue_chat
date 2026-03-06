<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Stores active direct-chat connections for users.
 */
class ActiveChatConnectionsStore
{
    /**
     * Cache key used to persist active chat connection map.
     */
    private const CACHE_KEY = 'chat:active_connections_by_user';

    /**
     * Get connected user IDs for one user.
     *
     * Logic:
     * 1) Read cached connection map.
     * 2) Resolve list under the provided user ID key.
     * 3) Normalize to unique integer IDs.
     *
     * @param  int  $userId
     * @return Collection<int, int>
     */
    public function connectedUserIds(int $userId): Collection
    {
        $map = $this->all();

        return collect($map[$userId] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
    }

    /**
     * Connect two users bidirectionally in the active map.
     *
     * Logic:
     * 1) Ignore self-connections.
     * 2) Append each user to the other user's list.
     * 3) Persist normalized map to cache.
     *
     * @param  int  $firstUserId
     * @param  int  $secondUserId
     * @return void
     */
    public function connectBidirectional(int $firstUserId, int $secondUserId): void
    {
        if ($firstUserId === $secondUserId) {
            return;
        }

        $map = $this->all();

        $map[$firstUserId] = $this->normalizedIds([...(array) ($map[$firstUserId] ?? []), $secondUserId]);
        $map[$secondUserId] = $this->normalizedIds([...(array) ($map[$secondUserId] ?? []), $firstUserId]);

        $this->put($map);
    }

    /**
     * Disconnect two users bidirectionally in the active map.
     *
     * Logic:
     * 1) Remove second user from first user's list.
     * 2) Remove first user from second user's list.
     * 3) Persist normalized map to cache.
     *
     * @param  int  $firstUserId
     * @param  int  $secondUserId
     * @return void
     */
    public function disconnectBidirectional(int $firstUserId, int $secondUserId): void
    {
        $map = $this->all();

        $map[$firstUserId] = $this->normalizedIds(
            collect((array) ($map[$firstUserId] ?? []))
                ->reject(fn (mixed $id): bool => (int) $id === $secondUserId)
                ->all(),
        );

        $map[$secondUserId] = $this->normalizedIds(
            collect((array) ($map[$secondUserId] ?? []))
                ->reject(fn (mixed $id): bool => (int) $id === $firstUserId)
                ->all(),
        );

        $this->put($map);
    }

    /**
     * Remove all active connections for one user and return former peers.
     *
     * Logic:
     * 1) Capture currently connected peers for the provided user.
     * 2) Remove references from each peer list.
     * 3) Clear the user's own connection list and persist.
     *
     * @param  int  $userId
     * @return array<int, int>
     */
    public function disconnectAllForUser(int $userId): array
    {
        $map = $this->all();
        $peers = $this->normalizedIds($map[$userId] ?? []);

        foreach ($peers as $peerId) {
            $map[$peerId] = $this->normalizedIds(
                collect((array) ($map[$peerId] ?? []))
                    ->reject(fn (mixed $id): bool => (int) $id === $userId)
                    ->all(),
            );
        }

        $map[$userId] = [];

        $this->put($map);

        return $peers;
    }

    /**
     * Persist full connection map in cache.
     *
     * Logic:
     * 1) Normalize all keys and value lists to integers.
     * 2) Remove duplicate and invalid IDs.
     * 3) Store final map indefinitely.
     *
     * @param  array<int|string, array<int, int|numeric-string>>  $map
     * @return void
     */
    private function put(array $map): void
    {
        $normalizedMap = [];

        foreach ($map as $userId => $peerIds) {
            $normalizedUserId = (int) $userId;

            if ($normalizedUserId <= 0) {
                continue;
            }

            $normalizedMap[$normalizedUserId] = $this->normalizedIds($peerIds);
        }

        Cache::forever(self::CACHE_KEY, $normalizedMap);
    }

    /**
     * Read the full connection map from cache.
     *
     * Logic:
     * 1) Retrieve map by stable cache key.
     * 2) Return empty map when no value exists.
     *
     * @return array<int|string, array<int, int|numeric-string>>
     */
    private function all(): array
    {
        $value = Cache::get(self::CACHE_KEY, []);

        if (! is_array($value)) {
            return [];
        }

        return $value;
    }

    /**
     * Normalize a peer-ID list.
     *
     * Logic:
     * 1) Cast values to integers.
     * 2) Keep only positive IDs.
     * 3) Remove duplicates and reset indexes.
     *
     * @param  array<int, int|numeric-string|mixed>  $ids
     * @return array<int, int>
     */
    private function normalizedIds(array $ids): array
    {
        return collect($ids)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
