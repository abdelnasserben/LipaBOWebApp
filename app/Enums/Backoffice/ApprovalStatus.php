<?php

namespace App\Enums\Backoffice;

enum ApprovalStatus: string
{
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case EXPIRED = 'EXPIRED';
}
