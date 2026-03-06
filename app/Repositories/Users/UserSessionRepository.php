<?php

namespace App\Repositories\Users;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provides read access to user online presence derived from sessions table.
 */
class UserSessionRepository
{
    /**
     * Determine whether active users can be resolved from sessions table.
     *
     * Logic:
     * 1) Require `database` session driver.
     * 2) Require sessions table availability.
     *
     * @return bool
     */
    public function canResolveActiveUsers(): bool
    {
        return config('session.driver') === 'database' && Schema::hasTable('sessions');
    }

    /**
     * Resolve unique user IDs with active sessions.
     *
     * Logic:
     * 1) Return empty collection when session resolution is unavailable.
     * 2) Query sessions newer than configured activity threshold.
     * 3) Normalize IDs to unique integers.
     *
     * @return Collection<int, int>
     */
    public function activeUserIds(): Collection
    {
        if (! $this->canResolveActiveUsers()) {
            return collect();
        }

        return DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $this->minimumLastActivityTimestamp())
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Determine whether a user currently has an active session.
     *
     * Logic:
     * 1) Require database session driver and sessions table availability.
     * 2) Compute minimum activity threshold from configured session lifetime.
     * 3) Return true when an active session row exists for the user.
     *
     * @param  int  $userId
     * @return bool
     */
    public function isUserOnline(int $userId): bool
    {
        if (! $this->canResolveActiveUsers()) {
            return false;
        }

        return DB::table('sessions')
            ->where('user_id', $userId)
            ->where('last_activity', '>=', $this->minimumLastActivityTimestamp())
            ->exists();
    }

    /**
     * Compute lower bound timestamp for active session filtering.
     *
     * Logic:
     * 1) Read session lifetime from configuration.
     * 2) Convert threshold to unix timestamp.
     *
     * @return int
     */
    private function minimumLastActivityTimestamp(): int
    {
        return now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp();
    }
}
