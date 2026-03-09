<?php

namespace App\Repositories\Chat;

use App\Models\ChatRoom;
use App\Models\ChatRoomMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Handles persistence and query operations for chat-room messages.
 */
class ChatRoomMessageRepository
{
    /**
     * Create and return one room message row.
     *
     * Logic:
     * 1) Persist room ID, sender ID, and message payload.
     * 2) Eager-load sender relation for API/resource projection.
     *
     * @param  ChatRoom  $chatRoom
     * @param  User  $fromUser
     * @param  string  $message
     * @return ChatRoomMessage
     */
    public function create(ChatRoom $chatRoom, User $fromUser, string $message): ChatRoomMessage
    {
        $chatRoomMessage = ChatRoomMessage::query()->create([
            'chat_room_id' => (int) $chatRoom->id,
            'from_user_id' => (int) $fromUser->id,
            'message' => $message,
        ]);

        $chatRoomMessage->loadMissing(['fromUser:id,name']);

        return $chatRoomMessage;
    }

    /**
     * Return ordered room message history for one room.
     *
     * Logic:
     * 1) Scope messages to the provided room ID.
     * 2) Eager-load sender metadata used by API resources.
     * 3) Order oldest-to-newest with configurable limit.
     *
     * @param  ChatRoom  $chatRoom
     * @param  int  $limit
     * @return Collection<int, ChatRoomMessage>
     */
    public function conversation(ChatRoom $chatRoom, int $limit): Collection
    {
        return ChatRoomMessage::query()
            ->with(['fromUser:id,name'])
            ->where('chat_room_id', (int) $chatRoom->id)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }
}
