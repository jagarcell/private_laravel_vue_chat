<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Resolve chat rooms created by this user.
     *
     * @return HasMany<ChatRoom, User>
     */
    public function createdChatRooms(): HasMany
    {
        return $this->hasMany(ChatRoom::class, 'created_by_user_id');
    }

    /**
     * Resolve chat rooms where this user is a participant.
     *
     * @return BelongsToMany<ChatRoom, User>
     */
    public function chatRooms(): BelongsToMany
    {
        return $this->belongsToMany(ChatRoom::class, 'chat_room_user')
            ->withTimestamps();
    }

    /**
     * Resolve room invites received by this user.
     *
     * @return HasMany<ChatRoomInvite, User>
     */
    public function receivedChatRoomInvites(): HasMany
    {
        return $this->hasMany(ChatRoomInvite::class, 'to_user_id');
    }

    /**
     * Resolve room invites sent by this user.
     *
     * @return HasMany<ChatRoomInvite, User>
     */
    public function sentChatRoomInvites(): HasMany
    {
        return $this->hasMany(ChatRoomInvite::class, 'from_user_id');
    }

}
