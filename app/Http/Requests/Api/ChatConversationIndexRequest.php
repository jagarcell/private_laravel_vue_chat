<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate query payload for conversation history retrieval.
 */
class ChatConversationIndexRequest extends FormRequest
{
    /**
     * Determine whether request is authorized.
     *
        * Logic:
        * 1) Defer authentication and authorization checks to middleware.
        * 2) Allow request-specific validation to execute.
        *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for conversation query params.
     *
        * Logic:
        * 1) Accept optional `limit` parameter.
        * 2) Constrain to a safe integer range for query performance.
        *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ];
    }
}
