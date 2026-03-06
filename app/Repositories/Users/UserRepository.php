<?php

namespace App\Repositories\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Handles user read queries used by API directory endpoints.
 */
class UserRepository
{
    /**
     * Return users for directory listing with optional search filter.
     *
     * Logic:
     * 1) Select only identity fields needed by API response.
     * 2) Apply optional search across name and email.
     * 3) Sort users by name.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, User>
     */
    public function directory(array $filters = []): Collection
    {
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
            ->get();
    }
}
