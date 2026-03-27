<?php

namespace App\Enums;

enum RequestDocumentUploadStatus: string
{
    case PENDING = 'pending';
    case UPLOADED = 'uploaded';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}