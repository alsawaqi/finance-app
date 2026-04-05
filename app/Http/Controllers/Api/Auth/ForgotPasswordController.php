<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $email = strtolower(trim($request->string('email')->toString()));

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

        $status = Password::sendResetLink(
            ['email' => $email]
        );

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => __($status),
                'errors' => [
                    'email' => [__($status)],
                ],
            ], 422);
        }

        return response()->json([
            'message' => __($status),
        ]);
    }
}
