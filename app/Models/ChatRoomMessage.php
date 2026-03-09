<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a persisted chat message sent in a chat room.
 */
class ChatRoomMessage extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'chat_room_messages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'chat_room_id',
        'from_user_id',
        'message',
    ];

    /**
     * Cast timestamp fields to Carbon instances.
     *
     * Logic:
     * 1) Ensure created/updated timestamps are consistently hydrated as datetimes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Resolve room relation for one room message.
     *
     * Logic:
     * 1) Link message row back to its owning chat room.
     *
     * @return BelongsTo<ChatRoom, ChatRoomMessage>
     */
    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    /**
     * Resolve sender relation for one room message.
     *
     * Logic:
     * 1) Link message row to sender user record for name/avatar projection.
     *
     * @return BelongsTo<User, ChatRoomMessage>
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }
}
