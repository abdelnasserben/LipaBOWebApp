<?php

namespace App\Enums\Backoffice;

enum AgentStatus: string
{
    case PENDING_KYC = 'PENDING_KYC';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case CLOSED = 'CLOSED';
}
