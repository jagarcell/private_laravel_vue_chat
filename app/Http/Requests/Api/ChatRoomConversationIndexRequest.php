<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate query payload for room conversation history retrieval.
 */
class ChatRoomConversationIndexRequest extends FormRequest
{
    /**
     * Determine whether this request is authorized.
     *
     * Logic:
     * 1) Allow all authenticated callers and defer membership checks to service/repository layer.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for room conversation query params.
     *
     * Logic:
     * 1) Accept optional `limit` constraint for bounded history pagination.
     * 2) Enforce sane minimum/maximum range to protect query cost.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
