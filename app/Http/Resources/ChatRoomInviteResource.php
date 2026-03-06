<?php

namespace App\Http\Resources;

use App\Models\ChatRoomInvite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms chat-room invitation data into API response shape.
 */
class ChatRoomInviteResource extends JsonResource
{
    /**
     * Convert chat-room invite resource data to API response array.
     *
     * Logic:
     * 1) When resource is a `ChatRoomInvite` model, map scalar fields directly.
     * 2) Include related room name and requester user name for invite banner UI.
     * 3) Fallback to array-access shape for non-model payload usage.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof ChatRoomInvite) {
            return [
                'id' => (int) $this->resource->id,
                'chat_room_id' => (int) $this->resource->chat_room_id,
                'chat_room_name' => (string) ($this->resource->chatRoom?->name ?? 'Chat Room'),
                'from_user_id' => (int) $this->resource->from_user_id,
                'from_user_name' => (string) ($this->resource->fromUser?->name ?? 'User'),
                'to_user_id' => (int) $this->resource->to_user_id,
                'status' => (string) $this->resource->status,
                'created_at' => $this->resource->created_at?->toISOString(),
            ];
        }

        return [
            'id' => (int) ($this['id'] ?? 0),
            'chat_room_id' => (int) ($this['chat_room_id'] ?? 0),
            'chat_room_name' => (string) ($this['chat_room_name'] ?? 'Chat Room'),
            'from_user_id' => (int) ($this['from_user_id'] ?? 0),
            'from_user_name' => (string) ($this['from_user_name'] ?? 'User'),
            'to_user_id' => (int) ($this['to_user_id'] ?? 0),
            'status' => (string) ($this['status'] ?? 'pending'),
            'created_at' => $this['created_at'] ?? null,
        ];
    }
}
