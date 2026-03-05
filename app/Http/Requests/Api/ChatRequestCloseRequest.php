<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate payload for closing an active chat.
 */
class ChatRequestCloseRequest extends FormRequest
{
    /**
     * Determine whether the requester is allowed to make this request.
     *
     * Logic:
     * 1) Delegate authentication and authorization to route middleware.
     * 2) Allow validation to proceed.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for close-chat payload.
     *
     * Logic:
     * 1) Require a target user ID.
     * 2) Ensure value is an integer.
     * 3) Ensure target user exists.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
