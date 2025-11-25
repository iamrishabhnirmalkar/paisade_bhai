<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        // Implementation needed. Placeholder response.
        return response()->json(['message' => 'Not implemented'], 501);
    }
    public function login(Request $request): JsonResponse
    {
        // Implementation needed. Placeholder response.
        return response()->json(['message' => 'Not implemented'], 501);
    }
    public function logout(Request $request): JsonResponse
    {
        // Implementation needed. Placeholder response.
        return response()->json(['message' => 'Not implemented'], 501);
    }
    public function refresh_token(Request $request): JsonResponse
    {
        // Implementation needed. Placeholder response.
        return response()->json(['message' => 'Not implemented'], 501);
    }
    public function me(Request $request): JsonResponse
    {
        // Implementation needed. Placeholder response.
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
