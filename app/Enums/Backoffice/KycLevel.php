<?php

namespace App\Enums\Backoffice;

enum KycLevel: string
{
    case KYC_NONE = 'KYC_NONE';
    case KYC_BASIC = 'KYC_BASIC';
    case KYC_VERIFIED = 'KYC_VERIFIED';
    case KYC_ENHANCED = 'KYC_ENHANCED';
}
