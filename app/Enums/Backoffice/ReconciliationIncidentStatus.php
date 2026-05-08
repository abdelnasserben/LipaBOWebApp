<?php

namespace App\Enums\Backoffice;

enum ReconciliationIncidentStatus: string
{
    case OPEN = 'OPEN';
    case UNDER_INVESTIGATION = 'UNDER_INVESTIGATION';
    case RESOLVED = 'RESOLVED';
    case CLOSED = 'CLOSED';
}
