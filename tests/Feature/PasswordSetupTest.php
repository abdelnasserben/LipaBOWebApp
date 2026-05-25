<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the first-login password setup flow (BO_Frontend_Specification §3.1a /
 * §3.1 BackofficeLoginResponse). Login returns an envelope with exactly one
 * branch populated; the client inspects passwordSetupRequired first.
 */
class PasswordSetupTest extends TestCase
{
    private function fakeLogin(array $data, int $status = 200): void
    {
        config(['komopay.base_url' => 'http://api.test']);

        Http::fake([
            'http://api.test/api/v1/auth/backoffice/login' => Http::response([
                'data' => $data,
                'timestamp' => '2026-05-24T12:00:00Z',
            ], $status),
        ]);
    }

    public function test_login_branch_a_full_session_proceeds_to_dashboard(): void
    {
        config(['komopay.base_url' => 'http://api.test']);

        // The access token carries only authorization claims (sub/brole/perms),
        // never email/name (spec §3.4). header.payload.signature; payload claims.
        $payload = rtrim(strtr(base64_encode(json_encode([
            'sub' => 'user-1',
            'brole' => 'SUPER_ADMIN',
            'perms' => ['TX_VIEW_ANY'],
        ])), '+/', '-_'), '=');
        $access = "header.{$payload}.sig";

        Http::fake([
            'http://api.test/api/v1/auth/backoffice/login' => Http::response([
                'data' => [
                    'passwordSetupRequired' => false,
                    'tokens' => [
                        'tokenType' => 'Bearer',
                        'accessToken' => $access,
                        'accessTokenExpiresAt' => '2026-05-24T16:00:00Z',
                        'refreshToken' => 'opaque-refresh',
                        'refreshTokenExpiresAt' => '2026-05-25T00:00:00Z',
                    ],
                ],
                'timestamp' => '2026-05-24T12:00:00Z',
            ], 200),
            // Profile is read live from /me, not guessed from the token.
            'http://api.test/api/v1/backoffice/me' => Http::response([
                'data' => [
                    'id' => 'user-1',
                    'email' => 'admin@example.com',
                    'fullName' => 'Admin User',
                    'role' => 'SUPER_ADMIN',
                    'permissions' => ['TX_VIEW_ANY'],
                    'status' => 'ACTIVE',
                    'mfaEnabled' => false,
                ],
            ], 200),
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        // /me is fetched with the freshly issued access token.
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/backoffice/me')
            && $request->hasHeader('Authorization', "Bearer {$access}"));

        $response->assertRedirect('/');
        $this->assertSame($access, session('bo_access_token'));
        $this->assertSame('opaque-refresh', session('bo_refresh_token'));
        $this->assertTrue(session()->has('bo_user'));
        $this->assertSame('admin@example.com', session('bo_user')['email']);
        $this->assertSame('Admin User', session('bo_user')['fullName']);
        $this->assertSame('SUPER_ADMIN', session('bo_user')['role']);
        $this->assertFalse(session('bo_user')['mfaEnabled']);
        $this->assertFalse(session()->has('bo_password_setup_token'));
    }

    public function test_login_branch_a_falls_back_to_token_claims_when_me_unreachable(): void
    {
        config(['komopay.base_url' => 'http://api.test']);

        $payload = rtrim(strtr(base64_encode(json_encode([
            'sub' => 'user-1',
            'brole' => 'OPERATOR',
            'perms' => ['TX_VIEW_ANY'],
        ])), '+/', '-_'), '=');
        $access = "header.{$payload}.sig";

        // /me returns an error: the session must still be usable from token claims
        // (role/permissions) so the user is not stranded (spec §3.4 fallback).
        Http::fake([
            'http://api.test/api/v1/auth/backoffice/login' => Http::response([
                'data' => [
                    'passwordSetupRequired' => false,
                    'tokens' => [
                        'tokenType' => 'Bearer',
                        'accessToken' => $access,
                        'accessTokenExpiresAt' => '2026-05-24T16:00:00Z',
                        'refreshToken' => 'opaque-refresh',
                        'refreshTokenExpiresAt' => '2026-05-25T00:00:00Z',
                    ],
                ],
            ], 200),
            'http://api.test/api/v1/backoffice/me' => Http::response(['error' => ['code' => 'SERVICE_UNAVAILABLE']], 503),
        ]);

        $response = $this->post('/login', [
            'email' => 'op@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertTrue(session()->has('bo_user'));
        $this->assertSame('user-1', session('bo_user')['id']);
        $this->assertSame('OPERATOR', session('bo_user')['role']);
        $this->assertSame(['TX_VIEW_ANY'], session('bo_user')['permissions']);
        // No profile data available from the token, so these stay empty.
        $this->assertSame('', session('bo_user')['email']);
        $this->assertFalse(session('bo_user')['mfaEnabled']);
    }

    public function test_login_branch_b_routes_to_password_setup_without_session(): void
    {
        $this->fakeLogin([
            'passwordSetupRequired' => true,
            'passwordSetupToken' => 'single-use-jwt',
            'passwordSetupTokenExpiresAt' => '2026-05-24T12:15:00Z',
        ]);

        $response = $this->post('/login', [
            'email' => 'newuser@example.com',
            'password' => 'temp-password',
        ]);

        $response->assertRedirect('/password-setup');
        $this->assertFalse(session()->has('bo_user'));
        $this->assertFalse(session()->has('bo_access_token'));
        $this->assertSame('single-use-jwt', session('bo_password_setup_token'));
    }

    public function test_password_setup_screen_requires_setup_token(): void
    {
        $this->get('/password-setup')->assertRedirect('/login');
    }

    public function test_password_setup_success_clears_token_and_returns_to_login(): void
    {
        config(['komopay.base_url' => 'http://api.test']);

        Http::fake([
            'http://api.test/api/v1/auth/backoffice/password-setup' => Http::response('', 204),
        ]);

        $response = $this
            ->withSession(['bo_password_setup_token' => 'single-use-jwt'])
            ->post('/password-setup', [
                'new_password' => 'my-final-password',
                'new_password_confirmation' => 'my-final-password',
            ]);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer single-use-jwt')
                && $request['newPassword'] === 'my-final-password';
        });

        $response->assertRedirect('/login');
        $response->assertSessionHas('status');
        $this->assertFalse(session()->has('bo_password_setup_token'));
    }

    public function test_password_setup_consumed_token_returns_to_login(): void
    {
        config(['komopay.base_url' => 'http://api.test']);

        Http::fake([
            'http://api.test/api/v1/auth/backoffice/password-setup' => Http::response([
                'code' => 'AUTH_INVALID_TOKEN',
                'message' => 'Token is not a PASSWORD_SETUP token.',
            ], 401),
        ]);

        $response = $this
            ->withSession(['bo_password_setup_token' => 'consumed-jwt'])
            ->post('/password-setup', [
                'new_password' => 'my-final-password',
                'new_password_confirmation' => 'my-final-password',
            ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertFalse(session()->has('bo_password_setup_token'));
    }

    public function test_password_setup_validation_error_keeps_token(): void
    {
        $response = $this
            ->from('/password-setup')
            ->withSession(['bo_password_setup_token' => 'single-use-jwt'])
            ->post('/password-setup', [
                'new_password' => 'short',
                'new_password_confirmation' => 'short',
            ]);

        $response->assertRedirect('/password-setup');
        $response->assertSessionHasErrors('new_password');
        $this->assertSame('single-use-jwt', session('bo_password_setup_token'));
    }
}
