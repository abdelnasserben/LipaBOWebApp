<?php

namespace App\Support;

use BackedEnum;

final class BackofficeEnums
{
    public static function label(?string $value, string $fallback = '-'): string
    {
        if ($value === null || trim($value) === '') {
            return $fallback;
        }

        $value = trim($value);

        $labels = [
            'KYC_NONE' => 'No KYC',
            'KYC_BASIC' => 'Basic KYC',
            'KYC_VERIFIED' => 'Verified KYC',
            'KYC_ENHANCED' => 'Enhanced KYC',
            'CASH_IN' => 'Cash-in',
            'CASH_OUT' => 'Cash-out',
            'P2P_TRANSFER' => 'P2P transfer',
            'MERCHANT_TO_MERCHANT' => 'Merchant to merchant',
            'AGENT_FUND_IN' => 'Agent fund in',
            'AGENT_FUND_OUT' => 'Agent fund out',
            'FEE_COLLECTION' => 'Fee collection',
            'COMMISSION_PAYOUT' => 'Commission payout',
            'CARD_SALE' => 'Card sale',
            'CARD_REPLACEMENT' => 'Card replacement',
            'PAYMENT_REQUEST' => 'Payment request',
            'SERVICE_PAYMENT' => 'Service payment',
            'BILL_PROVIDER_SETTLEMENT' => 'Bill provider settlement',
            'PLATFORM_REVENUE_WITHDRAWAL' => 'Platform revenue withdrawal',
            'PLATFORM_LIQUIDITY_TOP_UP' => 'Platform liquidity top-up',
            'PENDING_KYC' => 'Pending KYC',
            'PENDING_APPROVAL' => 'Pending approval',
            'SUPER_ADMIN' => 'Super admin',
            'BACKOFFICE_USER' => 'Backoffice user',
            'BACKOFFICE_UI' => 'Backoffice UI',
            'BACKOFFICE_JOB' => 'Backoffice job',
            'TERMINAL_NFC' => 'Terminal NFC',
            'TERMINAL_MANUAL' => 'Terminal manual',
            'MOBILE_APP' => 'Mobile app',
            'AGENT_CHANNEL' => 'Agent channel',
            'WEB_APP' => 'Web app',
            'IN_WAREHOUSE' => 'In warehouse',
            'ASSIGNED_TO_AGENT' => 'Assigned to agent',
            'SOLE_TRADER' => 'Sole trader',
            'EXTERNAL_API' => 'External API',
            'MAX_OF' => 'Max of',
            'MIN_OF' => 'Min of',
            'ON_TRANSACTION_AMOUNT' => 'On transaction amount',
            'ON_FEE_AMOUNT' => 'On fee amount',
            'BATCH_DAILY' => 'Daily batch',
            'BATCH_WEEKLY' => 'Weekly batch',
            'PARTIAL_FAILURE' => 'Partial failure',
            'NO_PAYOUTS' => 'No payouts',
            'ACCOUNT_CLOSURE' => 'Account closure',
            'LARGE_CASH_OUT' => 'Large cash-out',
            'BACKOFFICE_USER_PRIVILEGE_ELEVATION' => 'Backoffice user privilege elevation',
            'FEE_RULE_CHANGE' => 'Fee rule change',
            'COMMISSION_RULE_CHANGE' => 'Commission rule change',
            'CONTROL_THRESHOLD_CHANGE' => 'Control threshold change',
            'LIMIT_PROFILE_CHANGE' => 'Limit profile change',
            'SERVICE_PROVIDER_CHANGE' => 'Service provider change',
            'RECONCILIATION_ADJUSTMENT' => 'Reconciliation adjustment',
            'UNDER_INVESTIGATION' => 'Under investigation',
            'DOUBLE_ENTRY_MISMATCH' => 'Double-entry mismatch',
            'BALANCE_MISMATCH' => 'Balance mismatch',
            'FLOAT_IDENTITY_BREACH' => 'Float identity breach',
            'TO_SUSPENSE' => 'To suspense',
            'FROM_SUSPENSE' => 'From suspense',
            'TRANSACTION_SUMMARY' => 'Transaction summary',
            'TRANSACTIONS_SUMMARY' => 'Transaction summary',
            'KYC_SUMMARY' => 'KYC summary',
            'AML_LARGE_TRANSACTIONS' => 'AML large transactions',
            'FLOAT_REPORT' => 'Float report',
            'FLOAT' => 'Float report',
            'ACTOR_SUMMARY' => 'Actor summary',
            'ACTORS_SUMMARY' => 'Actor summary',
            'TV' => 'TV',
            'NATIONAL_ID' => 'National ID',
            'PASSPORT' => 'Passport',
            'PROOF_OF_ADDRESS' => 'Proof of address',
            'BUSINESS_LICENSE' => 'Business license',
            'PENDING_REVIEW' => 'Pending review',
            'ACCEPTED' => 'Accepted',
            'REJECTED' => 'Rejected',
            'MAINTENANCE' => 'Maintenance',
            'SUSPENDED' => 'Suspended',
            'QUEUED' => 'Queued',
            'IN_PROCESSING' => 'In processing',
            'SUCCEEDED' => 'Succeeded',
            'FAILED_REFUNDED' => 'Failed — refunded',
            'FAILED_RETRY' => 'Failed — retry',
            'RELEASED' => 'Released',
            'EXPIRED' => 'Expired',
            'PAID' => 'Paid',
            'CANCELLED' => 'Cancelled',
            'OPEN' => 'Open',
            'RESTRICTED' => 'Restricted',
            'TRANSACTION' => 'Transaction',
            'BILL_PAYMENT' => 'Bill payment',
            'APPROVAL' => 'Approval',
            'RECONCILIATION' => 'Reconciliation',
            'UNREAD' => 'Unread',
            'READ' => 'Read',
        ];

        return $labels[$value] ?? self::humanize($value);
    }

    public static function values(string $enumClass, ?array $onlyValues = null): array
    {
        $values = array_map(
            fn (BackedEnum $case): string => (string) $case->value,
            $enumClass::cases(),
        );

        if ($onlyValues === null) {
            return $values;
        }

        return array_values(array_filter($values, fn (string $value): bool => in_array($value, $onlyValues, true)));
    }

    public static function options(string $enumClass, ?array $onlyValues = null): array
    {
        return array_map(
            fn (string $value): array => ['value' => $value, 'label' => self::label($value)],
            self::values($enumClass, $onlyValues),
        );
    }

    public static function validationRule(string $enumClass, ?array $onlyValues = null): string
    {
        return 'in:'.implode(',', self::values($enumClass, $onlyValues));
    }

    public static function valuesFromRows(array $rows, string $key, ?string $includeValue = null): array
    {
        $values = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $value = $row[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $values[] = trim($value);
            }
        }

        if (is_string($includeValue) && trim($includeValue) !== '') {
            $values[] = trim($includeValue);
        }

        return array_values(array_unique($values));
    }

    public static function optionsFromRows(
        array $rows,
        string $key,
        ?string $enumClass = null,
        ?string $includeValue = null,
    ): array {
        $values = self::valuesFromRows($rows, $key, $includeValue);

        if ($enumClass !== null) {
            return self::options($enumClass, $values);
        }

        sort($values, SORT_NATURAL);

        return array_map(
            fn (string $value): array => ['value' => $value, 'label' => self::label($value)],
            $values,
        );
    }

    private static function humanize(string $value): string
    {
        $label = ucwords(strtolower(str_replace('_', ' ', $value)));

        return strtr($label, [
            'Api' => 'API',
            'Aml' => 'AML',
            'Bo' => 'BO',
            'Id' => 'ID',
            'Kmf' => 'KMF',
            'Kyc' => 'KYC',
            'M2m' => 'M2M',
            'Mfa' => 'MFA',
            'Nfc' => 'NFC',
            'P2p' => 'P2P',
            'Pin' => 'PIN',
            'Tv' => 'TV',
            'Url' => 'URL',
        ]);
    }
}
