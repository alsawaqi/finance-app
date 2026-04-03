<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateUserMailSettingsRequest;
use App\Models\User;
use App\Services\UserMailboxMailerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class StaffMailboxSettingsController extends Controller
{
    public function __construct(
        private readonly UserMailboxMailerService $mailboxMailer,
    ) {
    }

    public function index(): JsonResponse
    {
        $staff = User::query()
            ->where('account_type', UserAccountType::STAFF->value)
            ->orWhereHas('roles', fn ($query) => $query->where('name', 'staff'))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => (bool) $user->is_active,
                'mailbox_settings' => $user->mailboxSettingsSummary(),
            ])
            ->values();

        return response()->json([
            'staff' => $staff,
        ]);
    }

    public function show(User $staffUser): JsonResponse
    {
        $this->ensureStaffUser($staffUser);

        return response()->json([
            'settings' => $this->mailboxMailer->summary($staffUser),
            'staff_user' => $this->serializeStaffUser($staffUser),
        ]);
    }

    public function update(UpdateUserMailSettingsRequest $request, User $staffUser): JsonResponse
    {
        $this->ensureStaffUser($staffUser);

        $validated = $request->validated();
        $normalizedUsername = trim((string) ($validated['smtp_username'] ?? ''));
        $normalizedSenderName = trim((string) ($validated['smtp_sender_name'] ?? ''));
        $removePassword = (bool) ($validated['remove_smtp_password'] ?? false);
        $newPasswordProvided = filled($validated['smtp_password'] ?? null);

        $originalUsername = trim((string) ($staffUser->smtp_username ?? ''));
        $effectiveOriginalUsername = $originalUsername !== '' ? $originalUsername : trim((string) $staffUser->email);
        $effectiveNewUsername = $normalizedUsername !== '' ? $normalizedUsername : trim((string) $staffUser->email);

        $transportChanged = $effectiveOriginalUsername !== $effectiveNewUsername || $newPasswordProvided || $removePassword;

        $staffUser->smtp_username = $normalizedUsername !== '' && strcasecmp($normalizedUsername, (string) $staffUser->email) !== 0
            ? $normalizedUsername
            : null;
        $staffUser->smtp_sender_name = $normalizedSenderName !== '' ? $normalizedSenderName : null;

        if ($newPasswordProvided) {
            $staffUser->smtp_password = (string) $validated['smtp_password'];
        }

        if ($removePassword) {
            $staffUser->smtp_password = null;
        }

        if ($transportChanged) {
            $staffUser->smtp_enabled = false;
            $staffUser->smtp_verified_at = null;
            $staffUser->smtp_last_error = null;
        }

        $staffUser->save();

        return response()->json([
            'message' => 'Mailbox settings saved successfully for the selected staff member. Run the mailbox test before request emails are sent.',
            'settings' => $this->mailboxMailer->summary($staffUser->fresh()),
            'staff_user' => $this->serializeStaffUser($staffUser->fresh()),
        ]);
    }

    public function test(Request $request, User $staffUser): JsonResponse
    {
        $this->ensureStaffUser($staffUser);

        try {
            $this->mailboxMailer->sendTestMessage($staffUser);

            $staffUser->forceFill([
                'smtp_enabled' => true,
                'smtp_verified_at' => now(),
                'smtp_last_error' => null,
            ])->save();

            return response()->json([
                'message' => 'Mailbox test email sent successfully. The staff mailbox is now verified for request emails.',
                'settings' => $this->mailboxMailer->summary($staffUser->fresh()),
                'staff_user' => $this->serializeStaffUser($staffUser->fresh()),
            ]);
        } catch (Throwable $exception) {
            $staffUser->forceFill([
                'smtp_enabled' => false,
                'smtp_verified_at' => null,
                'smtp_last_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    private function serializeStaffUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => (bool) $user->is_active,
            'mailbox_settings' => $user->mailboxSettingsSummary(),
        ];
    }

    private function ensureStaffUser(User $user): void
    {
        $isStaff = ($user->account_type instanceof UserAccountType && $user->account_type === UserAccountType::STAFF)
            || $user->hasRole('staff');

        abort_unless($isStaff, 404);
    }
}
