<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate payload for creating chat rooms.
 */
class ChatRoomStoreRequest extends FormRequest
{
    /**
     * Determine whether this request is authorized.
     *
     * Logic:
     * 1) Delegate authentication and permission checks to route middleware.
     * 2) Allow request-specific validation to execute.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for chat-room creation.
     *
     * Logic:
     * 1) Require room name with max length.
     * 2) Require at least one selected participant.
     * 3) Ensure each participant ID exists and is unique.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id', 'distinct'],
        ];
    }
}
