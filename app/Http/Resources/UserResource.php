<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform user data into the API contract consumed by chat clients.
 */
class UserResource extends JsonResource
{
    /**
     * Convert the resource instance into a serializable array.
     *
     * Logic:
     * 1) Read user attributes from either array-backed data or model properties.
     * 2) Expose core identity fields (`id`, `name`, `email`).
     * 3) Resolve online state using `is_online` first, with legacy fallback to
     *    `is_authenticated` for compatibility.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'] ?? $this->id,
            'name' => $this['name'] ?? $this->name,
            'email' => $this['email'] ?? $this->email,
            'is_online' => $this['is_online'] ?? ($this['is_authenticated'] ?? false),
        ];
    }
}
