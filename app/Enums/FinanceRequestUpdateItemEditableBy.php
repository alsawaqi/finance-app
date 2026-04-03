<?php

namespace App\Enums;

enum FinanceRequestUpdateItemEditableBy: string
{
    case CLIENT = 'client';
    case ADMIN = 'admin';
    case BOTH = 'both';
}
