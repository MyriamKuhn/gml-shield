<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'ADMIN';
    case DISPATCHER = 'DISPATCHER';
    case DRIVER = 'DRIVER';
}
