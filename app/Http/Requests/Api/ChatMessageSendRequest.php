<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate payload for sending direct chat messages.
 */
class ChatMessageSendRequest extends FormRequest
{
    /**
     * Determine whether this request is authorized.
     *
     * Logic:
     * 1) Delegate auth and permission checks to route middleware.
     * 2) Allow request-specific validation to run.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for direct message sending.
     *
     * Logic:
     * 1) Require a target user ID.
     * 2) Ensure target ID is an integer that exists.
     * 3) Require message text and constrain its size.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
