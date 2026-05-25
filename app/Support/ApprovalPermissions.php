<?php

namespace App\Support;

final class ApprovalPermissions
{
    public static function forType(?string $type): ?string
    {
        return match (strtoupper(trim((string) $type))) {
            'REVERSAL' => 'TX_REVERSAL_APPROVE',
            'LARGE_CASH_OUT' => 'TX_LARGE_CASH_OUT_APPROVE',
            'BACKOFFICE_USER_PRIVILEGE_ELEVATION' => 'BACKOFFICE_USER_PRIVILEGE_ELEVATION_APPROVE',
            'FEE_RULE_CHANGE' => 'FEE_RULE_APPROVE',
            'COMMISSION_RULE_CHANGE' => 'COMMISSION_RULE_APPROVE',
            'CONTROL_THRESHOLD_CHANGE' => 'CONTROL_THRESHOLD_APPROVE',
            'LIMIT_PROFILE_CHANGE' => 'LIMIT_PROFILE_APPROVE',
            'SERVICE_PROVIDER_CHANGE' => 'SERVICE_PROVIDER_APPROVE',
            'BILL_PROVIDER_SETTLEMENT' => 'BILL_PROVIDER_SETTLEMENT_APPROVE',
            'PLATFORM_REVENUE_WITHDRAWAL' => 'PLATFORM_REVENUE_WITHDRAWAL_APPROVE',
            'PLATFORM_LIQUIDITY_TOP_UP' => 'PLATFORM_LIQUIDITY_TOP_UP_APPROVE',
            'RECONCILIATION_ADJUSTMENT' => 'RECONCILIATION_ADJUSTMENT_APPROVE',
            'ACCOUNT_CLOSURE' => 'ACTOR_CLOSE_APPROVE',
            'AGENT_FUND_IN', 'AGENT_FUND_OUT' => 'AGENT_FUND_APPROVE',
            default => null,
        };
    }

    /**
     * @param array<int, string> $permissions
     */
    public static function canActOn(?string $type, array $permissions): bool
    {
        $permission = self::forType($type);

        return $permission !== null && in_array($permission, $permissions, true);
    }

    /**
     * Every approval permission a user could hold. Listing approvals requires
     * at least one of these (spec §5.4 / §10.2).
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            'TX_REVERSAL_APPROVE',
            'TX_LARGE_CASH_OUT_APPROVE',
            'BACKOFFICE_USER_PRIVILEGE_ELEVATION_APPROVE',
            'FEE_RULE_APPROVE',
            'COMMISSION_RULE_APPROVE',
            'CONTROL_THRESHOLD_APPROVE',
            'LIMIT_PROFILE_APPROVE',
            'SERVICE_PROVIDER_APPROVE',
            'BILL_PROVIDER_SETTLEMENT_APPROVE',
            'PLATFORM_REVENUE_WITHDRAWAL_APPROVE',
            'PLATFORM_LIQUIDITY_TOP_UP_APPROVE',
            'RECONCILIATION_ADJUSTMENT_APPROVE',
            'ACTOR_CLOSE_APPROVE',
            'AGENT_FUND_APPROVE',
        ];
    }
}
