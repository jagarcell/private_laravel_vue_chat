<?php

namespace App\Http\Resources;

use App\Models\ChatRoomMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms room-scoped chat message data into API response shape.
 */
class ChatRoomMessageResource extends JsonResource
{
    /**
     * Convert resource instance to response array.
     *
     * Logic:
     * 1) Detect model-backed resources versus array-backed fallback payloads.
     * 2) Normalize both cases to one frontend-compatible message shape.
     * 3) Derive `is_mine` flag against authenticated user context.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authenticatedUserId = (int) ($request->user()?->id ?? 0);

        if ($this->resource instanceof ChatRoomMessage) {
            return [
                'id' => (int) $this->resource->id,
                'chat_room_id' => (int) $this->resource->chat_room_id,
                'from_user_id' => (int) $this->resource->from_user_id,
                'to_user_id' => null,
                'from_user_name' => (string) ($this->resource->fromUser?->name ?? 'User'),
                'message' => (string) $this->resource->message,
                'is_mine' => (int) $this->resource->from_user_id === $authenticatedUserId,
                'read_at' => null,
                'created_at' => $this->resource->created_at?->toISOString(),
            ];
        }

        return [
            'id' => (int) ($this['id'] ?? 0),
            'chat_room_id' => (int) ($this['chat_room_id'] ?? 0),
            'from_user_id' => (int) ($this['from_user_id'] ?? $authenticatedUserId),
            'to_user_id' => null,
            'from_user_name' => (string) ($this['from_user_name'] ?? ($request->user()?->name ?? 'You')),
            'message' => $this['message'] ?? null,
            'is_mine' => (bool) ($this['is_mine'] ?? true),
            'read_at' => null,
            'created_at' => $this['created_at'] ?? null,
        ];
    }
}
