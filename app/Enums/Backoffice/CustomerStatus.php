<?php

namespace App\Enums\Backoffice;

enum CustomerStatus: string
{
    case PENDING_KYC = 'PENDING_KYC';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case FROZEN = 'FROZEN';
    case CLOSED = 'CLOSED';
}
