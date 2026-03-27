<?php

namespace App\Enums;

enum RequestEmailDirection: string
{
    case OUTBOUND = 'outbound';
    case INBOUND = 'inbound';
}