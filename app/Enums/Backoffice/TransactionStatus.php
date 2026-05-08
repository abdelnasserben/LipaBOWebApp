<?php

namespace App\Enums\Backoffice;

enum TransactionStatus: string
{
    case PENDING = 'PENDING';
    case AUTHORIZED = 'AUTHORIZED';
    case COMPLETED = 'COMPLETED';
    case DECLINED = 'DECLINED';
    case EXPIRED = 'EXPIRED';
    case REVERSED = 'REVERSED';
}
