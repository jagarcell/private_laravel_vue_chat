<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate payload for marking a conversation as read.
 */
class ChatConversationMarkReadRequest extends FormRequest
{
    /**
     * Determine whether request is authorized.
     *
        * Logic:
        * 1) Defer authentication and authorization checks to middleware.
        * 2) Allow request handling to proceed.
        *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for mark-read request.
     *
        * Logic:
        * 1) No request body fields are required for this endpoint.
        * 2) Route model binding provides the target conversation user.
        *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
