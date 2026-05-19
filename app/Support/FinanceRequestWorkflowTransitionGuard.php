<?php

namespace App\Support;

use App\Enums\FinanceRequestWorkflowStage;
use Illuminate\Validation\ValidationException;

class FinanceRequestWorkflowTransitionGuard
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            FinanceRequestWorkflowStage::QUESTIONNAIRE->value => [
                FinanceRequestWorkflowStage::REVIEW->value,
                FinanceRequestWorkflowStage::SUBMITTED_FOR_REVIEW->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::REVIEW->value => [
                FinanceRequestWorkflowStage::SUBMITTED_FOR_REVIEW->value,
                FinanceRequestWorkflowStage::ADMIN_CONTRACT_PREPARATION->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::SUBMITTED_FOR_REVIEW->value => [
                FinanceRequestWorkflowStage::ADMIN_CONTRACT_PREPARATION->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::ADMIN_CONTRACT_PREPARATION->value => [
                FinanceRequestWorkflowStage::AWAITING_CLIENT_SIGNATURE->value,
                FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::CONTRACT->value => [
                FinanceRequestWorkflowStage::AWAITING_CLIENT_SIGNATURE->value,
                FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::AWAITING_CLIENT_SIGNATURE->value => [
                FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD->value,
                FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD->value => [
                FinanceRequestWorkflowStage::AWAITING_CLIENT_SIGNATURE->value,
                FinanceRequestWorkflowStage::AWAITING_ADMIN_COMMERCIAL_REGISTRATION_UPLOAD->value,
                FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::AWAITING_ADMIN_COMMERCIAL_REGISTRATION_UPLOAD->value => [
                FinanceRequestWorkflowStage::AWAITING_CLIENT_SIGNATURE->value,
                FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD->value,
                FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value => [
                FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD->value,
                FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
                FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value => [
                FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
                FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value,
                FinanceRequestWorkflowStage::UNDERSTUDY->value,
                FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value => [
                FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value,
                FinanceRequestWorkflowStage::UNDERSTUDY->value,
                FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value => [
                FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
                FinanceRequestWorkflowStage::UNDERSTUDY->value,
                FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::UNDERSTUDY->value => [
                FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS->value,
                FinanceRequestWorkflowStage::AWAITING_UNDERSTUDY_REVIEW->value,
                FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS->value => [
                FinanceRequestWorkflowStage::UNDERSTUDY->value,
                FinanceRequestWorkflowStage::AWAITING_UNDERSTUDY_REVIEW->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::AWAITING_UNDERSTUDY_REVIEW->value => [
                FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS->value,
                FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value => [
                FinanceRequestWorkflowStage::PROCESSING->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::PROCESSING->value => [
                FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::READY_FOR_PROCESSING->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::COMPLETED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::READY_FOR_PROCESSING->value => [
                FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::PROCESSING->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::COMPLETED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::ASSIGNED_TO_STAFF->value => [
                FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
                FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value,
                FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
            FinanceRequestWorkflowStage::ACCEPTED->value => [
                FinanceRequestWorkflowStage::COMPLETED->value,
            ],
            FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value => [
                FinanceRequestWorkflowStage::SUBMITTED_FOR_REVIEW->value,
                FinanceRequestWorkflowStage::ADMIN_CONTRACT_PREPARATION->value,
                FinanceRequestWorkflowStage::AWAITING_CLIENT_SIGNATURE->value,
                FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD->value,
                FinanceRequestWorkflowStage::AWAITING_ADMIN_COMMERCIAL_REGISTRATION_UPLOAD->value,
                FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::CONTRACT->value,
                FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
                FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value,
                FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value,
                FinanceRequestWorkflowStage::UNDERSTUDY->value,
                FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS->value,
                FinanceRequestWorkflowStage::AWAITING_UNDERSTUDY_REVIEW->value,
                FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::PROCESSING->value,
                FinanceRequestWorkflowStage::READY_FOR_PROCESSING->value,
                FinanceRequestWorkflowStage::ASSIGNED_TO_STAFF->value,
                FinanceRequestWorkflowStage::REJECTED->value,
            ],
        ];
    }

    public static function assertCanTransition(mixed $from, mixed $to): void
    {
        $fromStage = self::normalizeStage($from);
        $toStage = self::normalizeStage($to);

        if (! $fromStage || ! $toStage || $fromStage === $toStage) {
            return;
        }

        $allowed = self::allowedTransitions()[$fromStage->value] ?? [];

        if (in_array($toStage->value, $allowed, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'workflow_stage' => sprintf(
                'The workflow stage cannot move directly from %s to %s.',
                $fromStage->value,
                $toStage->value,
            ),
        ]);
    }

    public static function assertCanManualTransition(mixed $from, mixed $to): void
    {
        self::assertCanTransition($from, $to);

        $fromStage = self::normalizeStage($from);
        $toStage = self::normalizeStage($to);

        if (! $fromStage || ! $toStage || $fromStage === $toStage) {
            return;
        }

        $manualTransitions = [
            FinanceRequestWorkflowStage::PROCESSING->value => [
                FinanceRequestWorkflowStage::READY_FOR_PROCESSING->value,
            ],
            FinanceRequestWorkflowStage::READY_FOR_PROCESSING->value => [
                FinanceRequestWorkflowStage::PROCESSING->value,
            ],
        ];

        if (in_array($toStage->value, $manualTransitions[$fromStage->value] ?? [], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'workflow_stage' => 'This workflow stage must be changed through the matching workflow action so required steps are not skipped.',
        ]);
    }

    private static function normalizeStage(mixed $stage): ?FinanceRequestWorkflowStage
    {
        if ($stage instanceof FinanceRequestWorkflowStage) {
            return $stage;
        }

        if (! is_string($stage) || trim($stage) === '') {
            return null;
        }

        return FinanceRequestWorkflowStage::tryFrom(trim($stage));
    }
}
