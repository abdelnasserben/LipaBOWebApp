<?php

namespace App\Enums\Backoffice;

enum UserStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case LOCKED = 'LOCKED';
    case CLOSED = 'CLOSED';
}
