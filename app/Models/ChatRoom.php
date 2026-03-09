<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a persisted multi-user chat room.
 */
class ChatRoom extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'chat_rooms';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'created_by_user_id',
        'name',
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
     * Resolve the room creator relation.
     *
     * Logic:
     * 1) Link each room row to the owning creator user record.
     *
     * @return BelongsTo<User, ChatRoom>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Resolve room participants relation.
     *
     * Logic:
     * 1) Expose many-to-many membership through `chat_room_user` pivot.
     * 2) Keep pivot timestamps for membership lifecycle tracking.
     *
     * @return BelongsToMany<User, ChatRoom>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_room_user')
            ->withTimestamps();
    }

    /**
     * Resolve pending/processed invites for this room.
     *
     * Logic:
     * 1) Map one-to-many invite records by room foreign key.
     *
     * @return HasMany<ChatRoomInvite, ChatRoom>
     */
    public function invites(): HasMany
    {
        return $this->hasMany(ChatRoomInvite::class, 'chat_room_id');
    }

    /**
     * Resolve persisted room messages relation.
     *
     * Logic:
     * 1) Map one-to-many room messages by room foreign key.
     *
     * @return HasMany<ChatRoomMessage, ChatRoom>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatRoomMessage::class, 'chat_room_id');
    }

}
