<?php

namespace App\Enums\Backoffice;

enum ProcessingAssignmentStatus: string
{
    case ACTIVE = 'ACTIVE';
    case RELEASED = 'RELEASED';
    case EXPIRED = 'EXPIRED';
}
