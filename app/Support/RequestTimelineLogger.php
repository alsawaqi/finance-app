<?php

namespace App\Support;

use App\Models\FinanceRequest;
use App\Models\RequestTimeline;

class RequestTimelineLogger
{
    public static function log(
        FinanceRequest $financeRequest,
        string $eventType,
        ?int $actorUserId,
        string $titleEn,
        string $titleAr,
        ?string $descriptionEn = null,
        ?string $descriptionAr = null,
        array $metadata = [],
        $createdAt = null,
    ): RequestTimeline {
        return RequestTimeline::create([
            'finance_request_id' => $financeRequest->id,
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'event_title' => $titleEn,
            'event_title_en' => $titleEn,
            'event_title_ar' => $titleAr,
            'event_description' => $descriptionEn,
            'event_description_en' => $descriptionEn,
            'event_description_ar' => $descriptionAr,
            'metadata_json' => $metadata,
            'created_at' => $createdAt ?: now(),
        ]);
    }
}
