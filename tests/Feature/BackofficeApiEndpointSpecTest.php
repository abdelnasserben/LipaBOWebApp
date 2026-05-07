<?php

namespace Tests\Feature;

use App\Services\Api\HttpBackofficeApi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackofficeApiEndpointSpecTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'komopay.base_url' => 'http://api.test',
            'komopay.prefix' => '/api/v1/backoffice',
            'komopay.token' => 'test-token',
        ]);

        Http::preventStrayRequests();
    }

    public function test_dashboard_is_composed_from_spec_endpoints(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/customers*' => Http::response([
                'data' => [
                    ['id' => 'cust-1', 'walletId' => 'wallet-1', 'status' => 'ACTIVE'],
                ],
                'pagination' => ['hasMore' => false, 'nextCursor' => null, 'limit' => 100],
            ]),
            'http://api.test/api/v1/backoffice/agents*' => Http::response([
                'data' => [
                    ['id' => 'agent-1', 'walletId' => 'wallet-2', 'status' => 'ACTIVE'],
                ],
                'pagination' => ['hasMore' => false, 'nextCursor' => null, 'limit' => 100],
            ]),
            'http://api.test/api/v1/backoffice/merchants*' => Http::response([
                'data' => [
                    ['id' => 'merchant-1', 'walletId' => 'wallet-3', 'status' => 'ACTIVE'],
                ],
                'pagination' => ['hasMore' => false, 'nextCursor' => null, 'limit' => 100],
            ]),
            'http://api.test/api/v1/backoffice/transactions*' => Http::response([
                'data' => [
                    ['id' => 'tx-1', 'type' => 'PAYMENT', 'status' => 'COMPLETED', 'requestedAmount' => 2500, 'createdAt' => '2026-05-07T10:00:00Z'],
                ],
                'pagination' => ['hasMore' => false, 'nextCursor' => null, 'limit' => 100],
            ]),
            'http://api.test/api/v1/backoffice/approvals*' => Http::response([
                'data' => [
                    ['id' => 'apr-1', 'status' => 'PENDING_APPROVAL'],
                ],
                'pagination' => ['hasMore' => false, 'nextCursor' => null, 'limit' => 100],
            ]),
            'http://api.test/api/v1/backoffice/reconciliation/incidents*' => Http::response([
                'data' => [],
                'pagination' => ['hasMore' => false, 'nextCursor' => null, 'limit' => 100],
            ]),
        ]);

        $stats = (new HttpBackofficeApi)->dashboardStats();

        $this->assertSame(1, $stats['totalCustomers']);
        $this->assertSame(2500, $stats['volumeToday']);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/dashboard/stats'));
    }

    public function test_wallet_listing_uses_actor_lists_and_wallet_detail_endpoint(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/customers*' => Http::response([
                'data' => [
                    ['id' => 'cust-1', 'externalRef' => 'C001', 'fullName' => 'Test Customer', 'walletId' => 'wallet-1'],
                ],
            ]),
            'http://api.test/api/v1/backoffice/agents*' => Http::response(['data' => []]),
            'http://api.test/api/v1/backoffice/merchants*' => Http::response(['data' => []]),
            'http://api.test/api/v1/backoffice/wallets/wallet-1' => Http::response([
                'data' => [
                    'id' => 'wallet-1',
                    'ownerType' => 'CUSTOMER',
                    'ownerId' => 'cust-1',
                    'currency' => 'KMF',
                    'status' => 'ACTIVE',
                    'availableBalance' => 1000,
                    'frozenBalance' => 0,
                    'version' => 1,
                    'createdAt' => '2026-05-07T00:00:00Z',
                    'updatedAt' => '2026-05-07T00:00:00Z',
                ],
            ]),
        ]);

        $wallets = (new HttpBackofficeApi)->wallets();

        $this->assertCount(1, $wallets);
        $this->assertSame('Test Customer', $wallets[0]['ownerLabel']);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://api.test/api/v1/backoffice/wallets/wallet-1');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/wallets?'));
    }

    public function test_high_risk_sections_use_spec_paths(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/audit*' => Http::response(['data' => []]),
            'http://api.test/api/v1/backoffice/commission-settlements/runs*' => Http::response(['data' => []]),
            'http://api.test/api/v1/backoffice/commission-settlements/pending*' => Http::response(['data' => []]),
            'http://api.test/api/v1/backoffice/reports/transactions/summary*' => Http::response(['data' => ['lines' => []]]),
            'http://api.test/api/v1/backoffice/reports/kyc/summary*' => Http::response(['data' => ['lines' => []]]),
            'http://api.test/api/v1/backoffice/reports/actors/summary*' => Http::response(['data' => ['lines' => []]]),
            'http://api.test/api/v1/backoffice/service-providers' => Http::response(['data' => [['id' => 'sp-1', 'name' => 'Provider']]]),
            'http://api.test/api/v1/backoffice/service-providers/sp-1/services' => Http::response(['data' => []]),
        ]);

        $api = new HttpBackofficeApi;
        $api->auditEvents();
        $api->commissionSettlementRuns();
        $api->commissionPendingSummary();
        $api->transactionSummaryReport(['from' => '2026-05-01T00:00:00Z', 'to' => '2026-05-07T23:59:59Z']);
        $api->kycSummaryReport();
        $api->actorSummaryReport();
        $api->serviceProviders();
        $api->billServices('sp-1');

        $badFragments = [
            '/audit-events',
            '/commission-settlements/pending-summary',
            '/reports/transaction-summary',
            '/reports/kyc-summary',
            '/reports/actor-summary',
            '/bill-services',
        ];

        foreach ($badFragments as $fragment) {
            Http::assertNotSent(fn (Request $request) => str_contains($request->url(), $fragment));
        }
    }

    public function test_no_body_actions_follow_spec(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/customers/cust-1/suspend' => Http::response(['data' => ['id' => 'cust-1']]),
            'http://api.test/api/v1/backoffice/wallets/wallet-1/freeze' => Http::response(['data' => ['id' => 'wallet-1']]),
            'http://api.test/api/v1/backoffice/cards/card-1/block' => Http::response(['data' => ['id' => 'card-1']]),
            'http://api.test/api/v1/backoffice/cards/card-1/report-lost' => Http::response(['data' => ['id' => 'card-1']]),
            'http://api.test/api/v1/backoffice/terminals/terminal-1/suspend' => Http::response(['data' => ['id' => 'terminal-1']]),
        ]);

        $api = new HttpBackofficeApi;
        $api->suspendCustomer('cust-1', 'ignored by spec');
        $api->freezeWallet('wallet-1', 'ignored by spec');
        $api->blockCard('card-1', 'ignored by spec');
        $api->reportCardLost('card-1', 'ignored by spec');
        $api->suspendTerminal('terminal-1', 'ignored by spec');

        $recorded = Http::recorded();

        $this->assertCount(5, $recorded);

        foreach ($recorded as [$request]) {
            $this->assertSame('', $request->body());
        }
    }
}
