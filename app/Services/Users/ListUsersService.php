<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retrieves users with optional filters and computed online presence state.
 */
class ListUsersService
{
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

        return User::query()
            ->select(['id', 'name', 'email'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where(function ($scopedQuery) use ($filters): void {
                    $search = (string) $filters['search'];

                    $scopedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
            )
            ->orderBy('name')
            ->get()
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
        $onlineUserIds = collect();

        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            $minimumLastActivity = now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp();

            $onlineUserIds = DB::table('sessions')
                ->whereNotNull('user_id')
                ->where('last_activity', '>=', $minimumLastActivity)
                ->pluck('user_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values();
        }

        return $onlineUserIds
            ->push($authenticatedUser->id)
            ->unique()
            ->values();
    }
}
