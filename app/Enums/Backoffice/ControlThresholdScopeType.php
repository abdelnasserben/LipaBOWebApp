<?php

namespace App\Enums\Backoffice;

enum ControlThresholdScopeType: string
{
    case GLOBAL = 'GLOBAL';
    case LIMIT_PROFILE = 'LIMIT_PROFILE';
    case MERCHANT = 'MERCHANT';
    case AGENT = 'AGENT';
    case CUSTOMER = 'CUSTOMER';
}
