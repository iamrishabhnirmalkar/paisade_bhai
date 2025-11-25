<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Throwable;

trait ApiResponseTrait
{
    /**
     * Success Response
     */
    protected function successResponse($data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        $response = [
            'status' => true,
            'statusCode' => $statusCode,
            'request' => [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'url' => request()->fullUrl(),
            ],
            'message' => $message,
            'data' => $data,
        ];

        return response()->json($response, $statusCode);
    }

    /**
     * Error Response
     */
    protected function errorResponse(string $message = 'Error', int $statusCode = 500, ?Throwable $exception = null, array $errors = []): JsonResponse
    {
        $response = [
            'status' => false,
            'statusCode' => $statusCode,
            'request' => [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'url' => request()->fullUrl(),
            ],
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        // Add trace only in local/dev environment
        if (config('app.debug') && $exception) {
            $response['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => collect($exception->getTrace())->take(5),
            ];
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Resource created response
     */
    protected function createdResponse($data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * No content response
     */
    protected function noContentResponse(string $message = 'No content'): JsonResponse
    {
        return $this->successResponse(null, $message, 204);
    }

    /**
     * Validation error response
     */
    protected function validationErrorResponse(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, 422, null, $errors);
    }
}
