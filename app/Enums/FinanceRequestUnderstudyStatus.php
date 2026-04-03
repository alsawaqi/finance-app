<?php

namespace App\Enums;

enum FinanceRequestUnderstudyStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
