<?php

namespace App\Enums;

enum FinanceRequestUpdateItemStatus: string
{
    case PENDING = 'pending';
    case UPDATED = 'updated';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
}
