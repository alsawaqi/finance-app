<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateUserMailSettingsRequest;
use App\Services\UserMailboxMailerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class UserMailSettingsController extends Controller
{
    public function __construct(
        private readonly UserMailboxMailerService $mailboxMailer,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('admin'), 403);

        return response()->json([
            'settings' => $this->mailboxMailer->summary($user),
        ]);
    }

    public function update(UpdateUserMailSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('admin'), 403);

        $validated = $request->validated();
        $normalizedUsername = trim((string) ($validated['smtp_username'] ?? ''));
        $normalizedSenderName = trim((string) ($validated['smtp_sender_name'] ?? ''));
        $removePassword = (bool) ($validated['remove_smtp_password'] ?? false);
        $newPasswordProvided = filled($validated['smtp_password'] ?? null);

        $originalUsername = trim((string) ($user->smtp_username ?? ''));
        $effectiveOriginalUsername = $originalUsername !== '' ? $originalUsername : trim((string) $user->email);
        $effectiveNewUsername = $normalizedUsername !== '' ? $normalizedUsername : trim((string) $user->email);

        $transportChanged = $effectiveOriginalUsername !== $effectiveNewUsername || $newPasswordProvided || $removePassword;

        $user->smtp_username = $normalizedUsername !== '' && strcasecmp($normalizedUsername, (string) $user->email) !== 0
            ? $normalizedUsername
            : null;
        $user->smtp_sender_name = $normalizedSenderName !== '' ? $normalizedSenderName : null;

        if ($newPasswordProvided) {
            $user->smtp_password = (string) $validated['smtp_password'];
        }

        if ($removePassword) {
            $user->smtp_password = null;
        }

        if ($transportChanged) {
            $user->smtp_enabled = false;
            $user->smtp_verified_at = null;
            $user->smtp_last_error = null;
        }

        $user->save();

        return response()->json([
            'message' => 'Mailbox settings saved successfully. Please run the mailbox test before sending request emails.',
            'settings' => $this->mailboxMailer->summary($user->fresh()),
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('admin'), 403);

        try {
            $this->mailboxMailer->sendTestMessage($user);

            $user->forceFill([
                'smtp_enabled' => true,
                'smtp_verified_at' => now(),
                'smtp_last_error' => null,
            ])->save();

            return response()->json([
                'message' => 'Mailbox test email sent successfully. Your mailbox is now verified for request emails.',
                'settings' => $this->mailboxMailer->summary($user->fresh()),
            ]);
        } catch (Throwable $exception) {
            $user->forceFill([
                'smtp_enabled' => false,
                'smtp_verified_at' => null,
                'smtp_last_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }
}
