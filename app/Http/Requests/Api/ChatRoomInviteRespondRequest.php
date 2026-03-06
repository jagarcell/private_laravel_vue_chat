<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate payload for responding to chat-room invites.
 */
class ChatRoomInviteRespondRequest extends FormRequest
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
     * Get validation rules for chat-room invite response payload.
     *
     * Logic:
     * 1) Require `action` field in request payload.
     * 2) Restrict `action` to `accept` or `decline`.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:accept,decline'],
        ];
    }
}
