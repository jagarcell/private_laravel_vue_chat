<?php

namespace App\Services\Chat;

use App\Events\ChatRequestMessage;
use App\Models\User;
use App\Support\ActiveChatConnectionsStore;
use InvalidArgumentException;

/**
 * Handles business rules and broadcasting for chat request lifecycle actions.
 */
class HandleChatRequestLifecycleService
{
    /**
     * Create a new service instance.
     *
     * Logic:
     * 1) Inject active-chat connection store.
     * 2) Reuse store to keep accept/close/logout flows in sync.
     *
     * @param  ActiveChatConnectionsStore  $activeChatConnectionsStore
     * @return void
     */
    public function __construct(private readonly ActiveChatConnectionsStore $activeChatConnectionsStore) {}

    /**
     * Process a new chat request action.
     *
     * Logic:
     * 1) Ensure source and target users are not the same.
     * 2) Broadcast a `requested` message to the target user channel.
     *
     * @param  User  $fromUser
     * @param  int  $toUserId
     * @return void
     */
    public function send(User $fromUser, int $toUserId): void
    {
        $this->ensureDifferentUsers(
            fromUserId: (int) $fromUser->id,
            toUserId: $toUserId,
            errorMessage: 'You cannot request a chat with yourself.',
        );

        $this->broadcast(
            fromUser: $fromUser,
            toUserId: $toUserId,
            type: 'requested',
        );
    }

    /**
     * Process an accept/decline response to an existing chat request.
     *
     * Logic:
     * 1) Map `action` value to message type (`accepted` or `declined`).
     * 2) Broadcast the response to the original requester channel.
     *
     * @param  User  $fromUser
     * @param  int  $requesterUserId
     * @param  string  $action
     * @return void
     */
    public function respond(User $fromUser, int $requesterUserId, string $action): void
    {
        $type = $action === 'accept' ? 'accepted' : 'declined';

        if ($type === 'accepted') {
            $this->activeChatConnectionsStore->connectBidirectional((int) $fromUser->id, $requesterUserId);
        }

        if ($type === 'declined') {
            $this->activeChatConnectionsStore->disconnectBidirectional((int) $fromUser->id, $requesterUserId);
        }

        $this->broadcast(
            fromUser: $fromUser,
            toUserId: $requesterUserId,
            type: $type,
        );
    }

    /**
     * Process a close-chat action and notify the other participant.
     *
     * Logic:
     * 1) Ensure source and target users are not the same.
     * 2) Broadcast a `closed` message to the target user channel.
     *
     * @param  User  $fromUser
     * @param  int  $toUserId
     * @return void
     */
    public function close(User $fromUser, int $toUserId): void
    {
        $this->ensureDifferentUsers(
            fromUserId: (int) $fromUser->id,
            toUserId: $toUserId,
            errorMessage: 'You cannot close chat with yourself.',
        );

        $this->activeChatConnectionsStore->disconnectBidirectional((int) $fromUser->id, $toUserId);

        $this->broadcast(
            fromUser: $fromUser,
            toUserId: $toUserId,
            type: 'closed',
        );
    }

    /**
     * Close all active chats for a user and notify connected peers.
     *
     * Logic:
     * 1) Resolve all currently connected peer user IDs from the store.
     * 2) Remove those connections from the active map.
     * 3) Broadcast `closed` event to each previously connected peer.
     *
     * @param  User  $fromUser
     * @return void
     */
    public function closeAllConnected(User $fromUser): void
    {
        $peerUserIds = $this->activeChatConnectionsStore->disconnectAllForUser((int) $fromUser->id);

        foreach ($peerUserIds as $peerUserId) {
            $this->broadcast(
                fromUser: $fromUser,
                toUserId: $peerUserId,
                type: 'closed',
            );
        }
    }

    /**
     * Validate that source and destination users are different.
     *
     * Logic:
     * 1) Compare both IDs.
     * 2) Throw an InvalidArgumentException when they are equal.
     *
     * @param  int  $fromUserId
     * @param  int  $toUserId
     * @param  string  $errorMessage
     * @return void
     */
    private function ensureDifferentUsers(int $fromUserId, int $toUserId, string $errorMessage): void
    {
        if ($fromUserId === $toUserId) {
            throw new InvalidArgumentException($errorMessage);
        }
    }

    /**
     * Broadcast a chat request lifecycle message to the target user.
     *
     * Logic:
     * 1) Build event payload from source user and target ID.
     * 2) Dispatch `ChatRequestMessage` for realtime delivery.
     *
     * @param  User  $fromUser
     * @param  int  $toUserId
     * @param  string  $type
     * @return void
     */
    private function broadcast(User $fromUser, int $toUserId, string $type): void
    {
        event(new ChatRequestMessage(
            to_user_id: $toUserId,
            from_user_id: (int) $fromUser->id,
            from_user_name: (string) $fromUser->name,
            type: $type,
        ));
    }

}
