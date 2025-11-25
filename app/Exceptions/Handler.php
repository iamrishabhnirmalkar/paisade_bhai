<?php

namespace App\Exceptions;

use App\Traits\ApiResponseTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponseTrait;

    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // Always return JSON for API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * Handle API exceptions
     */
    protected function handleApiException($request, Throwable $exception)
    {
        $statusCode = $this->getStatusCode($exception);
        $message = $this->getMessage($exception);

        $response = [
            'status' => false,
            'statusCode' => $statusCode,
            'request' => [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
            ],
            'message' => $message,
        ];

        // Add validation errors if available
        if ($exception instanceof ValidationException) {
            $response['errors'] = $exception->errors();
        }

        // Add trace only for non-404 errors and only in debug mode
        if ($statusCode !== 404 && config('app.debug')) {
            $response['trace'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => collect($exception->getTrace())->take(5)->map(function ($trace) {
                    return [
                        'file' => $trace['file'] ?? 'unknown',
                        'line' => $trace['line'] ?? 0,
                        'function' => $trace['function'] ?? 'unknown',
                        'class' => $trace['class'] ?? null,
                        'type' => $trace['type'] ?? null,
                    ];
                })->toArray(),
            ];
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Get the status code from exception
     */
    protected function getStatusCode(Throwable $exception): int
    {
        if ($exception instanceof ValidationException) {
            return 422;
        }

        if ($exception instanceof AuthenticationException) {
            return 401;
        }

        if ($exception instanceof ModelNotFoundException) {
            return 404;
        }

        if ($exception instanceof NotFoundHttpException) {
            return 404;
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return 405;
        }

        if ($exception instanceof HttpException) {
            return $exception->getStatusCode();
        }

        return 500;
    }

    /**
     * Get the exception message
     */
    protected function getMessage(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return 'Validation failed';
        }

        if ($exception instanceof AuthenticationException) {
            return 'Unauthenticated';
        }

        if ($exception instanceof ModelNotFoundException) {
            return 'Resource not found';
        }

        if ($exception instanceof NotFoundHttpException) {
            return 'Endpoint not found';
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return 'Method not allowed';
        }

        // Return exception message or default
        return $exception->getMessage() ?: 'Something went wrong!';
    }
}
