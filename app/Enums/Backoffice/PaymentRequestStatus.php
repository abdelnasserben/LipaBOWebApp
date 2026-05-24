<?php

namespace App\Enums\Backoffice;

enum PaymentRequestStatus: string
{
    case ACTIVE = 'ACTIVE';
    case PAID = 'PAID';
    case CANCELLED = 'CANCELLED';
    case EXPIRED = 'EXPIRED';
}
