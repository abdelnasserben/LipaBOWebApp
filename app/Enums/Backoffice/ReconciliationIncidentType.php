<?php

namespace App\Enums\Backoffice;

enum ReconciliationIncidentType: string
{
    case DOUBLE_ENTRY_MISMATCH = 'DOUBLE_ENTRY_MISMATCH';
    case BALANCE_MISMATCH = 'BALANCE_MISMATCH';
    case FLOAT_IDENTITY_BREACH = 'FLOAT_IDENTITY_BREACH';
}
