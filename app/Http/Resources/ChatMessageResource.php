<?php

namespace App\Http\Resources;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms direct chat message action data into API response shape.
 */
class ChatMessageResource extends JsonResource
{
    /**
     * Convert the resource instance to an array.
     *
     * Logic:
     * 1) Expose target user identifier.
     * 2) Expose message content sent by the requester.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authenticatedUserId = (int) ($request->user()?->id ?? 0);

        if ($this->resource instanceof ChatMessage) {
            return [
                'id' => (int) $this->resource->id,
                'from_user_id' => (int) $this->resource->from_user_id,
                'from_user_name' => (string) ($this->resource->fromUser?->name ?? 'User'),
                'to_user_id' => (int) $this->resource->to_user_id,
                'message' => (string) $this->resource->message,
                'is_mine' => (int) $this->resource->from_user_id === $authenticatedUserId,
                'read_at' => $this->resource->read_at?->toISOString(),
                'created_at' => $this->resource->created_at?->toISOString(),
            ];
        }

        return [
            'id' => (int) ($this['id'] ?? 0),
            'from_user_id' => (int) ($this['from_user_id'] ?? $authenticatedUserId),
            'from_user_name' => (string) ($this['from_user_name'] ?? ($request->user()?->name ?? 'You')),
            'to_user_id' => $this['to_user_id'] ?? null,
            'message' => $this['message'] ?? null,
            'is_mine' => (bool) ($this['is_mine'] ?? true),
            'read_at' => $this['read_at'] ?? null,
            'created_at' => $this['created_at'] ?? null,
        ];
    }
}
