<?php

namespace App\Enums;

enum RequestAdditionalDocumentStatus: string
{
    case PENDING = 'pending';
    case UPLOADED = 'uploaded';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
}
