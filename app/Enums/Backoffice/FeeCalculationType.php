<?php

namespace App\Enums\Backoffice;

enum FeeCalculationType: string
{
    case FLAT = 'FLAT';
    case PERCENTAGE = 'PERCENTAGE';
    case TIERED = 'TIERED';
    case MAX_OF = 'MAX_OF';
    case MIN_OF = 'MIN_OF';
    case ZERO = 'ZERO';
}
