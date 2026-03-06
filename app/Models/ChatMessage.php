<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a persisted direct chat message exchanged between two users.
 */
class ChatMessage extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chat_messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'message',
        'read_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
        * Logic:
        * 1) Cast read timestamp for unread/read state handling.
        * 2) Cast lifecycle timestamps for API formatting.
        *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Resolve the sender user relation.
     *
        * Logic:
        * 1) Bind `from_user_id` foreign key to `User` model.
        * 2) Allow eager-loading sender metadata with each message.
        *
     * @return BelongsTo<User, ChatMessage>
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * Resolve the recipient user relation.
     *
        * Logic:
        * 1) Bind `to_user_id` foreign key to `User` model.
        * 2) Allow eager-loading recipient metadata when needed.
        *
     * @return BelongsTo<User, ChatMessage>
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
