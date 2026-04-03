<?php

namespace App\Services;

use App\Enums\FinanceRequestStaffQuestionStatus;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestStaffAssignment;
use App\Models\FinanceRequestStaffQuestion;
use App\Models\FinanceStaffQuestionTemplate;
use App\Support\RequestTimelineLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinanceRequestStaffQuestionTemplateService
{
    /**
     * Ensure active staff-question templates are instantiated for this request.
     *
     * @return Collection<int, FinanceRequestStaffQuestion>
     */
    public function ensureForRequest(FinanceRequest $financeRequest, ?int $actorUserId = null): Collection
    {
        $templates = FinanceStaffQuestionTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($templates->isEmpty()) {
            return collect();
        }

        $assignedTo = $this->resolveAssignedStaffId($financeRequest);
        $createdCount = 0;

        DB::transaction(function () use ($templates, $financeRequest, $assignedTo, &$createdCount): void {
            foreach ($templates as $template) {
                $existing = FinanceRequestStaffQuestion::query()
                    ->where('finance_request_id', $financeRequest->id)
                    ->where('finance_staff_question_template_id', $template->id)
                    ->first();

                if ($existing) {
                    continue;
                }

                FinanceRequestStaffQuestion::create([
                    'finance_request_id' => $financeRequest->id,
                    'finance_staff_question_template_id' => $template->id,
                    'asked_by' => null,
                    'assigned_to' => $assignedTo,
                    'question_code' => $template->code,
                    'question_text_en' => $template->question_text_en,
                    'question_text_ar' => $template->question_text_ar,
                    'question_type' => $template->question_type,
                    'options_json' => $template->options_json,
                    'placeholder_en' => $template->placeholder_en,
                    'placeholder_ar' => $template->placeholder_ar,
                    'help_text_en' => $template->help_text_en,
                    'help_text_ar' => $template->help_text_ar,
                    'validation_rules' => $template->validation_rules,
                    'status' => FinanceRequestStaffQuestionStatus::PENDING,
                    'is_required' => (bool) $template->is_required,
                    'sort_order' => (int) $template->sort_order,
                    'metadata_json' => [
                        'template_id' => $template->id,
                        'template_code' => $template->code,
                        'instantiated_from_template' => true,
                    ],
                    'asked_at' => now(),
                ]);

                $createdCount++;
            }
        });

        if ($createdCount > 0) {
            RequestTimelineLogger::log(
                $financeRequest,
                'staff_questions.instantiated',
                $actorUserId,
                'Staff study questions prepared',
                'تم تجهيز أسئلة الدراسة للموظف',
                "The fixed staff study questions were prepared for this request. {$createdCount} question(s) were created.",
                "تم تجهيز أسئلة الدراسة الثابتة لهذا الطلب. تم إنشاء {$createdCount} سؤال/أسئلة.",
                [
                    'created_count' => $createdCount,
                ],
            );
        }

        return FinanceRequestStaffQuestion::query()
            ->where('finance_request_id', $financeRequest->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function resolveAssignedStaffId(FinanceRequest $financeRequest): ?int
    {
        if (! empty($financeRequest->primary_staff_id)) {
            return (int) $financeRequest->primary_staff_id;
        }

        $primaryAssignment = FinanceRequestStaffAssignment::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('is_active', true)
            ->where('is_primary', true)
            ->latest('id')
            ->first();

        if ($primaryAssignment) {
            return (int) $primaryAssignment->staff_id;
        }

        $latestAssignment = FinanceRequestStaffAssignment::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        return $latestAssignment ? (int) $latestAssignment->staff_id : null;
    }
}