<?php

namespace App\Enums\Backoffice;

enum CommissionCalculationType: string
{
    case ON_TRANSACTION_AMOUNT = 'ON_TRANSACTION_AMOUNT';
    case ON_FEE_AMOUNT = 'ON_FEE_AMOUNT';
    case FLAT = 'FLAT';
}
