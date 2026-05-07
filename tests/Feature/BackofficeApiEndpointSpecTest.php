<?php

namespace Tests\Feature;

use App\Exceptions\BackofficeApiException;
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

    public function test_audit_listing_forwards_spec_filters(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/audit*' => Http::response(['data' => []]),
        ]);

        (new HttpBackofficeApi)->auditEvents([
            'cursor' => 'next-page',
            'limit' => 50,
            'eventType' => 'WALLET_FROZEN',
            'actorId' => '11111111-0000-0000-0000-000000000001',
            'from' => '2026-05-04',
            'to' => '2026-05-06',
            'correlationId' => 'c8-vwx',
            'ignoredEmpty' => '',
        ]);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->url() !== 'http://api.test/api/v1/backoffice/audit'
                && ($query['cursor'] ?? null) === 'next-page'
                && ($query['limit'] ?? null) === '50'
                && ($query['eventType'] ?? null) === 'WALLET_FROZEN'
                && ($query['actorId'] ?? null) === '11111111-0000-0000-0000-000000000001'
                && ($query['from'] ?? null) === '2026-05-04T00:00:00Z'
                && ($query['to'] ?? null) === '2026-05-06T23:59:59Z'
                && ($query['correlationId'] ?? null) === 'c8-vwx'
                && ! array_key_exists('ignoredEmpty', $query);
        });
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

    public function test_backoffice_user_lifecycle_endpoints_follow_spec(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/users/user-1/suspend' => Http::response(['data' => ['id' => 'user-1']]),
            'http://api.test/api/v1/backoffice/users/user-1/reactivate' => Http::response(['data' => ['id' => 'user-1']]),
            'http://api.test/api/v1/backoffice/users/user-1/close' => Http::response(['data' => ['id' => 'user-1']]),
            'http://api.test/api/v1/backoffice/users/user-1/elevate-role' => Http::response([
                'data' => [
                    'status' => 'PENDING_APPROVAL',
                    'approvalId' => 'approval-1',
                ],
            ], 202),
        ]);

        $api = new HttpBackofficeApi;
        $api->suspendBackofficeUser('user-1');
        $api->reactivateBackofficeUser('user-1');
        $api->closeBackofficeUser('user-1');
        $response = $api->elevateBackofficeUserRole('user-1', ['newRole' => 'admin']);

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        $this->assertSame('PENDING_APPROVAL', $response['status']);
        $this->assertSame('', $requests[0]->body());
        $this->assertSame('', $requests[1]->body());
        $this->assertSame('', $requests[2]->body());
        $this->assertSame(['newRole' => 'ADMIN'], json_decode($requests[3]->body(), true));
    }

    public function test_priority_write_payloads_match_backoffice_dtos(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/agents' => Http::response(['data' => ['id' => 'agent-1']], 201),
            'http://api.test/api/v1/backoffice/agents/agent-1/fund-in' => Http::response(['data' => ['id' => 'approval-1']], 201),
            'http://api.test/api/v1/backoffice/agents/agent-1/approve-kyc' => Http::response(['data' => ['id' => 'agent-1']], 200),
            'http://api.test/api/v1/backoffice/merchants' => Http::response(['data' => ['id' => 'merchant-1']], 201),
            'http://api.test/api/v1/backoffice/users' => Http::response(['data' => ['id' => 'user-1']], 201),
            'http://api.test/api/v1/backoffice/approvals/approval-1/approve' => Http::response(['data' => ['id' => 'approval-1']], 200),
            'http://api.test/api/v1/backoffice/approvals/approval-1/reject' => Http::response(['data' => ['id' => 'approval-1']], 200),
        ]);

        $api = new HttpBackofficeApi;

        $api->createAgent([
            'fullName' => '  Ahmed Omar  ',
            'phoneCountryCode' => '+269',
            'phoneNumber' => '3211234',
            'zone' => '',
            'contractRef' => '  AGT-2026-001  ',
        ]);
        $api->fundAgent('agent-1', 'fund-in', ['amount' => '500000', 'notes' => ' float top-up ']);
        $api->approveAgentKyc('agent-1', ['kycLevel' => 'kyc_verified']);
        $api->createMerchant([
            'businessName' => '  Boutique Omar  ',
            'legalName' => ' SARL Omar Commerce ',
            'businessType' => 'sole_trader',
            'taxId' => '',
            'phoneCountryCode' => '+269',
            'phoneNumber' => '3215678',
            'address' => [
                'island' => 'Grande Comore',
                'city' => 'Moroni',
                'district' => '',
            ],
            'category' => 'retail',
        ]);
        $api->createBackofficeUser([
            'email' => ' ops@lipa.km ',
            'password' => 'SecurePass123!',
            'fullName' => ' Ali Hassan ',
            'role' => 'operator',
        ]);
        $api->approveRequest('approval-1', ['reason' => '']);
        $api->rejectRequest('approval-1', ['reason' => ' Duplicate request ']);

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        $this->assertTrue($requests[0]->hasHeader('Authorization', 'Bearer test-token'));
        $this->assertTrue($requests[0]->hasHeader('Accept', 'application/json'));
        $this->assertTrue($requests[0]->hasHeader('Content-Type', 'application/json'));

        $this->assertSame([
            'fullName' => 'Ahmed Omar',
            'phoneCountryCode' => '+269',
            'phoneNumber' => '3211234',
            'contractRef' => 'AGT-2026-001',
        ], json_decode($requests[0]->body(), true));

        $this->assertSame([
            'amount' => 500000,
            'notes' => 'float top-up',
        ], json_decode($requests[1]->body(), true));

        $this->assertSame(['kycLevel' => 'KYC_VERIFIED'], json_decode($requests[2]->body(), true));

        $this->assertSame([
            'businessName' => 'Boutique Omar',
            'legalName' => 'SARL Omar Commerce',
            'businessType' => 'SOLE_TRADER',
            'phoneCountryCode' => '+269',
            'phoneNumber' => '3215678',
            'addressIsland' => 'Grande Comore',
            'addressCity' => 'Moroni',
            'category' => 'RETAIL',
        ], json_decode($requests[3]->body(), true));

        $this->assertArrayNotHasKey('address', json_decode($requests[3]->body(), true));

        $this->assertSame([
            'email' => 'ops@lipa.km',
            'password' => 'SecurePass123!',
            'fullName' => 'Ali Hassan',
            'role' => 'OPERATOR',
        ], json_decode($requests[4]->body(), true));

        $this->assertSame('', $requests[5]->body());
        $this->assertSame(['reason' => 'Duplicate request'], json_decode($requests[6]->body(), true));
    }

    public function test_api_validation_details_are_flattened_for_livewire_alerts(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/users' => Http::response([
                'code' => 'VALIDATION_FAILED',
                'message' => 'Invalid request.',
                'details' => [
                    'email' => ['must be a well-formed email address'],
                    'password' => 'size must be between 8 and 100',
                ],
            ], 400),
        ]);

        try {
            (new HttpBackofficeApi)->createBackofficeUser([
                'email' => 'bad',
                'password' => 'short',
                'fullName' => 'Bad User',
                'role' => 'OPERATOR',
            ]);
            $this->fail('Expected BackofficeApiException was not thrown.');
        } catch (BackofficeApiException $e) {
            $this->assertStringContainsString('Invalid request', $e->userMessage());
            $this->assertStringContainsString('email: must be a well-formed email address', $e->userMessage());
            $this->assertStringContainsString('password: size must be between 8 and 100', $e->userMessage());
        }
    }
}
