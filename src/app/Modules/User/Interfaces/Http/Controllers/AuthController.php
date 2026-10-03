<?php

namespace App\Modules\User\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiForgotPasswordRequest;
use App\Http\Requests\Api\ApiLoginRequest;
use App\Http\Requests\Api\ApiRegisterRequest;
use App\Http\Requests\Api\ApiResetPasswordRequest;
use App\Modules\User\Models\User;
use App\Modules\User\Interfaces\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(ApiRegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user->assignRole('user');

        $token = $user->createToken('spa-token')->plainTextToken;

        return response()->json([
            'message' => 'Registered',
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(ApiLoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var \App\Modules\User\Models\User $user */
        $user = $request->user();
        $token = $user->createToken('spa-token')->plainTextToken;

        return response()->json([
            'message' => 'Authenticated',
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function forgotPassword(ApiForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        // Same response whether or not the email is registered, so this
        // endpoint can't be used to enumerate accounts.
        return response()->json([
            'message' => 'If an account exists for that email, a reset link has been sent.',
        ]);
    }

    public function resetPassword(ApiResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
                // Reset is a credential change: drop existing sessions everywhere.
                $user->tokens()->delete();
            }
        );

        $message = match ($status) {
            Password::PASSWORD_RESET => 'Password has been reset.',
            Password::INVALID_TOKEN => 'This password reset link is invalid or has expired.',
            Password::INVALID_USER => 'We could not find an account for that email.',
            default => 'Unable to reset password.',
        };

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [$message]]);
        }

        return response()->json(['message' => $message]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user ? new UserResource($user) : null,
            'roles' => $user?->getRoleNames() ?? [],
        ]);
    }
}
