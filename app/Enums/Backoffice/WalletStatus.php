<?php

namespace App\Enums\Backoffice;

enum WalletStatus: string
{
    case ACTIVE = 'ACTIVE';
    case FROZEN = 'FROZEN';
    case SUSPENDED = 'SUSPENDED';
    case CLOSED = 'CLOSED';
}
