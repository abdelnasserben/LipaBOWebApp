<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TerminalActionConfirmationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'komopay.use_mock_api' => false,
            'komopay.base_url' => 'http://api.test',
            'komopay.prefix' => '/api/v1/backoffice',
            'komopay.token' => 'test-token',
        ]);

        Http::preventStrayRequests();
    }

    #[DataProvider('terminalActionProvider')]
    public function test_terminal_actions_open_confirmation_without_posting(
        string $method,
        string $status,
        string $title,
    ): void {
        $this->fakeTerminalApi($status);

        Livewire::test('terminals.terminal-management')
            ->call('selectRow', 'terminal-1')
            ->call($method)
            ->assertSet('pendingTerminalAction', $method)
            ->assertSee($title)
            ->assertSee('LIPA-POS-0001');

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    #[DataProvider('confirmedTerminalActionProvider')]
    public function test_confirming_terminal_action_posts_to_expected_endpoint(
        string $method,
        string $status,
        string $endpoint,
        string $notification,
    ): void {
        $this->fakeTerminalApi($status);

        Livewire::test('terminals.terminal-management')
            ->call('selectRow', 'terminal-1')
            ->call($method)
            ->call('confirmTerminalAction')
            ->assertSet('pendingTerminalAction', null)
            ->assertSet('terminalActionError', '')
            ->assertSet('notification', $notification);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === "http://api.test/api/v1/backoffice/terminals/terminal-1/{$endpoint}");
    }

    public function test_canceling_terminal_action_does_not_post(): void
    {
        $this->fakeTerminalApi('ACTIVE');

        Livewire::test('terminals.terminal-management')
            ->call('selectRow', 'terminal-1')
            ->call('suspend')
            ->call('cancelTerminalAction')
            ->assertSet('pendingTerminalAction', null)
            ->assertSet('terminalActionError', '')
            ->assertDontSee('Suspend terminal');

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_unavailable_terminal_action_does_not_open_confirmation_or_post(): void
    {
        $this->fakeTerminalApi('REVOKED');

        Livewire::test('terminals.terminal-management')
            ->call('selectRow', 'terminal-1')
            ->call('provision')
            ->assertSet('pendingTerminalAction', null)
            ->assertSee('This action is not available for the selected terminal.');

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_failed_terminal_action_keeps_modal_open_and_shows_error(): void
    {
        $this->fakeTerminalApi('SUSPENDED', [
            'http://api.test/api/v1/backoffice/terminals/terminal-1/reactivate' => Http::response([
                'error' => [
                    'code' => 'TERMINAL_STATE_CONFLICT',
                    'message' => 'Terminal cannot be reactivated from its current state.',
                    'correlationId' => 'corr-terminal-reactivate',
                ],
            ], 409),
        ]);

        Livewire::test('terminals.terminal-management')
            ->call('selectRow', 'terminal-1')
            ->call('reactivate')
            ->call('confirmTerminalAction')
            ->assertSet('pendingTerminalAction', 'reactivate')
            ->assertSee('Reactivate terminal')
            ->assertSee('Terminal cannot be reactivated from its current state.');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://api.test/api/v1/backoffice/terminals/terminal-1/reactivate');
    }

    public static function terminalActionProvider(): array
    {
        return [
            'provision' => ['provision', 'REGISTERED', 'Provision terminal'],
            'suspend' => ['suspend', 'ACTIVE', 'Suspend terminal'],
            'reactivate' => ['reactivate', 'SUSPENDED', 'Reactivate terminal'],
        ];
    }

    public static function confirmedTerminalActionProvider(): array
    {
        return [
            'provision' => ['provision', 'REGISTERED', 'provision', 'Terminal provisioned.'],
            'suspend' => ['suspend', 'ACTIVE', 'suspend', 'Terminal suspended.'],
            'reactivate' => ['reactivate', 'SUSPENDED', 'reactivate', 'Terminal reactivated.'],
        ];
    }

    private function fakeTerminalApi(string $status, array $overrides = []): void
    {
        $terminal = $this->terminal($status);

        Http::fake(array_merge([
            'http://api.test/api/v1/backoffice/terminals/terminal-1/provision' => Http::response([
                'data' => ['terminalId' => 'terminal-1'],
            ]),
            'http://api.test/api/v1/backoffice/terminals/terminal-1/suspend' => Http::response([
                'data' => ['id' => 'terminal-1'],
            ]),
            'http://api.test/api/v1/backoffice/terminals/terminal-1/reactivate' => Http::response([
                'data' => ['id' => 'terminal-1'],
            ]),
        ], $overrides, [
            'http://api.test/api/v1/backoffice/terminals/terminal-1' => Http::response([
                'data' => $terminal,
            ]),
            'http://api.test/api/v1/backoffice/terminals*' => Http::response([
                'data' => [$terminal],
            ]),
        ]));
    }

    private function terminal(string $status): array
    {
        return [
            'id' => 'terminal-1',
            'serialNumber' => 'LIPA-POS-0001',
            'deviceModel' => 'PAX A920',
            'androidVersion' => '11',
            'appVersion' => '2.3.1',
            'merchantId' => 'merchant-1',
            'status' => $status,
            'apiKeyIssuedAt' => null,
            'apiKeyExpiresAt' => null,
            'lastAuthAt' => null,
            'authFailedCount' => 0,
            'registeredAt' => '2026-05-01T10:00:00Z',
        ];
    }
}
