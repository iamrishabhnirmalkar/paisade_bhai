<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FallbackController extends Controller
{
    use ApiResponseTrait;

    /**
     * Handle undefined API routes (404 Not Found)
     */
    public function __invoke(Request $request): JsonResponse
    {
        return $this->errorResponse(
            message: 'Endpoint not found. The requested API route does not exist.',
            statusCode: 404
        );
    }

    /**
     * Handle method not allowed errors (405 Method Not Allowed)
     */
    public function methodNotAllowed(Request $request): JsonResponse
    {
        $allowedMethods = $request->route()
            ? implode(', ', $request->route()->methods())
            : 'Unknown';

        return $this->errorResponse(
            message: "Method {$request->method()} is not allowed for this route. Allowed methods: {$allowedMethods}",
            statusCode: 405
        );
    }
}
