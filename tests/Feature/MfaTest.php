<?php

namespace Tests\Feature;

use App\Services\Api\Contracts\BackofficeApiContract;
use App\Services\Api\HttpBackofficeApi;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers backoffice TOTP MFA (BO_Frontend_Specification §3.1b) and the two new
 * login branches that feed it: branch C (mandatory enrollment) and branch D
 * (TOTP challenge). Login returns an envelope with exactly one branch populated;
 * the client inspects the flags in order: passwordSetup → mfaEnrollment →
 * mfaRequired → tokens.
 */
class MfaTest extends TestCase
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

    /** Builds a JWT whose payload carries the given claims (header/sig are dummy). */
    private function jwt(array $claims): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');

        return "header.{$payload}.sig";
    }

    // --- Login branch routing -------------------------------------------------

    public function test_login_branch_c_routes_to_mfa_enrollment_without_session(): void
    {
        $this->fakeLogin([
            'mfaEnrollmentRequired' => true,
            'mfaEnrollmentToken' => 'enroll-jwt',
            'mfaEnrollmentTokenExpiresAt' => '2026-05-24T12:15:00Z',
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/mfa/enroll');
        $this->assertFalse(session()->has('bo_user'));
        $this->assertSame('enroll-jwt', session('bo_mfa_enrollment_token'));
    }

    public function test_login_branch_d_routes_to_mfa_challenge_without_session(): void
    {
        $this->fakeLogin([
            'mfaRequired' => true,
            'challengeId' => '11111111-1111-1111-1111-111111111111',
            'mfaFactor' => 'TOTP',
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/mfa/challenge');
        $this->assertFalse(session()->has('bo_user'));
        $this->assertSame('11111111-1111-1111-1111-111111111111', session('bo_mfa_challenge_id'));
    }

    // --- Challenge screens are gated ------------------------------------------

    public function test_mfa_challenge_screen_requires_challenge_id(): void
    {
        $this->get('/mfa/challenge')->assertRedirect('/login');
    }

    public function test_mfa_enroll_screen_requires_enrollment_token(): void
    {
        $this->get('/mfa/enroll')->assertRedirect('/login');
    }

    // --- verify-mfa (login branch D step) -------------------------------------

    public function test_verify_mfa_success_establishes_session(): void
    {
        config(['komopay.base_url' => 'http://api.test']);

        $access = $this->jwt([
            'sub' => 'user-1',
            'email' => 'admin@example.com',
            'name' => 'Admin User',
            'brole' => 'ADMIN',
            'perms' => ['TX_VIEW_ANY'],
        ]);

        Http::fake([
            'http://api.test/api/v1/auth/backoffice/login/verify-mfa' => Http::response([
                'data' => [
                    'tokenType' => 'Bearer',
                    'accessToken' => $access,
                    'accessTokenExpiresAt' => '2026-05-24T16:00:00Z',
                    'refreshToken' => 'opaque-refresh',
                    'refreshTokenExpiresAt' => '2026-05-25T00:00:00Z',
                ],
            ], 200),
        ]);

        $response = $this
            ->withSession(['bo_mfa_challenge_id' => 'challenge-uuid'])
            ->post('/mfa/challenge', ['code' => '123456']);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/login/verify-mfa')
                && $request['challengeId'] === 'challenge-uuid'
                && $request['code'] === '123456'
                && ! $request->hasHeader('Authorization');
        });

        $response->assertRedirect('/');
        $this->assertSame($access, session('bo_access_token'));
        $this->assertSame('ADMIN', session('bo_user')['role']);
        $this->assertFalse(session()->has('bo_mfa_challenge_id'));
    }

    public function test_verify_mfa_invalid_code_restarts_login(): void
    {
        config(['komopay.base_url' => 'http://api.test']);

        Http::fake([
            'http://api.test/api/v1/auth/backoffice/login/verify-mfa' => Http::response([
                'code' => 'MFA_INVALID',
                'message' => 'Invalid or expired challenge.',
            ], 401),
        ]);

        $response = $this
            ->withSession(['bo_mfa_challenge_id' => 'challenge-uuid'])
            ->post('/mfa/challenge', ['code' => '000000']);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertFalse(session()->has('bo_mfa_challenge_id'));
    }

    public function test_verify_mfa_rejects_non_numeric_code(): void
    {
        $response = $this
            ->from('/mfa/challenge')
            ->withSession(['bo_mfa_challenge_id' => 'challenge-uuid'])
            ->post('/mfa/challenge', ['code' => 'abc']);

        $response->assertRedirect('/mfa/challenge');
        $response->assertSessionHasErrors('code');
    }

    // --- Mandatory enrollment (branch C) confirm ------------------------------

    public function test_enroll_confirm_success_clears_token_and_returns_to_login(): void
    {
        config(['komopay.base_url' => 'http://api.test']);

        Http::fake([
            'http://api.test/api/v1/auth/backoffice/totp-confirm' => Http::response('', 204),
        ]);

        $response = $this
            ->withSession(['bo_mfa_enrollment_token' => 'enroll-jwt'])
            ->post('/mfa/enroll', ['code' => '123456']);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer enroll-jwt')
            && $request['code'] === '123456');

        $response->assertRedirect('/login');
        $response->assertSessionHas('status');
        $this->assertFalse(session()->has('bo_mfa_enrollment_token'));
    }

    public function test_enroll_confirm_wrong_code_keeps_token_and_reprompts(): void
    {
        $this->withoutVite();
        config(['komopay.base_url' => 'http://api.test']);

        Http::fake([
            'http://api.test/api/v1/auth/backoffice/totp-confirm' => Http::response([
                'code' => 'MFA_INVALID',
                'message' => 'Wrong code.',
            ], 401),
        ]);

        $response = $this
            ->withSession(['bo_mfa_enrollment_token' => 'enroll-jwt'])
            ->post('/mfa/enroll', ['code' => '000000']);

        // Re-renders the code-only retry screen (200) WITHOUT rotating the secret,
        // and keeps the enrollment token for the next attempt.
        $response->assertOk();
        $this->assertSame('enroll-jwt', session('bo_mfa_enrollment_token'));
    }

    public function test_enroll_confirm_expired_token_restarts_login(): void
    {
        config(['komopay.base_url' => 'http://api.test']);

        Http::fake([
            'http://api.test/api/v1/auth/backoffice/totp-confirm' => Http::response([
                'code' => 'UNAUTHORIZED',
                'message' => 'Token rejected.',
            ], 401),
        ]);

        $response = $this
            ->withSession(['bo_mfa_enrollment_token' => 'spent-jwt'])
            ->post('/mfa/enroll', ['code' => '123456']);

        $response->assertRedirect('/login');
        $this->assertFalse(session()->has('bo_mfa_enrollment_token'));
    }

    // --- Voluntary management -------------------------------------------------

    /**
     * The app layout embeds the notification-bell Livewire component, which calls
     * the backoffice API on render. Stub the contract so full-page GET tests for
     * authenticated screens don't hit the network.
     */
    private function stubNotificationApi(): void
    {
        $this->withoutVite();

        $this->app->instance(BackofficeApiContract::class, new class extends HttpBackofficeApi
        {
            public function notifications(int $limit = 20): array
            {
                return [];
            }

            public function unreadNotificationCount(): int
            {
                return 0;
            }
        });
    }

    private function authedSession(string $role): array
    {
        return [
            'bo_access_token' => 'access-jwt',
            'bo_user' => [
                'id' => 'u1', 'email' => 'op@example.com', 'fullName' => 'Op',
                'role' => $role, 'permissions' => [],
            ],
        ];
    }

    public function test_security_page_offers_disable_for_optional_role(): void
    {
        $this->stubNotificationApi();

        $response = $this
            ->withSession($this->authedSession('OPERATOR'))
            ->get('/security');

        $response->assertOk();
        $response->assertSee('Disable');
    }

    public function test_security_page_hides_disable_for_mandatory_role(): void
    {
        $this->stubNotificationApi();

        $response = $this
            ->withSession($this->authedSession('ADMIN'))
            ->get('/security');

        $response->assertOk();
        $response->assertSee('mandatory for your role');
    }

    public function test_revoke_blocked_for_mandatory_role_without_calling_api(): void
    {
        Http::fake();

        $response = $this
            ->from('/security')
            ->withSession($this->authedSession('SUPER_ADMIN'))
            ->delete('/security/mfa', ['code' => '123456']);

        $response->assertRedirect('/security');
        $response->assertSessionHasErrors('code');
        Http::assertNothingSent();
    }

    public function test_revoke_success_for_optional_role(): void
    {
        config(['komopay.base_url' => 'http://api.test']);

        Http::fake([
            'http://api.test/api/v1/auth/backoffice/totp-setup' => Http::response('', 204),
        ]);

        $response = $this
            ->withSession($this->authedSession('OPERATOR'))
            ->delete('/security/mfa', ['code' => '123456']);

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request->hasHeader('Authorization', 'Bearer access-jwt')
            && $request['code'] === '123456');

        $response->assertRedirect('/security');
        $response->assertSessionHas('status');
    }
}
