<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthApiErrorHandlingTest extends TestCase
{
    public function test_real_api_login_error_returns_to_form_with_message(): void
    {
        config([
            'komopay.base_url' => 'http://api.test',
        ]);

        Http::fake([
            'http://api.test/api/v1/auth/backoffice/login' => Http::response([
                'error' => [
                    'code' => 'AUTH_INVALID_CREDENTIALS',
                    'message' => 'Invalid credentials.',
                    'correlationId' => 'corr-login',
                ],
            ], 401),
        ]);

        $response = $this
            ->from('/login')
            ->post('/login', [
                'email' => 'wrong@example.com',
                'password' => 'wrongpass',
            ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'Invalid credentials.']);
        $this->assertFalse(session()->has('bo_user'));
    }

    public function test_backoffice_api_exception_renders_friendly_error_page(): void
    {
        $this->withoutVite();

        config([
            'komopay.base_url' => 'http://api.test',
            'komopay.prefix' => '/api/v1/backoffice',
        ]);

        Http::fake([
            'http://api.test/api/v1/backoffice/customers*' => Http::response([
                'error' => [
                    'code' => 'SERVICE_UNAVAILABLE',
                    'message' => 'Backoffice service is unavailable.',
                    'correlationId' => 'corr-dashboard',
                ],
            ], 503),
        ]);

        $response = $this
            ->withSession([
                'bo_access_token' => 'token',
                'bo_user' => [
                    'id' => 'user-1',
                    'email' => 'admin@example.com',
                    'fullName' => 'Admin User',
                    'role' => 'ADMIN',
                    // Needs the actor-view permission so the dashboard actually
                    // queries /customers (the endpoint faked to fail here).
                    'permissions' => ['ACTOR_VIEW_ANY'],
                ],
            ])
            ->get('/');

        $response->assertStatus(503);
        $response->assertSee('API request failed');
        $response->assertSee('Backoffice service is unavailable.');
        $response->assertSee('SERVICE_UNAVAILABLE');
        $response->assertSee('corr-dashboard');
    }
}
