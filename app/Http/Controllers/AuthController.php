<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    // Mock users — replace with real API call when backend is ready
    private array $mockUsers = [
        [
            'id'          => '11111111-0000-0000-0000-000000000001',
            'email'       => 'admin@komopay.km',
            'password'    => 'password',
            'fullName'    => 'Admin User',
            'role'        => 'SUPER_ADMIN',
            'permissions' => [
                'ACTOR_VIEW_ANY', 'ACTOR_KYC_UPDATE', 'ACTOR_SUSPEND', 'ACTOR_REACTIVATE', 'ACTOR_CLOSE',
                'AGENT_FUND', 'AGENT_FUND_APPROVE', 'AUDIT_VIEW', 'BACKOFFICE_USER_MANAGE',
                'BACKOFFICE_USER_PRIVILEGE_ELEVATION_APPROVE', 'BILL_PROVIDER_SETTLEMENT_APPROVE',
                'BILL_PROVIDER_SETTLEMENT_REQUEST', 'BILL_PROVIDER_SETTLEMENT_VIEW',
                'CARD_BLOCK_ANY', 'CARD_CLOSE_ANY', 'CARD_REPORT_ANY', 'CARD_STOCK_ASSIGN',
                'CARD_STOCK_IMPORT', 'CARD_VIEW_ANY', 'COMMISSION_RULE_ACTIVATE',
                'COMMISSION_RULE_APPROVE', 'COMMISSION_RULE_WRITE', 'CONTROL_THRESHOLD_APPROVE',
                'CONTROL_THRESHOLD_VIEW', 'CONTROL_THRESHOLD_WRITE', 'FEE_RULE_ACTIVATE',
                'FEE_RULE_APPROVE', 'FEE_RULE_VIEW', 'FEE_RULE_WRITE', 'LIMIT_PROFILE_APPROVE',
                'LIMIT_PROFILE_VIEW', 'LIMIT_PROFILE_WRITE', 'PLATFORM_REVENUE_WITHDRAWAL_APPROVE',
                'PLATFORM_REVENUE_WITHDRAWAL_REQUEST', 'PLATFORM_REVENUE_WITHDRAWAL_VIEW',
                'RECONCILIATION_ADJUSTMENT_APPROVE', 'RECONCILIATION_RESOLVE', 'RECONCILIATION_VIEW',
                'REPORT_REGULATORY_EXPORT', 'SERVICE_PROVIDER_APPROVE', 'SERVICE_PROVIDER_MANAGE',
                'SERVICE_PROVIDER_VIEW', 'TERMINAL_MANAGE', 'TX_CASH_OUT_INITIATE',
                'TX_LARGE_CASH_OUT_APPROVE', 'TX_REVERSAL_APPROVE', 'TX_REVERSAL_INITIATE',
                'TX_VIEW_ANY', 'WALLET_FREEZE', 'WALLET_UNFREEZE', 'WALLET_VIEW_ANY',
                'ACTOR_CLOSE_APPROVE',
            ],
        ],
        [
            'id'          => '11111111-0000-0000-0000-000000000002',
            'email'       => 'supervisor@komopay.km',
            'password'    => 'password',
            'fullName'    => 'Supervisor User',
            'role'        => 'SUPERVISOR',
            'permissions' => [
                'ACTOR_KYC_UPDATE', 'ACTOR_REACTIVATE', 'ACTOR_SUSPEND', 'ACTOR_VIEW_ANY',
                'AGENT_FUND', 'BILL_PROVIDER_SETTLEMENT_REQUEST', 'BILL_PROVIDER_SETTLEMENT_VIEW',
                'CARD_REPORT_ANY', 'CARD_STOCK_ASSIGN', 'CARD_VIEW_ANY', 'FEE_RULE_VIEW',
                'LIMIT_PROFILE_VIEW', 'RECONCILIATION_RESOLVE', 'RECONCILIATION_VIEW',
                'SERVICE_PROVIDER_VIEW', 'TX_CASH_OUT_INITIATE', 'TX_REVERSAL_INITIATE',
                'TX_VIEW_ANY', 'WALLET_VIEW_ANY',
            ],
        ],
        [
            'id'          => '11111111-0000-0000-0000-000000000003',
            'email'       => 'compliance@komopay.km',
            'password'    => 'password',
            'fullName'    => 'Compliance Officer',
            'role'        => 'COMPLIANCE',
            'permissions' => [
                'ACTOR_VIEW_ANY', 'AUDIT_VIEW', 'BILL_PROVIDER_SETTLEMENT_VIEW',
                'CARD_VIEW_ANY', 'FEE_RULE_VIEW', 'PLATFORM_REVENUE_WITHDRAWAL_VIEW',
                'RECONCILIATION_RESOLVE', 'RECONCILIATION_VIEW', 'REPORT_REGULATORY_EXPORT',
                'SERVICE_PROVIDER_VIEW', 'TX_VIEW_ANY', 'WALLET_VIEW_ANY',
            ],
        ],
    ];

    public function showLogin()
    {
        if (session()->has('bo_user')) {
            return redirect()->route('dashboard');
        }

        return view('pages.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|min:8',
        ]);

        $user = collect($this->mockUsers)->first(fn($u) =>
            $u['email'] === $request->email && $u['password'] === $request->password
        );

        if (!$user) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        session([
            'bo_user' => [
                'id'          => $user['id'],
                'email'       => $user['email'],
                'fullName'    => $user['fullName'],
                'role'        => $user['role'],
                'permissions' => $user['permissions'],
            ],
        ]);

        return redirect()->route('dashboard');
    }

    public function logout()
    {
        session()->forget('bo_user');
        return redirect()->route('login');
    }
}
