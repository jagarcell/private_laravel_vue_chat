<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate query parameters for the users index API endpoint.
 */
class UsersIndexRequest extends FormRequest
{
    /**
     * Determine whether the current request is authorized.
     *
     * Logic:
     * 1) Defer authentication/authorization concerns to route middleware.
     * 2) Always allow this request to proceed to validation.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for supported users index filters.
     *
     * Logic:
     * 1) Accept an optional `search` query parameter.
     * 2) Ensure `search` is a string when provided.
     * 3) Limit search input length for predictable query performance.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
