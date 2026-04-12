<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use App\Services\UserAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(
        private readonly UserAuthService $authService,
    ) {}

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return response()->json([
            'data' => [
                'user'  => $result['user'],
                'token' => $result['token'],
            ],
            'message' => __('Login successful.'),
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'data' => [
                'user'  => $result['user'],
                'token' => $result['token'],
            ],
            'message' => __('Registration successful.'),
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->logout($user);

        return response()->json([
            'message' => __('Logged out successfully.'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'user' => $request->user(),
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'  => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user = $this->authService->updateProfile($user, $validated);

        return response()->json([
            'data' => [
                'user' => $user,
            ],
            'message' => __('Profile updated successfully.'),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->authService->refresh($user);

        return response()->json([
            'data' => [
                'user'  => $result['user'],
                'token' => $result['token'],
            ],
            'message' => __('Token refreshed.'),
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->sendPasswordResetLink($request->validated()['email']);

        return response()->json([
            'message' => __('Password reset link sent.'),
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->validated());

        return response()->json([
            'message' => __('Password reset successfully.'),
        ]);
    }
}
