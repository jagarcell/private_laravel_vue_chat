<?php

namespace App\Http\Resources;

use App\Models\ChatRoom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms chat-room model data into API response shape.
 */
class ChatRoomResource extends JsonResource
{
    /**
     * Convert chat-room resource data to API response array.
     *
     * Logic:
     * 1) When resource is a `ChatRoom` model, map scalar fields directly.
     * 2) Project participant users into a normalized `{id,name,email}` array.
     * 3) Fallback to array-access shape for non-model payload usage.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof ChatRoom) {
            return [
                'id' => (int) $this->resource->id,
                'name' => (string) $this->resource->name,
                'created_by_user_id' => (int) $this->resource->created_by_user_id,
                'created_by_user_name' => (string) ($this->resource->creator?->name ?? 'User'),
                'users' => $this->resource->users
                    ->map(static fn ($user): array => [
                        'id' => (int) $user->id,
                        'name' => (string) $user->name,
                        'email' => (string) $user->email,
                    ])->all(),
                'created_at' => $this->resource->created_at?->toISOString(),
            ];
        }

        return [
            'id' => (int) ($this['id'] ?? 0),
            'name' => (string) ($this['name'] ?? ''),
            'created_by_user_id' => (int) ($this['created_by_user_id'] ?? 0),
            'created_by_user_name' => (string) ($this['created_by_user_name'] ?? 'User'),
            'users' => $this['users'] ?? [],
            'created_at' => $this['created_at'] ?? null,
        ];
    }
}
