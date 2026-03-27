<?php

namespace App\Enums;

enum RequestCommentVisibility: string
{
    case ADMIN_ONLY = 'admin_only';
    case INTERNAL = 'internal';
    case CLIENT_VISIBLE = 'client_visible';
}