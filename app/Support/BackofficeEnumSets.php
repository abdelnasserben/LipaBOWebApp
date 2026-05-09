<?php

namespace App\Support;

use App\Enums\Backoffice\ActorType;
use App\Enums\Backoffice\ApprovalType;
use App\Enums\Backoffice\BackofficeRole;
use App\Enums\Backoffice\KycLevel;
use App\Enums\Backoffice\ReportType;
use App\Enums\Backoffice\SettlementMode;
use App\Enums\Backoffice\TransactionType;

final class BackofficeEnumSets
{
    public static function manageableBackofficeRoles(): array
    {
        return [
            BackofficeRole::OPERATOR->value,
            BackofficeRole::SUPERVISOR->value,
            BackofficeRole::COMPLIANCE->value,
            BackofficeRole::ADMIN->value,
        ];
    }

    public static function grantableKycLevels(): array
    {
        return [
            KycLevel::KYC_BASIC->value,
            KycLevel::KYC_VERIFIED->value,
            KycLevel::KYC_ENHANCED->value,
        ];
    }

    public static function ruleTransactionTypes(): array
    {
        return [
            TransactionType::CASH_IN->value,
            TransactionType::CASH_OUT->value,
            TransactionType::PAYMENT->value,
            TransactionType::P2P_TRANSFER->value,
            TransactionType::MERCHANT_TO_MERCHANT->value,
            TransactionType::SERVICE_PAYMENT->value,
            TransactionType::CARD_SALE->value,
            TransactionType::CARD_REPLACEMENT->value,
            TransactionType::AGENT_FUND_IN->value,
            TransactionType::AGENT_FUND_OUT->value,
        ];
    }

    public static function commissionTransactionTypes(): array
    {
        return [
            TransactionType::CASH_IN->value,
            TransactionType::CASH_OUT->value,
            TransactionType::CARD_SALE->value,
            TransactionType::CARD_REPLACEMENT->value,
        ];
    }

    public static function thresholdTransactionTypes(): array
    {
        return [
            TransactionType::CASH_IN->value,
            TransactionType::CASH_OUT->value,
            TransactionType::PAYMENT->value,
            TransactionType::MERCHANT_TO_MERCHANT->value,
            TransactionType::P2P_TRANSFER->value,
            TransactionType::SERVICE_PAYMENT->value,
            TransactionType::AGENT_FUND_IN->value,
            TransactionType::AGENT_FUND_OUT->value,
        ];
    }

    public static function operationalActorTypes(): array
    {
        return [
            ActorType::CUSTOMER->value,
            ActorType::AGENT->value,
            ActorType::MERCHANT->value,
        ];
    }

    public static function thresholdApprovalTypes(): array
    {
        return [
            ApprovalType::LARGE_CASH_OUT->value,
            ApprovalType::AGENT_FUND_IN->value,
            ApprovalType::AGENT_FUND_OUT->value,
            ApprovalType::REVERSAL->value,
        ];
    }

    public static function commissionSettlementModes(): array
    {
        return [
            SettlementMode::BATCH_DAILY->value,
            SettlementMode::BATCH_WEEKLY->value,
        ];
    }

    public static function reportExportTypes(): array
    {
        return [
            ReportType::TRANSACTION_SUMMARY->value,
            ReportType::KYC_SUMMARY->value,
            ReportType::AML_LARGE_TRANSACTIONS->value,
            ReportType::FLOAT_REPORT->value,
            ReportType::ACTOR_SUMMARY->value,
        ];
    }
}
