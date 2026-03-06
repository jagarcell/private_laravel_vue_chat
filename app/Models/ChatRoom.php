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
     * @return BelongsTo<User, ChatRoom>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Resolve room participants relation.
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
     * @return HasMany<ChatRoomInvite, ChatRoom>
     */
    public function invites(): HasMany
    {
        return $this->hasMany(ChatRoomInvite::class, 'chat_room_id');
    }

}
