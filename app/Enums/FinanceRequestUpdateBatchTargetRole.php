<?php

namespace App\Enums;

enum FinanceRequestUpdateBatchTargetRole: string
{
    case CLIENT = 'client';
    case ADMIN = 'admin';
    case BOTH = 'both';
}
