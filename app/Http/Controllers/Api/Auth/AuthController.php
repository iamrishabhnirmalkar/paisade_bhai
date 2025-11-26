<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        // Validation rules
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:users,phone_number|max:15',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray(), 'Registration validation failed');
        }

        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'phone_number' => $request->phone_number,
                'password' => Hash::make($request->password),
            ]);

            // Generate JWT tokens
            $accessToken = JWTAuth::fromUser($user);
            $refreshToken = JWTAuth::customClaims(['rt' => true])->fromUser($user); // Custom claim for refresh token

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'phone_number' => $user->phone_number,
                'tokens' => [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'bearer',
                    'access_token_expires_in' => config('jwt.ttl') * 60, // in seconds
                    'refresh_token_expires_in' => config('jwt.refresh_ttl') * 60, // in seconds
                ]
            ];

            return $this->createdResponse($userData, 'User registered successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Login user and return JWT tokens
     */
    public function login(Request $request): JsonResponse
    {
        // Validation rules
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray(), 'Login validation failed');
        }

        try {
            $credentials = $request->only('phone_number', 'password');

            // Attempt to verify credentials and create token
            if (!$accessToken = JWTAuth::attempt($credentials)) {
                return $this->errorResponse('Invalid phone number or password', 401);
            }

            // Generate refresh token with custom claim
            $refreshToken = JWTAuth::customClaims(['rt' => true])->attempt($credentials);

            $user = Auth::user();
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'phone_number' => $user->phone_number,
                'tokens' => [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'bearer',
                    'access_token_expires_in' => config('jwt.ttl') * 60, // in seconds
                    'refresh_token_expires_in' => config('jwt.refresh_ttl') * 60, // in seconds
                ]
            ];

            return $this->successResponse($userData, 'Login successful');
        } catch (\Exception $e) {
            return $this->errorResponse('Login failed: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Logout user (invalidate tokens)
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Get both tokens from request
            $accessToken = JWTAuth::getToken();

            // Invalidate both access and refresh tokens
            JWTAuth::invalidate($accessToken);

            return $this->successResponse(null, 'Successfully logged out');
        } catch (\Exception $e) {
            return $this->errorResponse('Logout failed: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Refresh JWT tokens
     */
    public function refresh_token(Request $request): JsonResponse
    {
        try {
            // Get the current token (should be refresh token)
            $currentToken = JWTAuth::getToken();

            // Check if it's a refresh token by checking custom claim
            $payload = JWTAuth::getPayload($currentToken);

            if (!$payload->get('rt')) {
                return $this->errorResponse('Invalid refresh token', 401);
            }

            // Refresh both tokens
            $newAccessToken = JWTAuth::refresh($currentToken);
            $newRefreshToken = JWTAuth::customClaims(['rt' => true])->refresh($currentToken);

            $user = Auth::user();
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'phone_number' => $user->phone_number,
                'tokens' => [
                    'access_token' => $newAccessToken,
                    'refresh_token' => $newRefreshToken,
                    'token_type' => 'bearer',
                    'access_token_expires_in' => config('jwt.ttl') * 60, // in seconds
                    'refresh_token_expires_in' => config('jwt.refresh_ttl') * 60, // in seconds
                ]
            ];

            return $this->successResponse($userData, 'Tokens refreshed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Token refresh failed: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'phone_number' => $user->phone_number,
                'phone_verified_at' => $user->phone_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];

            return $this->successResponse($userData, 'User retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve user: ' . $e->getMessage(), 500, $e);
        }
    }
}
