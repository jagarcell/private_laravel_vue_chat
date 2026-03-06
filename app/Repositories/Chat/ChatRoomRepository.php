<?php

namespace App\Repositories\Chat;

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Handles persistence and query operations for chat rooms.
 */
class ChatRoomRepository
{
    /**
     * Create a new chat room and attach participant users.
     *
     * Logic:
     * 1) Create room row with creator and room name.
     * 2) Attach participants through pivot table.
     * 3) Eager-load creator and participants for API projection.
     *
     * @param  User  $creator
     * @param  string  $name
     * @param  array<int, int>  $participantUserIds
     * @return ChatRoom
     */
    public function create(User $creator, string $name, array $participantUserIds): ChatRoom
    {
        /** @var ChatRoom $chatRoom */
        $chatRoom = DB::transaction(function () use ($creator, $name, $participantUserIds): ChatRoom {
            $room = ChatRoom::query()->create([
                'created_by_user_id' => (int) $creator->id,
                'name' => $name,
            ]);

            $room->users()->sync($participantUserIds);
            $room->loadMissing(['creator:id,name', 'users:id,name,email']);

            return $room;
        });

        return $chatRoom;
    }

    /**
     * Add one participant to an existing room when absent.
     *
     * Logic:
     * 1) Attach user via pivot without removing existing participants.
     * 2) Eager-load creator and participants for API projection.
     * 3) Return refreshed room aggregate.
     *
     * @param  ChatRoom  $chatRoom
     * @param  int  $userId
     * @return ChatRoom
     */
    public function addParticipant(ChatRoom $chatRoom, int $userId): ChatRoom
    {
        $chatRoom->users()->syncWithoutDetaching([$userId]);
        $chatRoom->loadMissing(['creator:id,name', 'users:id,name,email']);

        return $chatRoom;
    }

    /**
     * Return chat rooms where the authenticated user is a participant.
     *
     * Logic:
     * 1) Query rooms constrained by membership relation to authenticated user.
     * 2) Eager-load creator and participants for response projection.
     * 3) Return rooms ordered by latest creation timestamp.
     *
     * @param  User  $authenticatedUser
     * @return Collection<int, ChatRoom>
     */
    public function listForUser(User $authenticatedUser): Collection
    {
        return ChatRoom::query()
            ->with(['creator:id,name', 'users:id,name,email'])
            ->whereHas('users', function ($query) use ($authenticatedUser): void {
                $query->where('users.id', $authenticatedUser->id);
            })
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Resolve one chat room when authenticated user is a participant.
     *
     * @param  int  $chatRoomId
     * @param  User  $authenticatedUser
     * @return ChatRoom
     */
    public function findForParticipant(int $chatRoomId, User $authenticatedUser): ChatRoom
    {
        $chatRoom = ChatRoom::query()
            ->with(['creator:id,name', 'users:id,name,email'])
            ->where('id', $chatRoomId)
            ->whereHas('users', function ($query) use ($authenticatedUser): void {
                $query->where('users.id', $authenticatedUser->id);
            })
            ->first();

        if (is_null($chatRoom)) {
            throw new InvalidArgumentException('Chat room not found.');
        }

        return $chatRoom;
    }

    /**
     * Remove one participant from room membership.
     *
     * @param  ChatRoom  $chatRoom
     * @param  int  $userId
     * @return void
     */
    public function removeParticipant(ChatRoom $chatRoom, int $userId): void
    {
        $chatRoom->users()->detach($userId);
    }

    /**
     * Delete one chat room.
     *
     * @param  ChatRoom  $chatRoom
     * @return void
     */
    public function delete(ChatRoom $chatRoom): void
    {
        $chatRoom->delete();
    }
}
