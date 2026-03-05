<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms chat request action metadata into a consistent API response shape.
 */
class ChatRequestActionResource extends JsonResource
{
    /**
     * Convert the resource instance to an array.
     *
     * Logic:
     * 1) Expose action type (`requested`, `accept`, `decline`, `closed`).
     * 2) Include target user reference when present.
     * 3) Include requester user reference when present.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'action' => $this['action'] ?? null,
            'to_user_id' => $this['to_user_id'] ?? null,
            'requester_user_id' => $this['requester_user_id'] ?? null,
        ];
    }
}
