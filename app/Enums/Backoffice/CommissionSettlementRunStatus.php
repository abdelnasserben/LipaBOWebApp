<?php

namespace App\Enums\Backoffice;

enum CommissionSettlementRunStatus: string
{
    case COMPLETED = 'COMPLETED';
    case PARTIAL_FAILURE = 'PARTIAL_FAILURE';
    case NO_PAYOUTS = 'NO_PAYOUTS';
    case FAILED = 'FAILED';
}
