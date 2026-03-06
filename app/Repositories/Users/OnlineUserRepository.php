<?php

namespace App\Repositories\Users;

use App\Support\OnlineUsersStore;
use Illuminate\Support\Collection;

/**
 * Encapsulates tracked online-user persistence operations.
 */
class OnlineUserRepository
{
    /**
     * Create a new repository instance.
    *
    * Logic:
    * 1) Inject online-user store abstraction.
    * 2) Delegate tracked presence persistence through this repository.
     *
     * @param  OnlineUsersStore  $onlineUsersStore
     * @return void
     */
    public function __construct(private readonly OnlineUsersStore $onlineUsersStore) {}

    /**
     * Return all tracked online user IDs.
        *
        * Logic:
        * 1) Read persisted online IDs from the store.
        * 2) Return normalized IDs as a collection.
     *
     * @return Collection<int, int>
     */
    public function all(): Collection
    {
        return $this->onlineUsersStore->all();
    }

    /**
     * Persist the full tracked online-user ID set.
        *
        * Logic:
        * 1) Accept collection/array input of user IDs.
        * 2) Delegate normalization and persistence to the store.
     *
     * @param  Collection<int, int>|array<int, int>  $userIds
     * @return void
     */
    public function put(Collection|array $userIds): void
    {
        $this->onlineUsersStore->put($userIds);
    }

    /**
     * Mark one user as online.
        *
        * Logic:
        * 1) Accept one user ID.
        * 2) Add the user to tracked-online state.
     *
     * @param  int  $userId
     * @return void
     */
    public function markOnline(int $userId): void
    {
        $this->onlineUsersStore->markOnline($userId);
    }

    /**
     * Mark one user as offline.
        *
        * Logic:
        * 1) Accept one user ID.
        * 2) Remove the user from tracked-online state.
     *
     * @param  int  $userId
     * @return void
     */
    public function markOffline(int $userId): void
    {
        $this->onlineUsersStore->markOffline($userId);
    }
}
