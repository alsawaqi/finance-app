<?php

namespace App\Jobs;

use App\Services\FinanceRequestEmailService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class SendFinanceRequestEmailJob
{
    use Dispatchable;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $requestEmailId,
    ) {
    }

    public function handle(FinanceRequestEmailService $financeRequestEmailService): void
    {
        $financeRequestEmailService->deliverQueuedEmail($this->requestEmailId);
    }
}
