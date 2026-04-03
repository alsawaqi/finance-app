<?php

namespace App\Enums;

enum FinanceRequestUpdateBatchStatus: string
{
    case OPEN = 'open';
    case PARTIALLY_COMPLETED = 'partially_completed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
