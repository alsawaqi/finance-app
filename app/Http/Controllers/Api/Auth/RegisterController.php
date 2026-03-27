<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => trim($request->string('name')->toString()),
            'email' => strtolower(trim($request->string('email')->toString())),
            'phone' => $request->filled('phone') ? trim((string) $request->input('phone')) : null,
            'password' => $request->string('password')->toString(),
            'account_type' => UserAccountType::CLIENT,
            'is_active' => true,
        ]);

        $user->assignRole('client');

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Registration successful.',
            'user' => $user->load('roles'),
        ], 201);
    }
}