<?php

namespace App\Services;

use App\Enums\FinanceRequestStaffQuestionStatus;
use App\Enums\FinanceRequestUnderstudyStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestStaffQuestion;
use App\Models\User;
use App\Support\RequestTimelineLogger;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class FinanceRequestStaffQuestionService
{
    public function summary(FinanceRequest $financeRequest): array
    {
        $questions = $financeRequest->relationLoaded('staffQuestions')
            ? $financeRequest->staffQuestions
            : $financeRequest->staffQuestions()->get();

        $total = $questions->count();
        $required = $questions->where('is_required', true)->count();
        $pending = $questions->where('status', FinanceRequestStaffQuestionStatus::PENDING)->count();
        $answered = $questions->where('status', FinanceRequestStaffQuestionStatus::ANSWERED)->count();
        $closed = $questions->where('status', FinanceRequestStaffQuestionStatus::CLOSED)->count();
        $pendingRequired = $questions
            ->where('is_required', true)
            ->where('status', FinanceRequestStaffQuestionStatus::PENDING)
            ->count();

        return [
            'total' => $total,
            'required_total' => $required,
            'pending_total' => $pending,
            'answered_total' => $answered,
            'closed_total' => $closed,
            'pending_required_total' => $pendingRequired,
            'all_required_answered' => $pendingRequired === 0,
            'can_advance_from_understudy' => $pendingRequired === 0,
        ];
    }

    public function hasPendingRequiredQuestions(FinanceRequest $financeRequest): bool
    {
        return $this->pendingRequiredCount($financeRequest) > 0;
    }

    public function pendingRequiredCount(FinanceRequest $financeRequest): int
    {
        return $financeRequest->staffQuestions()
            ->where('is_required', true)
            ->where('status', FinanceRequestStaffQuestionStatus::PENDING)
            ->count();
    }

    public function answerQuestion(
        FinanceRequest $financeRequest,
        FinanceRequestStaffQuestion $staffQuestion,
        User $actor,
        array $validated,
    ): FinanceRequestStaffQuestion {
        $this->assertQuestionBelongsToRequest($financeRequest, $staffQuestion);

        [$answerText, $answerJson] = $this->normalizeAnswerPayload($staffQuestion, $validated);

        $staffQuestion->forceFill([
            'answer_text' => $answerText,
            'answer_json' => $answerJson,
            'status' => FinanceRequestStaffQuestionStatus::ANSWERED,
            'answered_at' => now(),
            'closed_at' => null,
        ])->save();

        $financeRequest->forceFill([
            'latest_activity_at' => now(),
        ])->save();

        RequestTimelineLogger::log(
            $financeRequest,
            'staff_question.answered',
            $actor->id,
            'Staff study question answered',
            'تمت الإجابة على سؤال الدراسة من الموظف',
            'A staff study question was answered: ' . $staffQuestion->question_text_en,
            'تمت الإجابة على أحد أسئلة الدراسة: ' . ($staffQuestion->question_text_ar ?: $staffQuestion->question_text_en),
            [
                'staff_question_id' => $staffQuestion->id,
                'template_id' => $staffQuestion->finance_staff_question_template_id,
                'assigned_to' => $staffQuestion->assigned_to,
                'question_type' => $staffQuestion->question_type,
            ],
        );

        return $staffQuestion->fresh([
            'asker:id,name,email',
            'assignedStaff:id,name,email',
            'template:id,code,question_text_en,question_text_ar,question_type,is_required,is_active,sort_order',
        ]);
    }

    public function reviewQuestion(
        FinanceRequest $financeRequest,
        FinanceRequestStaffQuestion $staffQuestion,
        User $actor,
        string $action,
        ?string $reviewNote = null,
    ): FinanceRequestStaffQuestion {
        $this->assertQuestionBelongsToRequest($financeRequest, $staffQuestion);

        $metadata = $staffQuestion->metadata_json ?? [];
        $reviews = Arr::get($metadata, 'reviews', []);
        $reviews[] = [
            'action' => $action,
            'note' => $reviewNote,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now()->toISOString(),
        ];
        $metadata['reviews'] = $reviews;

        if ($action === 'close') {
            if (blank($staffQuestion->answer_text) && blank($staffQuestion->answer_json)) {
                throw ValidationException::withMessages([
                    'action' => 'This question cannot be closed before the staff member provides an answer.',
                ]);
            }

            $staffQuestion->forceFill([
                'status' => FinanceRequestStaffQuestionStatus::CLOSED,
                'closed_at' => now(),
                'metadata_json' => $metadata,
            ])->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'staff_question.closed',
                $actor->id,
                'Staff study question reviewed and closed',
                'تمت مراجعة سؤال الدراسة وإغلاقه',
                'The admin reviewed and closed a staff study question.',
                'قام المسؤول بمراجعة أحد أسئلة الدراسة وإغلاقه.',
                [
                    'staff_question_id' => $staffQuestion->id,
                    'review_note' => $reviewNote,
                ],
            );
        } else {
            $staffQuestion->forceFill([
                'status' => FinanceRequestStaffQuestionStatus::PENDING,
                'closed_at' => null,
                'metadata_json' => $metadata,
            ])->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'staff_question.reopened',
                $actor->id,
                'Staff study question reopened',
                'تمت إعادة فتح سؤال الدراسة',
                'The admin reopened a staff study question for further follow-up.',
                'قام المسؤول بإعادة فتح أحد أسئلة الدراسة لمتابعته مرة أخرى.',
                [
                    'staff_question_id' => $staffQuestion->id,
                    'review_note' => $reviewNote,
                ],
            );
        }

        $financeRequest->forceFill([
            'latest_activity_at' => now(),
        ])->save();

        return $staffQuestion->fresh([
            'asker:id,name,email',
            'assignedStaff:id,name,email',
            'template:id,code,question_text_en,question_text_ar,question_type,is_required,is_active,sort_order',
        ]);
    }


    public function canSubmitStudy(FinanceRequest $financeRequest): bool
    {
        return $this->pendingRequiredCount($financeRequest) === 0;
    }

    public function submitStudy(
        FinanceRequest $financeRequest,
        User $actor,
        string $note,
    ): FinanceRequest {
        $note = trim($note);

        if ($note === '') {
            throw ValidationException::withMessages([
                'understudy_note' => 'Please add a short study note before submitting to admin.',
            ]);
        }

        $pendingRequired = $this->pendingRequiredCount($financeRequest);

        if ($pendingRequired > 0) {
            throw ValidationException::withMessages([
                'staff_questions' => 'All required staff study questions must be answered before submitting to admin.',
            ]);
        }

        $financeRequest->forceFill([
            'understudy_status' => FinanceRequestUnderstudyStatus::SUBMITTED,
            'understudy_note' => $note,
            'understudy_submitted_by' => $actor->id,
            'understudy_submitted_at' => now(),
            'understudy_reviewed_by' => null,
            'understudy_reviewed_at' => null,
            'understudy_review_note' => null,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_UNDERSTUDY_REVIEW,
            'latest_activity_at' => now(),
        ])->save();

        RequestTimelineLogger::log(
            $financeRequest,
            'understudy.submitted',
            $actor->id,
            'Understudy submitted to admin',
            'تم إرسال الدراسة إلى المسؤول',
            'The staff member completed the study answers and submitted the understudy package to the admin.',
            'أكمل الموظف إجابات الدراسة وأرسل ملف الدراسة إلى المسؤول.',
            [
                'understudy_status' => FinanceRequestUnderstudyStatus::SUBMITTED->value,
                'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_UNDERSTUDY_REVIEW->value,
            ],
        );

        return $financeRequest->fresh();
    }

    public function saveStudyDraft(
        FinanceRequest $financeRequest,
        User $actor,
        ?string $note,
    ): FinanceRequest {
        $financeRequest->forceFill([
            'understudy_status' => FinanceRequestUnderstudyStatus::DRAFT,
            'understudy_note' => $note !== null ? trim($note) : null,
            'understudy_reviewed_by' => null,
            'understudy_reviewed_at' => null,
            'understudy_review_note' => null,
            'latest_activity_at' => now(),
        ])->save();

        RequestTimelineLogger::log(
            $financeRequest,
            'understudy.draft_saved',
            $actor->id,
            'Understudy draft saved',
            'تم حفظ مسودة الدراسة',
            'The staff member saved the understudy note draft.',
            'قام الموظف بحفظ مسودة ملاحظة الدراسة.',
            [
                'understudy_status' => FinanceRequestUnderstudyStatus::DRAFT->value,
            ],
        );

        return $financeRequest->fresh();
    }

    public function reviewStudy(
        FinanceRequest $financeRequest,
        User $actor,
        string $action,
        ?string $reviewNote = null,
    ): FinanceRequest {
        if ($financeRequest->understudy_status !== FinanceRequestUnderstudyStatus::SUBMITTED) {
            throw ValidationException::withMessages([
                'understudy_status' => 'The understudy package must be submitted before the admin can review it.',
            ]);
        }

        $targetStatus = $action === 'approve'
            ? FinanceRequestUnderstudyStatus::APPROVED
            : FinanceRequestUnderstudyStatus::REJECTED;

        $targetStage = $action === 'approve'
            ? FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT
            : FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS;

        $financeRequest->forceFill([
            'understudy_status' => $targetStatus,
            'understudy_reviewed_by' => $actor->id,
            'understudy_reviewed_at' => now(),
            'understudy_review_note' => $reviewNote ? trim($reviewNote) : null,
            'workflow_stage' => $targetStage,
            'latest_activity_at' => now(),
        ])->save();

        RequestTimelineLogger::log(
            $financeRequest,
            $action === 'approve' ? 'understudy.approved' : 'understudy.rejected',
            $actor->id,
            $action === 'approve' ? 'Understudy approved by admin' : 'Understudy returned to staff',
            $action === 'approve' ? 'تم اعتماد الدراسة من المسؤول' : 'تمت إعادة الدراسة إلى الموظف',
            $reviewNote ?: ($action === 'approve'
                ? 'The admin approved the understudy package and moved the request to agent assignment.'
                : 'The admin rejected the understudy package and returned it to staff for updates.'),
            $reviewNote ?: ($action === 'approve'
                ? 'اعتمد المسؤول ملف الدراسة ونقل الطلب إلى مرحلة تعيين الوكلاء.'
                : 'رفض المسؤول ملف الدراسة وأعاده إلى الموظف لإجراء التعديلات.'),
            [
                'understudy_status' => $targetStatus->value,
                'workflow_stage' => $targetStage->value,
            ],
        );

        return $financeRequest->fresh();
    }

    private function assertQuestionBelongsToRequest(FinanceRequest $financeRequest, FinanceRequestStaffQuestion $staffQuestion): void
    {
        if ((int) $staffQuestion->finance_request_id !== (int) $financeRequest->id) {
            throw ValidationException::withMessages([
                'staff_question_id' => 'The selected staff question does not belong to this request.',
            ]);
        }
    }

    private function normalizeAnswerPayload(FinanceRequestStaffQuestion $staffQuestion, array $validated): array
    {
        $type = (string) $staffQuestion->question_type;
        $answerText = isset($validated['answer_text']) ? trim((string) $validated['answer_text']) : null;
        $answerJson = $validated['answer_json'] ?? null;

        if (is_array($answerJson)) {
            $answerJson = collect($answerJson)
                ->map(fn ($value) => trim((string) $value))
                ->filter(fn ($value) => $value !== '')
                ->values()
                ->all();

            if ($answerJson === []) {
                $answerJson = null;
            }
        }

        $allowedOptions = collect($staffQuestion->options_json ?? [])
            ->map(fn ($option) => trim((string) $option))
            ->filter(fn ($option) => $option !== '')
            ->values();

        if (in_array($type, ['checkbox'], true)) {
            if (! is_array($answerJson) || $answerJson === []) {
                throw ValidationException::withMessages([
                    'answer_json' => 'Please select at least one answer option for this question.',
                ]);
            }

            if ($allowedOptions->isNotEmpty()) {
                $invalid = collect($answerJson)->first(fn ($value) => ! $allowedOptions->contains($value));
                if ($invalid !== null) {
                    throw ValidationException::withMessages([
                        'answer_json' => 'Please select only the available answer options for this question.',
                    ]);
                }
            }

            return [implode(', ', $answerJson), $answerJson];
        }

        if (in_array($type, ['select', 'radio'], true)) {
            if (blank($answerText)) {
                throw ValidationException::withMessages([
                    'answer_text' => 'Please provide an answer for this question.',
                ]);
            }

            if ($allowedOptions->isNotEmpty() && ! $allowedOptions->contains($answerText)) {
                throw ValidationException::withMessages([
                    'answer_text' => 'Please choose one of the available answer options for this question.',
                ]);
            }

            return [$answerText, null];
        }

        if (blank($answerText)) {
            throw ValidationException::withMessages([
                'answer_text' => 'Please provide an answer for this question.',
            ]);
        }

        return [$answerText, null];
    }
}