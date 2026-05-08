<?php

namespace App\Enums\Backoffice;

enum MerchantCategory: string
{
    case RETAIL = 'RETAIL';
    case FOOD = 'FOOD';
    case SERVICE = 'SERVICE';
    case TELECOM = 'TELECOM';
    case UTILITY = 'UTILITY';
    case OTHER = 'OTHER';
}
