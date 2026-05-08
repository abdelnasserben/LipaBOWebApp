<?php

namespace App\Enums\Backoffice;

enum ReportType: string
{
    case TRANSACTION_SUMMARY = 'TRANSACTION_SUMMARY';
    case KYC_SUMMARY = 'KYC_SUMMARY';
    case AML_LARGE_TRANSACTIONS = 'AML_LARGE_TRANSACTIONS';
    case FLOAT_REPORT = 'FLOAT_REPORT';
    case ACTOR_SUMMARY = 'ACTOR_SUMMARY';
}
