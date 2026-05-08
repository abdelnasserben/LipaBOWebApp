<?php

namespace App\Enums\Backoffice;

enum BackofficeRole: string
{
    case OPERATOR = 'OPERATOR';
    case SUPERVISOR = 'SUPERVISOR';
    case COMPLIANCE = 'COMPLIANCE';
    case ADMIN = 'ADMIN';
    case SUPER_ADMIN = 'SUPER_ADMIN';
}
