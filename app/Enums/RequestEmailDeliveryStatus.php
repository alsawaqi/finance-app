<?php

namespace App\Enums;

enum RequestEmailDeliveryStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case FAILED = 'failed';
    case RECEIVED = 'received';
    case OPENED = 'opened';
    case BOUNCED = 'bounced';
}