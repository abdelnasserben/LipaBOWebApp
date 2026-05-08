<?php

namespace App\Enums\Backoffice;

enum SuspenseDirection: string
{
    case TO_SUSPENSE = 'TO_SUSPENSE';
    case FROM_SUSPENSE = 'FROM_SUSPENSE';
}
