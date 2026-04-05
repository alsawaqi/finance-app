<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __invoke(\App\Http\Requests\Auth\LoginRequest $request): JsonResponse
    {
        $email = strtolower(trim($request->string('email')->toString()));
        $password = $request->string('password')->toString();

        $user = User::query()
            ->where('email', $email)
            ->first();

        if ($user && ! $user->is_active) {
            return response()->json([
                'message' => 'Your account has been blocked. Please contact support.',
                'errors' => [
                    'email' => ['Your account has been blocked. Please contact support.'],
                ],
            ], 403);
        }

        $credentials = [
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ];

        $remember = (bool) $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return response()->json([
                'message' => 'The provided credentials do not match our records.',
                'errors' => [
                    'email' => ['The provided credentials do not match our records.'],
                ],
            ], 422);
        }

        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user->fresh()->load('roles'),
        ]);
    }
}
