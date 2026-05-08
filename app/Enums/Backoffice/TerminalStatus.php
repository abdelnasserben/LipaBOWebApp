<?php

namespace App\Enums\Backoffice;

enum TerminalStatus: string
{
    case REGISTERED = 'REGISTERED';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case REVOKED = 'REVOKED';
}
