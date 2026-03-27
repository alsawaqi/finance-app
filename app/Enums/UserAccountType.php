<?php

namespace App\Enums;

enum UserAccountType: string
{
    case ADMIN = 'admin';
    case STAFF = 'staff';
    case CLIENT = 'client';
}