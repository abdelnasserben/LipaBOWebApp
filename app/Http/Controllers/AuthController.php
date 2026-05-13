<?php

namespace App\Http\Controllers;

use App\Exceptions\BackofficeApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    private array $mockUsers = [
        [
            'id' => '11111111-0000-0000-0000-000000000001',
            'email' => 'admin@komopay.km',
            'password' => 'password',
            'fullName' => 'Admin User',
            'role' => 'SUPER_ADMIN',
            'permissions' => [
                'ACTOR_AUTH_PIN_RESET',
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
                'PLATFORM_LIQUIDITY_TOP_UP_APPROVE', 'PLATFORM_LIQUIDITY_TOP_UP_REQUEST',
                'PLATFORM_LIQUIDITY_TOP_UP_VIEW',
                'RECONCILIATION_ADJUSTMENT_APPROVE', 'RECONCILIATION_RESOLVE', 'RECONCILIATION_VIEW',
                'REPORT_REGULATORY_EXPORT', 'SERVICE_PROVIDER_APPROVE', 'SERVICE_PROVIDER_MANAGE',
                'SERVICE_PROVIDER_VIEW', 'TERMINAL_MANAGE', 'TX_CASH_OUT_INITIATE',
                'TX_LARGE_CASH_OUT_APPROVE', 'TX_REVERSAL_APPROVE', 'TX_REVERSAL_INITIATE',
                'TX_VIEW_ANY', 'WALLET_FREEZE', 'WALLET_UNFREEZE', 'WALLET_VIEW_ANY',
                'ACTOR_CLOSE_APPROVE',
            ],
        ],
        [
            'id' => '11111111-0000-0000-0000-000000000002',
            'email' => 'supervisor@komopay.km',
            'password' => 'password',
            'fullName' => 'Supervisor User',
            'role' => 'SUPERVISOR',
            'permissions' => [
                'ACTOR_AUTH_PIN_RESET',
                'ACTOR_KYC_UPDATE', 'ACTOR_REACTIVATE', 'ACTOR_SUSPEND', 'ACTOR_VIEW_ANY',
                'AGENT_FUND', 'BILL_PROVIDER_SETTLEMENT_REQUEST', 'BILL_PROVIDER_SETTLEMENT_VIEW',
                'CARD_REPORT_ANY', 'CARD_STOCK_ASSIGN', 'CARD_VIEW_ANY', 'FEE_RULE_VIEW',
                'LIMIT_PROFILE_VIEW', 'RECONCILIATION_RESOLVE', 'RECONCILIATION_VIEW',
                'SERVICE_PROVIDER_VIEW', 'TX_CASH_OUT_INITIATE', 'TX_REVERSAL_INITIATE',
                'TX_VIEW_ANY', 'WALLET_VIEW_ANY',
            ],
        ],
        [
            'id' => '11111111-0000-0000-0000-000000000003',
            'email' => 'compliance@komopay.km',
            'password' => 'password',
            'fullName' => 'Compliance Officer',
            'role' => 'COMPLIANCE',
            'permissions' => [
                'ACTOR_VIEW_ANY', 'AUDIT_VIEW', 'BILL_PROVIDER_SETTLEMENT_VIEW',
                'CARD_VIEW_ANY', 'FEE_RULE_VIEW', 'PLATFORM_REVENUE_WITHDRAWAL_VIEW',
                'PLATFORM_LIQUIDITY_TOP_UP_VIEW',
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
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        return config('komopay.use_mock_api')
            ? $this->loginWithMock($request)
            : $this->loginWithApi($request);
    }

    public function logout()
    {
        if (! config('komopay.use_mock_api') && session()->has('bo_access_token')) {
            try {
                Http::baseUrl(rtrim((string) config('komopay.base_url'), '/'))
                    ->timeout((int) config('komopay.timeout', 15))
                    ->withToken((string) session('bo_access_token'))
                    ->post('/api/v1/auth/backoffice/logout');
            } catch (\Throwable) {
                // Local logout must still complete if the upstream service is unavailable.
            }
        }

        session()->forget(['bo_user', 'bo_access_token', 'bo_refresh_token', 'bo_token_expires_at']);

        return redirect()->route('login');
    }

    private function loginWithMock(Request $request)
    {
        $user = collect($this->mockUsers)->first(
            fn ($user) => $user['email'] === $request->email && $user['password'] === $request->password
        );

        if (! $user) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        session([
            'bo_user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'fullName' => $user['fullName'],
                'role' => $user['role'],
                'permissions' => $user['permissions'],
            ],
        ]);

        return redirect()->route('dashboard');
    }

    private function loginWithApi(Request $request)
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('komopay.base_url'), '/'))
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('komopay.timeout', 15))
                ->post('/api/v1/auth/backoffice/login', [
                    'email' => $request->email,
                    'password' => $request->password,
                ]);
        } catch (ConnectionException) {
            return back()
                ->withErrors(['email' => 'Could not reach the Backoffice service. Please try again.'])
                ->withInput($request->except('password'));
        }

        if ($response->failed()) {
            $error = BackofficeApiException::fromResponse($response);

            return back()
                ->withErrors(['email' => $error->userMessage()])
                ->withInput($request->except('password'));
        }

        $body = $response->json();
        $data = is_array($body['data'] ?? null) ? $body['data'] : (is_array($body) ? $body : []);

        $accessToken = $data['accessToken'] ?? null;
        $refreshToken = $data['refreshToken'] ?? null;
        $expiresAt = $data['accessTokenExpiresAt'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            return back()
                ->withErrors(['email' => 'The Backoffice service returned an unexpected response.'])
                ->withInput($request->except('password'));
        }

        $claims = $this->decodeJwtClaims($accessToken);
        $email = is_string($claims['email'] ?? null) ? $claims['email'] : $request->email;
        $fullName = is_string($claims['name'] ?? null) ? $claims['name'] : $email;
        $role = is_string($claims['brole'] ?? null) ? $claims['brole'] : '';
        $permissions = is_array($claims['perms'] ?? null) ? $claims['perms'] : [];

        session([
            'bo_access_token' => $accessToken,
            'bo_refresh_token' => $refreshToken,
            'bo_token_expires_at' => $expiresAt,
            'bo_user' => [
                'id' => is_string($claims['sub'] ?? null) ? $claims['sub'] : '',
                'email' => $email,
                'fullName' => $fullName,
                'role' => $role,
                'permissions' => $permissions,
            ],
        ]);

        return redirect()->route('dashboard');
    }

    private function decodeJwtClaims(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) < 2) {
            return [];
        }

        $payload = strtr($parts[1], '-_', '+/');
        $padded = str_pad($payload, strlen($payload) + (4 - strlen($payload) % 4) % 4, '=');
        $decoded = base64_decode($padded, true);

        if ($decoded === false) {
            return [];
        }

        $json = json_decode($decoded, true);

        return is_array($json) ? $json : [];
    }
}
