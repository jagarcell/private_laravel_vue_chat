<?php

namespace App\Services\Chat;

use App\Events\ChatMessageSent;
use App\Events\ChatRequestMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Handles business rules and broadcasting for chat request lifecycle actions.
 */
class HandleChatRequestLifecycleService
{
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

        $this->broadcast(
            fromUser: $fromUser,
            toUserId: $toUserId,
            type: 'closed',
        );
    }

    /**
     * Process direct message sending to another user.
     *
     * Logic:
     * 1) Ensure source and destination users are different.
     * 2) Validate the target user is currently online.
     * 3) Broadcast the message to the target user's private message channel.
     *
     * @param  User  $fromUser
     * @param  int  $toUserId
     * @param  string  $message
     * @return void
     */
    public function sendMessage(User $fromUser, int $toUserId, string $message): void
    {
        $this->ensureDifferentUsers(
            fromUserId: (int) $fromUser->id,
            toUserId: $toUserId,
            errorMessage: 'You cannot send a message to yourself.',
        );

        if (! $this->isUserOnline($toUserId)) {
            throw new InvalidArgumentException('You can only send messages to online users.');
        }

        event(new ChatMessageSent(
            to_user_id: $toUserId,
            from_user_id: (int) $fromUser->id,
            from_user_name: (string) $fromUser->name,
            message: $message,
        ));
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

    /**
     * Determine whether a target user is currently online based on active sessions.
     *
     * Logic:
     * 1) Ensure database session driver and sessions table are available.
     * 2) Compute the minimum active timestamp from configured lifetime.
     * 3) Check for an active session row for the target user.
     *
     * @param  int  $userId
     * @return bool
     */
    private function isUserOnline(int $userId): bool
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
