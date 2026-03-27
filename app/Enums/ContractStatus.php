<?php

namespace App\Enums;

enum ContractStatus: string
{
    case GENERATED = 'generated';
    case ADMIN_SIGNED = 'admin_signed';
    case CLIENT_SIGNED = 'client_signed';
    case FULLY_SIGNED = 'fully_signed';
    case SUPERSEDED = 'superseded';
    case VOIDED = 'voided';
}