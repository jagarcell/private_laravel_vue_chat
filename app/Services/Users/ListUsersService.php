<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ListUsersService
{
    /**
     * @param  array<string, mixed>  $filters
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
