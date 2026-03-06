<?php

namespace App\Repositories\Chat;

use App\Support\ActiveChatConnectionsStore;
use Illuminate\Support\Collection;

/**
 * Encapsulates active chat-connection persistence operations.
 */
class ActiveChatConnectionRepository
{
    /**
     * Create a new repository instance.
    *
    * Logic:
    * 1) Inject active chat-connection store abstraction.
    * 2) Delegate chat-connection persistence operations through this repository.
     *
     * @param  ActiveChatConnectionsStore  $activeChatConnectionsStore
     * @return void
     */
    public function __construct(private readonly ActiveChatConnectionsStore $activeChatConnectionsStore) {}

    /**
     * Connect two users bidirectionally.
        *
        * Logic:
        * 1) Accept two participant IDs.
        * 2) Delegate bidirectional connection persistence to the store.
     *
     * @param  int  $firstUserId
     * @param  int  $secondUserId
     * @return void
     */
    public function connectBidirectional(int $firstUserId, int $secondUserId): void
    {
        $this->activeChatConnectionsStore->connectBidirectional($firstUserId, $secondUserId);
    }

    /**
     * Disconnect two users bidirectionally.
        *
        * Logic:
        * 1) Accept two participant IDs.
        * 2) Delegate bidirectional disconnection persistence to the store.
     *
     * @param  int  $firstUserId
     * @param  int  $secondUserId
     * @return void
     */
    public function disconnectBidirectional(int $firstUserId, int $secondUserId): void
    {
        $this->activeChatConnectionsStore->disconnectBidirectional($firstUserId, $secondUserId);
    }

    /**
     * Disconnect all peers for one user.
        *
        * Logic:
        * 1) Resolve all currently connected peer IDs for the given user.
        * 2) Remove all those connections from persisted state.
        * 3) Return the list of disconnected peer IDs.
     *
     * @param  int  $userId
     * @return array<int, int>
     */
    public function disconnectAllForUser(int $userId): array
    {
        return $this->activeChatConnectionsStore->disconnectAllForUser($userId);
    }

    /**
     * Return connected peer IDs for one user.
        *
        * Logic:
        * 1) Load persisted connection state for the provided user ID.
        * 2) Normalize and return the connected peer IDs collection.
     *
     * @param  int  $userId
     * @return Collection<int, int>
     */
    public function connectedUserIds(int $userId): Collection
    {
        return $this->activeChatConnectionsStore->connectedUserIds($userId);
    }
}
