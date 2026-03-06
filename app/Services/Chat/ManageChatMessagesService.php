<?php

namespace App\Services\Chat;

use App\Events\ChatMessageSent;
use App\Events\ChatMessagesRead;
use App\Models\ChatMessage;
use App\Models\User;
use App\Repositories\Chat\ChatMessageRepository;
use App\Repositories\Users\UserSessionRepository;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Encapsulates direct chat message persistence and retrieval rules.
 */
class ManageChatMessagesService
{
    /**
     * Create a new service instance.
     *
     * Logic:
     * 1) Inject chat message repository for direct message persistence/querying.
     * 2) Inject user session repository for online-presence checks.
     *
     * @param  ChatMessageRepository  $chatMessageRepository
     * @param  UserSessionRepository  $userSessionRepository
     * @return void
     */
    public function __construct(
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly UserSessionRepository $userSessionRepository,
    ) {}

    /**
     * Persist and broadcast a direct chat message.
     *
     * Logic:
     * 1) Reject self-targeted messages.
     * 2) Ensure the recipient is online.
     * 3) Persist the message row as unread for recipient.
     * 4) Broadcast realtime event to recipient channel.
     *
     * @param  User  $fromUser
     * @param  int  $toUserId
     * @param  string  $message
     * @return ChatMessage
     */
    public function send(User $fromUser, int $toUserId, string $message): ChatMessage
    {
        $this->ensureDifferentUsers(
            fromUserId: (int) $fromUser->id,
            toUserId: $toUserId,
            errorMessage: 'You cannot send a message to yourself.',
        );

        if (! $this->userSessionRepository->isUserOnline($toUserId)) {
            throw new InvalidArgumentException('You can only send messages to online users.');
        }

        $chatMessage = $this->chatMessageRepository->create($fromUser, $toUserId, $message);

        event(new ChatMessageSent(
            chat_message_id: (int) $chatMessage->id,
            to_user_id: $toUserId,
            from_user_id: (int) $fromUser->id,
            from_user_name: (string) $fromUser->name,
            message: $message,
            created_at: (string) $chatMessage->created_at,
        ));

        return $chatMessage;
    }

    /**
     * Fetch a conversation between the authenticated user and another user.
     *
     * Logic:
     * 1) Select messages where participants match either direction.
     * 2) Order by creation timestamp ascending.
     * 3) Eager-load sender relation used by API resource.
     *
     * @param  User  $authenticatedUser
     * @param  User  $otherUser
     * @param  int  $limit
     * @return Collection<int, ChatMessage>
     */
    public function conversation(User $authenticatedUser, User $otherUser, int $limit = 200): Collection
    {
        return $this->chatMessageRepository->conversation($authenticatedUser, $otherUser, $limit);
    }

    /**
     * Return unread incoming message counters grouped by sender.
     *
     * Logic:
     * 1) Select unread rows targeted to authenticated user.
     * 2) Group by sender user ID.
     * 3) Return sender/count pairs for API projection.
     *
     * @param  User  $authenticatedUser
     * @return array<int, array<string, int>>
     */
    public function unreadCounts(User $authenticatedUser): array
    {
        return $this->chatMessageRepository->unreadCounts($authenticatedUser);
    }

    /**
     * Mark unread incoming messages from one user as read.
     *
     * Logic:
     * 1) Scope to unread messages from other user to authenticated user.
     * 2) Update `read_at` with current timestamp.
     * 3) Return number of updated rows.
     *
     * @param  User  $authenticatedUser
     * @param  User  $otherUser
     * @return int
     */
    public function markConversationAsRead(User $authenticatedUser, User $otherUser): int
    {
        return $this->chatMessageRepository->markConversationAsRead($authenticatedUser, $otherUser);
    }

    /**
     * Mark unread messages as read and broadcast read receipt to original sender.
     *
     * Logic:
     * 1) Collect unread incoming message IDs before updating timestamps.
     * 2) Return early when there is nothing new to mark as read.
     * 3) Mark unread messages as read in persistence layer.
     * 4) Broadcast read-receipt event back to original sender.
     * 5) Return update summary for controller/API response usage.
     *
     * @param  User  $authenticatedUser
     * @param  User  $otherUser
     * @return array{updated_count:int,message_ids:array<int,int>,read_at:string|null}
     */
    public function markConversationAsReadWithReceipt(User $authenticatedUser, User $otherUser): array
    {
        $messageIds = $this->chatMessageRepository->unreadIncomingMessageIds($authenticatedUser, $otherUser);

        if (count($messageIds) === 0) {
            return [
                'updated_count' => 0,
                'message_ids' => [],
                'read_at' => null,
            ];
        }

        $updatedCount = $this->chatMessageRepository->markConversationAsRead($authenticatedUser, $otherUser);
        $readAt = now()->toISOString();

        event(new ChatMessagesRead(
            notify_user_id: (int) $otherUser->id,
            reader_user_id: (int) $authenticatedUser->id,
            message_ids: $messageIds,
            read_at: $readAt,
        ));

        return [
            'updated_count' => $updatedCount,
            'message_ids' => $messageIds,
            'read_at' => $readAt,
        ];
    }

    /**
     * Validate that source and destination users are different.
     *
     * Logic:
     * 1) Compare sender and recipient IDs.
     * 2) Throw a domain validation exception when IDs match.
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
}
