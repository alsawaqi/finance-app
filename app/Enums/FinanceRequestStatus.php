<?php

namespace App\Enums;

enum FinanceRequestStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case ACTIVE = 'active';
    case ON_HOLD = 'on_hold';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case BLOCKED = 'blocked';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}