<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
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
