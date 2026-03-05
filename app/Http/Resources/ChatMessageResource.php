<?php

namespace App\Http\Resources;

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
        return [
            'to_user_id' => $this['to_user_id'] ?? null,
            'message' => $this['message'] ?? null,
        ];
    }
}
