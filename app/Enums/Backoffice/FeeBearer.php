<?php

namespace App\Enums\Backoffice;

enum FeeBearer: string
{
    case SENDER = 'SENDER';
    case RECEIVER = 'RECEIVER';
    case PLATFORM = 'PLATFORM';
}
