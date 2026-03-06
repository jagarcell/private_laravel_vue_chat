<?php

namespace App\Repositories\Users;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provides read access to user online presence derived from sessions table.
 */
class UserSessionRepository
{
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
        if (config('session.driver') !== 'database' || ! Schema::hasTable('sessions')) {
            return false;
        }

        $minimumLastActivity = now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp();

        return DB::table('sessions')
            ->where('user_id', $userId)
            ->where('last_activity', '>=', $minimumLastActivity)
            ->exists();
    }
}
