<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate payload for responding to a chat request.
 */
class ChatRequestRespondRequest extends FormRequest
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
     * Get validation rules for response payload.
     *
     * Logic:
     * 1) Require original requester user ID.
     * 2) Ensure requester ID is an integer and exists.
     * 3) Restrict action to `accept` or `decline`.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'requester_user_id' => ['required', 'integer', 'exists:users,id'],
            'action' => ['required', 'in:accept,decline'],
        ];
    }
}
