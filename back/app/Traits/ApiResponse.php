<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json([
            'statusCode' => $code,
            'status'     => 'success',
            'timestamp'  => now()->toIso8601String(),
            'message'    => $message,
            'data'       => $data,
        ], $code);
    }

    protected function error(string $message, int $code = 400, mixed $data = null): JsonResponse
    {
        return response()->json([
            'statusCode' => $code,
            'status'     => 'error',
            'timestamp'  => now()->toIso8601String(),
            'message'    => $message,
            'data'       => $data,
        ], $code);
    }
}
