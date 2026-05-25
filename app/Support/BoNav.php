<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;

/**
 * Decides which sidebar links the current backoffice user may see, so the UI
 * never offers a destination that the API would answer with 403.
 *
 * Source of truth: BO_Frontend_Specification.md §10 (permissions) and the
 * per-screen "Required permission" notes in §5. Server-side checks remain
 * authoritative; this is purely UI gating.
 */
final class BoNav
{
    /**
     * Permission(s) that make a sidebar route useful. A route mapped to an
     * array is visible when the user holds ANY of the listed permissions.
     * Routes absent from this map (dashboard, security) are always visible.
     *
     * @var array<string, array<int, string>>
     */
    private const ROUTE_PERMISSIONS = [
        'customers'         => ['ACTOR_VIEW_ANY'],
        'agents'            => ['ACTOR_VIEW_ANY'],
        'merchants'         => ['ACTOR_VIEW_ANY'],
        'transactions'      => ['TX_VIEW_ANY'],
        'bill-payments'     => ['BILL_PAYMENT_PROCESS_VIEW'],
        'wallets'           => ['WALLET_VIEW_ANY'],
        'audit'             => ['AUDIT_VIEW'],
        'reconciliation'    => ['RECONCILIATION_VIEW'],
        'reports'           => ['REPORT_REGULATORY_EXPORT'],
        'treasury'          => ['PLATFORM_LIQUIDITY_TOP_UP_VIEW', 'PLATFORM_REVENUE_WITHDRAWAL_VIEW', 'BILL_PROVIDER_SETTLEMENT_VIEW', 'RECONCILIATION_VIEW'],
        'rules-limits'      => ['FEE_RULE_VIEW', 'LIMIT_PROFILE_VIEW', 'LIMIT_PROFILE_WRITE', 'CONTROL_THRESHOLD_VIEW', 'CONTROL_THRESHOLD_WRITE'],
        'service-providers' => ['SERVICE_PROVIDER_VIEW', 'SERVICE_PROVIDER_MANAGE'],
        'cards'             => ['CARD_VIEW_ANY'],
        'terminals'         => ['TERMINAL_MANAGE'],
        'users'             => ['BACKOFFICE_USER_MANAGE'],
    ];

    /**
     * The permissions held by the signed-in backoffice user.
     *
     * @return array<int, string>
     */
    public static function permissions(): array
    {
        return (array) Session::get('bo_user.permissions', []);
    }

    public static function has(string $permission): bool
    {
        return in_array($permission, self::permissions(), true);
    }

    /**
     * @param array<int, string> $permissions
     */
    public static function hasAny(array $permissions): bool
    {
        return count(array_intersect($permissions, self::permissions())) > 0;
    }

    /**
     * Whether the current user should see the sidebar link for $route.
     */
    public static function canSee(string $route): bool
    {
        // Approvals is dynamic: visible when the user can act on any approval type.
        if ($route === 'approvals') {
            return self::hasAny(ApprovalPermissions::all());
        }

        // Unmapped routes (dashboard, security) are always available.
        if (! array_key_exists($route, self::ROUTE_PERMISSIONS)) {
            return true;
        }

        return self::hasAny(self::ROUTE_PERMISSIONS[$route]);
    }

    /**
     * Whether the user can see at least one of the given sidebar routes.
     * Used to hide a whole nav group whose links are all gated away.
     *
     * @param array<int, string> $routes
     */
    public static function canSeeAny(array $routes): bool
    {
        foreach ($routes as $route) {
            if (self::canSee($route)) {
                return true;
            }
        }

        return false;
    }
}
