<?php

namespace App\Enums;

enum FinanceRequestStaffQuestionStatus: string
{
    case PENDING = 'pending';
    case ANSWERED = 'answered';
    case CLOSED = 'closed';
}
