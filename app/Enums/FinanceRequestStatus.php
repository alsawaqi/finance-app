<?php

namespace App\Enums;

enum FinanceRequestStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case ACTIVE = 'active';
    case ON_HOLD = 'on_hold';
    case REJECTED = 'rejected';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}