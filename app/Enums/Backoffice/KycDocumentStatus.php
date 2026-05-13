<?php

namespace App\Enums\Backoffice;

enum KycDocumentStatus: string
{
    case PENDING_REVIEW = 'PENDING_REVIEW';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
}
