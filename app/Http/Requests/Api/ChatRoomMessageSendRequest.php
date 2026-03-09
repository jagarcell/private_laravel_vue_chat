<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate payload for sending a chat-room message.
 */
class ChatRoomMessageSendRequest extends FormRequest
{
    /**
     * Determine whether this request is authorized.
     *
     * Logic:
     * 1) Allow request through and rely on service-layer participant checks for room access.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for room message payload.
     *
     * Logic:
     * 1) Require a message body for send operations.
     * 2) Limit message length to avoid oversized payloads.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}
