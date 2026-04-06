<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Twilio\RegistrationWelcomeWhatsAppNotifier;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function __construct(
        private readonly RegistrationWelcomeWhatsAppNotifier $registrationWelcomeWhatsApp,
    ) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $countryCodeRaw = trim((string) $request->input('phone_country_code', ''));
        $phoneRaw = trim((string) $request->input('phone', ''));

        $countryCodeDigits = preg_replace('/\D+/', '', $countryCodeRaw) ?: '';
        $phoneDigits = preg_replace('/\D+/', '', $phoneRaw) ?: '';
        $normalizedCountryCode = $countryCodeDigits !== '' ? '+'.$countryCodeDigits : '';
        $normalizedLocalPhone = ltrim($phoneDigits, '0');
        if ($normalizedLocalPhone === '') {
            $normalizedLocalPhone = $phoneDigits;
        }
        $normalizedPhone = $phoneDigits !== ''
            ? trim(($normalizedCountryCode !== '' ? $normalizedCountryCode : '').$normalizedLocalPhone)
            : null;

        $user = User::create([
            'name' => trim($request->string('name')->toString()),
            'email' => strtolower(trim($request->string('email')->toString())),
            'phone' => $normalizedPhone,
            'password' => $request->string('password')->toString(),
            'account_type' => UserAccountType::CLIENT,
            'is_active' => true,
        ]);

        $user->assignRole('client');

        event(new Registered($user));

        $this->registrationWelcomeWhatsApp->notify(
            $user,
            $request->header('Accept-Language'),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Registration successful.',
            'user' => $user->load('roles'),
        ], 201);
    }
}
