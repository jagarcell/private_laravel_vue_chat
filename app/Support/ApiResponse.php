<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Provides a consistent JSON response envelope for API success and error outputs.
 */
class ApiResponse
{
    /**
     * Build a standardized success response payload.
     *
     * Logic:
     * 1) Set `success` to true.
     * 2) Include the caller-provided message and data payload.
     * 3) Return the payload as JSON with the given HTTP status code.
     *
     * @param  string  $message
     * @param  array<string, mixed>|null  $data
     * @param  int  $status
     * @return JsonResponse
     */
    public static function success(string $message, ?array $data = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
        * Build a standardized error response payload.
        *
        * Logic:
        * 1) Set `success` to false.
        * 2) Include message, optional data, and optional validation/domain errors.
        * 3) Return the payload as JSON with the provided HTTP status code.
        *
        * @param  string  $message
        * @param  int  $status
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>|null  $errors
        * @return JsonResponse
     */
    public static function error(string $message, int $status, ?array $data = null, ?array $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'data' => $data,
        ];

        if (! is_null($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
