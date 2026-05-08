<?php

namespace App\Enums\Backoffice;

enum ReconciliationStatus: string
{
    case OK = 'OK';
    case MISMATCH = 'MISMATCH';
}
