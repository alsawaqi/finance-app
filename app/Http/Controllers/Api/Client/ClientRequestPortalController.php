<?php

namespace App\Http\Controllers\Api\Client;

use App\Enums\FinanceRequestPriority;
use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientFinanceRequestRequest;
use App\Models\FinanceRequest;
use App\Models\RequestAnswer;
use App\Models\RequestAttachment;
use App\Models\RequestQuestion;
use App\Models\RequestTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientRequestPortalController extends Controller
{
    public function questions(): JsonResponse
    {
        $questions = RequestQuestion::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (RequestQuestion $question) => [
                'id' => $question->id,
                'code' => $question->code,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'options_json' => $question->options_json ?? [],
                'placeholder' => $question->placeholder,
                'help_text' => $question->help_text,
                'validation_rules' => $question->validation_rules,
                'is_required' => (bool) $question->is_required,
                'sort_order' => $question->sort_order,
            ])
            ->values();

        return response()->json([
            'questions' => $questions,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $requests = FinanceRequest::query()
            ->with(['currentContract:id,finance_request_id,status,admin_signed_at,client_signed_at'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'requests' => $requests,
        ]);
    }

    public function store(StoreClientFinanceRequestRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $details = Arr::get($validated, 'details', []);
        $answers = collect(Arr::get($validated, 'answers', []));
        $now = now();

        $financeRequest = DB::transaction(function () use ($request, $user, $details, $answers, $now) {
            $fullName = trim((string) Arr::get($details, 'full_name', Arr::get($details, 'name', '')));
            $countryCode = trim((string) Arr::get($details, 'country_code', Arr::get($details, 'country', '')));
            $requestedAmount = (float) Arr::get($details, 'requested_amount', 0);
            $financeType = Arr::get($details, 'finance_type');
            $notes = Arr::get($details, 'notes');

            $financeRequest = FinanceRequest::create([
                'reference_number' => 'TMP-' . Str::upper(Str::random(12)),
                'user_id' => $user->id,
                'status' => FinanceRequestStatus::SUBMITTED,
                'workflow_stage' => FinanceRequestWorkflowStage::REVIEW,
                'priority' => FinanceRequestPriority::NORMAL,
                'submitted_at' => $now,
                'latest_activity_at' => $now,
                'intake_details_json' => [
                    'full_name' => $fullName,
                    'country_code' => $countryCode,
                    'requested_amount' => $requestedAmount,
                    'finance_type' => $financeType,
                    'notes' => $notes,
                    'name' => $fullName,
                    'country' => $countryCode,
                ],
            ]);

            $financeRequest->forceFill([
                'reference_number' => $this->buildReferenceNumber($financeRequest),
            ])->save();

            foreach ($answers as $answer) {
                $questionId = (int) Arr::get($answer, 'question_id');
                $value = Arr::get($answer, 'value');

                [$answerText, $answerValueJson] = $this->normalizeAnswerPayload($value);

                RequestAnswer::create([
                    'finance_request_id' => $financeRequest->id,
                    'question_id' => $questionId,
                    'answer_text' => $answerText,
                    'answer_value_json' => $answerValueJson,
                    'answered_by' => $user->id,
                ]);
            }

            $storedAttachments = [];
            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store('request-attachments/initial-submission', 'public');

                $storedAttachments[] = RequestAttachment::create([
                    'finance_request_id' => $financeRequest->id,
                    'category' => 'initial_submission',
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'disk' => 'public',
                    'mime_type' => $file->getClientMimeType(),
                    'file_extension' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => $user->id,
                ]);
            }

            RequestTimeline::create([
                'finance_request_id' => $financeRequest->id,
                'actor_user_id' => $user->id,
                'event_type' => 'request_submitted',
                'event_title' => 'Request submitted',
                'event_description' => 'Client submitted a new finance request and it is now waiting for admin review.',
                'metadata_json' => [
                    'reference_number' => $financeRequest->reference_number,
                    'answer_count' => $answers->count(),
                    'attachment_count' => count($storedAttachments),
                    'finance_type' => $financeType,
                    'requested_amount' => $requestedAmount,
                    'country_code' => $countryCode,
                ],
                'created_at' => $now,
            ]);

            return $financeRequest->fresh(['answers.question', 'attachments', 'timeline']);
        });

        return response()->json([
            'message' => 'Request submitted successfully.',
            'request' => $this->transformRequestDetails($financeRequest),
        ], 201);
    }

    public function show(FinanceRequest $financeRequest): JsonResponse
    {
        abort_unless((int) $financeRequest->user_id === (int) auth()->id(), 403);

        $financeRequest->load([
            'client:id,name,email,phone',
            'answers.question:id,code,question_text,question_type,sort_order',
            'attachments',
            'timeline.actor:id,name',
            'currentContract',
        ]);

        return response()->json([
            'request' => $financeRequest,
        ]);
    }

    private function buildReferenceNumber(FinanceRequest $financeRequest): string
    {
        return sprintf(
            'REQ-%s-%04d',
            Carbon::parse($financeRequest->created_at)->format('Ymd'),
            $financeRequest->id
        );
    }

    private function normalizeAnswerPayload(mixed $value): array
    {
        if (is_array($value)) {
            $clean = array_values(array_filter(array_map(function ($item) {
                if (is_scalar($item) || $item === null) {
                    return trim((string) $item);
                }

                return null;
            }, $value), fn ($item) => $item !== ''));

            return [
                implode(', ', $clean),
                $clean,
            ];
        }

        if (is_bool($value)) {
            return [$value ? 'Yes' : 'No', $value];
        }

        if ($value === null) {
            return [null, null];
        }

        return [trim((string) $value), $value];
    }

    private function transformRequestDetails(FinanceRequest $financeRequest): array
    {
        $details = $financeRequest->intake_details_json ?? [];
        $stage = $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage;
        $status = $financeRequest->status?->value ?? (string) $financeRequest->status;

        return [
            'id' => $financeRequest->id,
            'reference_number' => $financeRequest->reference_number,
            'status' => $status,
            'workflow_stage' => $stage,
            'priority' => $financeRequest->priority?->value ?? (string) $financeRequest->priority,
            'submitted_at' => optional($financeRequest->submitted_at)->toISOString(),
            'approved_at' => optional($financeRequest->approved_at)->toISOString(),
            'latest_activity_at' => optional($financeRequest->latest_activity_at)->toISOString(),
            'intake_details' => $details,
            'intake_details_json' => $details,
            'can_sign' => $stage === FinanceRequestWorkflowStage::CONTRACT->value,
            'can_upload_documents' => $stage === FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value,
            'answers' => $financeRequest->answers
                ->sortBy(fn (RequestAnswer $answer) => $answer->question?->sort_order ?? 0)
                ->values()
                ->map(fn (RequestAnswer $answer) => [
                    'id' => $answer->id,
                    'question_id' => $answer->question_id,
                    'question_text' => $answer->question?->question_text,
                    'question_type' => $answer->question?->question_type,
                    'answer_text' => $answer->answer_text,
                    'answer_value_json' => $answer->answer_value_json,
                ]),
            'attachments' => $financeRequest->attachments->map(fn (RequestAttachment $attachment) => [
                'id' => $attachment->id,
                'category' => $attachment->category,
                'file_name' => $attachment->file_name,
                'file_path' => $attachment->file_path,
                'disk' => $attachment->disk,
                'mime_type' => $attachment->mime_type,
                'file_extension' => $attachment->file_extension,
                'file_size' => $attachment->file_size,
                'uploaded_at' => optional($attachment->created_at)->toISOString(),
            ]),
            'timeline' => $financeRequest->timeline->map(fn (RequestTimeline $event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'event_title' => $event->event_title,
                'event_description' => $event->event_description,
                'metadata_json' => $event->metadata_json,
                'created_at' => optional($event->created_at)->toISOString(),
            ]),
        ];
    }
}
