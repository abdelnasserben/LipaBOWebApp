<?php

namespace App\Enums\Backoffice;

enum BusinessType: string
{
    case SOLE_TRADER = 'SOLE_TRADER';
    case COMPANY = 'COMPANY';
    case NGO = 'NGO';
}
