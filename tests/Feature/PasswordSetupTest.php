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
        // accessToken is a JWT with header.payload.signature; payload carries claims.
        $payload = rtrim(strtr(base64_encode(json_encode([
            'sub' => 'user-1',
            'email' => 'admin@example.com',
            'name' => 'Admin User',
            'brole' => 'SUPER_ADMIN',
            'perms' => ['TX_VIEW_ANY'],
        ])), '+/', '-_'), '=');

        $this->fakeLogin([
            'passwordSetupRequired' => false,
            'tokens' => [
                'tokenType' => 'Bearer',
                'accessToken' => "header.{$payload}.sig",
                'accessTokenExpiresAt' => '2026-05-24T16:00:00Z',
                'refreshToken' => 'opaque-refresh',
                'refreshTokenExpiresAt' => '2026-05-25T00:00:00Z',
            ],
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertSame("header.{$payload}.sig", session('bo_access_token'));
        $this->assertSame('opaque-refresh', session('bo_refresh_token'));
        $this->assertTrue(session()->has('bo_user'));
        $this->assertSame('admin@example.com', session('bo_user')['email']);
        $this->assertSame('SUPER_ADMIN', session('bo_user')['role']);
        $this->assertFalse(session()->has('bo_password_setup_token'));
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
