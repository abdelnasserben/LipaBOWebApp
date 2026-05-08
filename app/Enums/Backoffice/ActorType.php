<?php

namespace App\Enums\Backoffice;

enum ActorType: string
{
    case CUSTOMER = 'CUSTOMER';
    case MERCHANT = 'MERCHANT';
    case AGENT = 'AGENT';
    case MERCHANT_OPERATOR = 'MERCHANT_OPERATOR';
    case BACKOFFICE_USER = 'BACKOFFICE_USER';
    case SYSTEM = 'SYSTEM';
}
