<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms unread message count records into API response shape.
 */
class ChatUnreadCountResource extends JsonResource
{
    /**
     * Convert the resource instance to an array.
     *
        * Logic:
        * 1) Normalize sender user ID to integer.
        * 2) Normalize unread message count to integer.
        *
     * @param  Request  $request
     * @return array<string, int>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => (int) ($this['user_id'] ?? 0),
            'unread_count' => (int) ($this['unread_count'] ?? 0),
        ];
    }
}
