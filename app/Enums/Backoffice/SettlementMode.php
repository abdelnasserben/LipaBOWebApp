<?php

namespace App\Enums\Backoffice;

enum SettlementMode: string
{
    case IMMEDIATE = 'IMMEDIATE';
    case BATCH_DAILY = 'BATCH_DAILY';
    case BATCH_WEEKLY = 'BATCH_WEEKLY';
}
