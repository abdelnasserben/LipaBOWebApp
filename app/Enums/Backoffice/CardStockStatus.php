<?php

namespace App\Enums\Backoffice;

enum CardStockStatus: string
{
    case IN_WAREHOUSE = 'IN_WAREHOUSE';
    case ASSIGNED_TO_AGENT = 'ASSIGNED_TO_AGENT';
    case SOLD = 'SOLD';
    case RETURNED = 'RETURNED';
    case SPOILED = 'SPOILED';
}
