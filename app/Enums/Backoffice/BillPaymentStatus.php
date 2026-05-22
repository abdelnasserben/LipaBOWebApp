<?php

namespace App\Enums\Backoffice;

enum BillPaymentStatus: string
{
    case QUEUED = 'QUEUED';
    case IN_PROCESSING = 'IN_PROCESSING';
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED_REFUNDED = 'FAILED_REFUNDED';
    case FAILED_RETRY = 'FAILED_RETRY';
}
