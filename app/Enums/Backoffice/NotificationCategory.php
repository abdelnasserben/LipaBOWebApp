<?php

namespace App\Enums\Backoffice;

enum NotificationCategory: string
{
    case TRANSACTION = 'TRANSACTION';
    case BILL_PAYMENT = 'BILL_PAYMENT';
}
