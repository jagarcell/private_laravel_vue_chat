<?php

namespace App\Services\Chat;

use App\Events\ChatRoomMessageSent;
use App\Models\ChatRoomMessage;
use App\Models\User;
use App\Repositories\Chat\ChatRoomMessageRepository;
use App\Repositories\Chat\ChatRoomRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Encapsulates chat-room message persistence and retrieval rules.
 */
class ManageChatRoomMessagesService
{
    /**
     * Create a new service instance.
     *
     * Logic:
     * 1) Inject room repository for membership-scoped room resolution.
     * 2) Inject room message repository for persistence/query operations.
     *
     * @param  ChatRoomRepository  $chatRoomRepository
     * @param  ChatRoomMessageRepository  $chatRoomMessageRepository
     * @return void
     */
    public function __construct(
        private readonly ChatRoomRepository $chatRoomRepository,
        private readonly ChatRoomMessageRepository $chatRoomMessageRepository,
    ) {}

    /**
     * Persist and broadcast one room-scoped chat message.
     *
     * Logic:
     * 1) Resolve room scoped to authenticated participant membership.
     * 2) Persist room message row with sender and room IDs.
     * 3) Broadcast realtime room message event to all other participants.
     *
     * @param  User  $authenticatedUser
     * @param  int  $chatRoomId
     * @param  string  $message
     * @return ChatRoomMessage
     */
    public function send(User $authenticatedUser, int $chatRoomId, string $message): ChatRoomMessage
    {
        $chatRoom = $this->chatRoomRepository->findForParticipant($chatRoomId, $authenticatedUser);

        $chatRoomMessage = $this->chatRoomMessageRepository->create($chatRoom, $authenticatedUser, $message);

        $recipientIds = $chatRoom->users
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(fn (int $userId): bool => $userId !== (int) $authenticatedUser->id)
            ->values()
            ->all();

        foreach ($recipientIds as $recipientId) {
            event(new ChatRoomMessageSent(
                to_user_id: $recipientId,
                chat_room_id: (int) $chatRoom->id,
                chat_room_message_id: (int) $chatRoomMessage->id,
                from_user_id: (int) $authenticatedUser->id,
                from_user_name: (string) $authenticatedUser->name,
                message: (string) $chatRoomMessage->message,
                created_at: $chatRoomMessage->created_at?->toISOString(),
            ));
        }

        return $chatRoomMessage;
    }

    /**
     * Return room-scoped message history for one room.
     *
     * Logic:
     * 1) Resolve room scoped to authenticated participant membership.
     * 2) Query ordered room history via repository layer.
     *
     * @param  User  $authenticatedUser
     * @param  int  $chatRoomId
     * @param  int  $limit
     * @return Collection<int, ChatRoomMessage>
     */
    public function conversation(User $authenticatedUser, int $chatRoomId, int $limit = 200): Collection
    {
        $chatRoom = $this->chatRoomRepository->findForParticipant($chatRoomId, $authenticatedUser);

        return $this->chatRoomMessageRepository->conversation($chatRoom, $limit);
    }
}
