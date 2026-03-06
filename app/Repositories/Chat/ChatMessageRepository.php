<?php

namespace App\Repositories\Chat;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Handles persistence and query operations for direct chat messages.
 */
class ChatMessageRepository
{
    /**
     * Create and return a direct chat message row.
     *
     * Logic:
     * 1) Persist sender, recipient, and message payload.
     * 2) Initialize message as unread (`read_at` null).
     * 3) Eager-load sender relation for response/event payloads.
     *
     * @param  User  $fromUser
     * @param  int  $toUserId
     * @param  string  $message
     * @return ChatMessage
     */
    public function create(User $fromUser, int $toUserId, string $message): ChatMessage
    {
        $chatMessage = ChatMessage::query()->create([
            'from_user_id' => (int) $fromUser->id,
            'to_user_id' => $toUserId,
            'message' => $message,
            'read_at' => null,
        ]);

        $chatMessage->loadMissing(['fromUser:id,name']);

        return $chatMessage;
    }

    /**
     * Fetch conversation messages between two users.
     *
     * Logic:
     * 1) Query both direction pairs for provided users.
     * 2) Order by creation timestamp ascending.
     * 3) Limit and eager-load sender metadata.
     *
     * @param  User  $authenticatedUser
     * @param  User  $otherUser
     * @param  int  $limit
     * @return Collection<int, ChatMessage>
     */
    public function conversation(User $authenticatedUser, User $otherUser, int $limit): Collection
    {
        return ChatMessage::query()
            ->with(['fromUser:id,name'])
            ->where(function ($query) use ($authenticatedUser, $otherUser): void {
                $query
                    ->where('from_user_id', $authenticatedUser->id)
                    ->where('to_user_id', $otherUser->id);
            })
            ->orWhere(function ($query) use ($authenticatedUser, $otherUser): void {
                $query
                    ->where('from_user_id', $otherUser->id)
                    ->where('to_user_id', $authenticatedUser->id);
            })
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Return unread incoming message counters grouped by sender.
     *
     * Logic:
     * 1) Select unread rows addressed to authenticated user.
     * 2) Group by sender and count rows.
     * 3) Normalize aggregate rows to array payload.
     *
     * @param  User  $authenticatedUser
     * @return array<int, array<string, int>>
     */
    public function unreadCounts(User $authenticatedUser): array
    {
        return ChatMessage::query()
            ->selectRaw('from_user_id, COUNT(*) as unread_count')
            ->where('to_user_id', $authenticatedUser->id)
            ->whereNull('read_at')
            ->groupBy('from_user_id')
            ->orderBy('from_user_id')
            ->get()
            ->map(function (ChatMessage $chatMessage): array {
                return [
                    'user_id' => (int) $chatMessage->from_user_id,
                    'unread_count' => (int) $chatMessage->unread_count,
                ];
            })
            ->all();
    }

    /**
     * Mark unread incoming messages from one sender as read.
     *
     * Logic:
     * 1) Scope to unread rows from other user to authenticated user.
     * 2) Set `read_at` to current timestamp.
     * 3) Return number of affected rows.
     *
     * @param  User  $authenticatedUser
     * @param  User  $otherUser
     * @return int
     */
    public function markConversationAsRead(User $authenticatedUser, User $otherUser): int
    {
        return ChatMessage::query()
            ->where('from_user_id', $otherUser->id)
            ->where('to_user_id', $authenticatedUser->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);
    }

    /**
     * Return unread incoming message IDs for one conversation direction.
     *
     * Logic:
     * 1) Scope query to unread messages sent by other user to authenticated user.
     * 2) Order by ID so receipt updates follow insertion order.
     * 3) Return normalized integer IDs for broadcasting payload.
     *
     * @param  User  $authenticatedUser
     * @param  User  $otherUser
     * @return array<int, int>
     */
    public function unreadIncomingMessageIds(User $authenticatedUser, User $otherUser): array
    {
        return ChatMessage::query()
            ->where('from_user_id', $otherUser->id)
            ->where('to_user_id', $authenticatedUser->id)
            ->whereNull('read_at')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
