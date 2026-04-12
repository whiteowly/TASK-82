<?php
declare(strict_types=1);

namespace app;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;

class ExceptionHandle extends Handle
{
    /**
     * Render exceptions into normalized API error envelopes.
     */
    public function render($request, \Throwable $e): Response
    {
        // Let HTTP response exceptions pass through
        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }

        $requestId = $request->requestId ?? generate_request_id();

        // Validation errors
        if ($e instanceof ValidateException) {
            return json([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Validation failed',
                    'details' => is_array($e->getError()) ? $e->getError() : [$e->getError()],
                ],
                'meta' => ['request_id' => $requestId],
            ])->code(422);
        }

        // Not found
        if ($e instanceof DataNotFoundException || $e instanceof ModelNotFoundException) {
            return json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Resource not found',
                    'details' => [],
                ],
                'meta' => ['request_id' => $requestId],
            ])->code(404);
        }

        // HTTP exceptions (403, 404, etc.)
        if ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();
            $code = match ($statusCode) {
                401 => 'AUTH_REQUIRED',
                403 => 'FORBIDDEN_ROLE',
                404 => 'NOT_FOUND',
                429 => 'RATE_LIMITED',
                default => 'ERROR',
            };
            return json([
                'error' => [
                    'code' => $code,
                    'message' => $e->getMessage() ?: 'Request failed',
                    'details' => [],
                ],
                'meta' => ['request_id' => $requestId],
            ])->code($statusCode);
        }

        // Log the real error for operators; never send internals to clients
        \think\facade\Log::error('Unhandled exception: ' . get_class($e) . ': ' . $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
            'request_id' => $requestId,
        ]);

        return json([
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => 'An internal error occurred',
                'details' => [],
            ],
            'meta' => ['request_id' => $requestId],
        ])->code(500);
    }
}
