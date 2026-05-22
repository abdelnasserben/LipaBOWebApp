<?php

namespace Tests\Feature;

use App\Exceptions\BackofficeApiException;
use App\Services\Api\HttpBackofficeApi;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
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

    public function test_reports_queries_normalize_dates_enums_thresholds_and_export_generation(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/reports/transactions/summary*' => Http::response([
                'data' => ['from' => '2026-05-01T00:00:00Z', 'to' => '2026-05-31T23:59:59Z', 'groupBy' => 'MONTH', 'lines' => []],
            ]),
            'http://api.test/api/v1/backoffice/reports/aml/large-transactions*' => Http::response(['data' => []]),
            'http://api.test/api/v1/backoffice/reports/exports*' => Http::response([
                'data' => ['id' => 'rx-new', 'reportType' => 'AML_LARGE_TRANSACTIONS'],
            ]),
        ]);

        $api = new HttpBackofficeApi;
        $api->transactionSummaryReport([
            'from' => '2026-05-01',
            'to' => '2026-05-31',
            'groupBy' => 'month',
            'ignoredEmpty' => '',
        ]);
        $api->amlLargeTransactions([
            'from' => '2026-05-01',
            'to' => '2026-05-31',
            'thresholdKmf' => '750,000',
            'cursor' => 'next-page',
            'limit' => 25,
        ]);
        $api->requestReportExport([
            'reportType' => 'aml_large_transactions',
            'periodFrom' => '2026-05-01',
            'periodTo' => '',
            'recordCount' => '',
        ]);

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'http://api.test/api/v1/backoffice/reports/transactions/summary?')
                && ($query['from'] ?? null) === '2026-05-01T00:00:00Z'
                && ($query['to'] ?? null) === '2026-05-31T23:59:59Z'
                && ($query['groupBy'] ?? null) === 'MONTH'
                && ($query['format'] ?? null) === 'json'
                && ! array_key_exists('ignoredEmpty', $query);
        });

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'http://api.test/api/v1/backoffice/reports/aml/large-transactions?')
                && ($query['from'] ?? null) === '2026-05-01T00:00:00Z'
                && ($query['to'] ?? null) === '2026-05-31T23:59:59Z'
                && ($query['thresholdKmf'] ?? null) === '750000'
                && ($query['cursor'] ?? null) === 'next-page'
                && ($query['limit'] ?? null) === '25';
        });

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'POST'
                && str_starts_with($request->url(), 'http://api.test/api/v1/backoffice/reports/exports?')
                && $request->body() === ''
                && ($query['reportType'] ?? null) === 'AML_LARGE_TRANSACTIONS'
                && ($query['periodFrom'] ?? null) === '2026-05-01T00:00:00Z'
                && ! array_key_exists('periodTo', $query)
                && ! array_key_exists('recordCount', $query);
        });
    }

    public function test_report_csv_download_uses_summary_endpoint_and_forces_csv_format(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/reports/transactions/summary*' => Http::response(
                "type,period,count\n",
                200,
                ['Content-Type' => 'text/csv'],
            ),
        ]);

        $response = (new HttpBackofficeApi)->downloadReport('transaction-summary', [
            'from' => '2026-05-01',
            'to' => '2026-05-31',
            'groupBy' => 'day',
            'format' => 'json',
        ]);

        $this->assertSame('text/csv', $response['contentType']);

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'http://api.test/api/v1/backoffice/reports/transactions/summary?')
                && ($query['from'] ?? null) === '2026-05-01T00:00:00Z'
                && ($query['to'] ?? null) === '2026-05-31T23:59:59Z'
                && ($query['groupBy'] ?? null) === 'DAY'
                && ($query['format'] ?? null) === 'csv';
        });
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
            'http://api.test/api/v1/backoffice/cards/card-1/unblock' => Http::response(['data' => ['id' => 'card-1']]),
            'http://api.test/api/v1/backoffice/cards/card-1/report-lost' => Http::response(['data' => ['id' => 'card-1']]),
            'http://api.test/api/v1/backoffice/cards/card-1/report-stolen' => Http::response(['data' => ['id' => 'card-1']]),
            'http://api.test/api/v1/backoffice/terminals/terminal-1/provision' => Http::response(['data' => ['terminalId' => 'terminal-1']]),
            'http://api.test/api/v1/backoffice/terminals/terminal-1/suspend' => Http::response(['data' => ['id' => 'terminal-1']]),
            'http://api.test/api/v1/backoffice/terminals/terminal-1/reactivate' => Http::response(['data' => ['id' => 'terminal-1']]),
        ]);

        $api = new HttpBackofficeApi;
        $api->suspendCustomer('cust-1', 'ignored by spec');
        $api->freezeWallet('wallet-1', 'ignored by spec');
        $api->blockCard('card-1', 'ignored by spec');
        $api->unblockCard('card-1');
        $api->reportCardLost('card-1', 'ignored by spec');
        $api->reportCardStolen('card-1', 'ignored by spec');
        $api->provisionTerminal('terminal-1', ['ignored' => 'by spec']);
        $api->suspendTerminal('terminal-1', 'ignored by spec');
        $api->reactivateTerminal('terminal-1');

        $recorded = Http::recorded();

        $this->assertCount(9, $recorded);

        foreach ($recorded as [$request]) {
            $this->assertSame('', $request->body());
        }
    }

    public function test_terminal_register_payload_matches_backoffice_dto(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/terminals' => Http::response(['data' => ['id' => 'terminal-1']], 201),
        ]);

        (new HttpBackofficeApi)->createTerminal([
            'serialNumber' => ' TRM-2024-001 ',
            'deviceModel' => ' ',
            'androidVersion' => ' 11.0 ',
            'appVersion' => '',
            'merchantId' => ' 11111111-1111-1111-1111-111111111111 ',
        ]);

        $request = Http::recorded()->first()[0];
        $payload = json_decode($request->body(), true);

        $this->assertSame('POST', $request->method());
        $this->assertSame('http://api.test/api/v1/backoffice/terminals', $request->url());
        $this->assertSame([
            'serialNumber' => 'TRM-2024-001',
            'androidVersion' => '11.0',
            'merchantId' => '11111111-1111-1111-1111-111111111111',
        ], $payload);
        $this->assertArrayNotHasKey('deviceModel', $payload);
        $this->assertArrayNotHasKey('appVersion', $payload);
    }

    public function test_service_provider_write_payloads_match_backoffice_dtos(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/service-providers' => Http::response(['data' => ['id' => 'approval-provider-create']], 202),
            'http://api.test/api/v1/backoffice/service-providers/sp-1' => Http::response(['data' => ['id' => 'approval-provider-update']], 202),
            'http://api.test/api/v1/backoffice/service-providers/sp-1/activate' => Http::response(['data' => ['id' => 'approval-provider-activate']], 202),
            'http://api.test/api/v1/backoffice/service-providers/sp-1/deactivate' => Http::response(['data' => ['id' => 'approval-provider-deactivate']], 202),
            'http://api.test/api/v1/backoffice/service-providers/sp-1/services' => Http::response(['data' => ['id' => 'approval-service-create']], 202),
            'http://api.test/api/v1/backoffice/service-providers/sp-1/services/bs-1' => Http::response(['data' => ['id' => 'approval-service-update']], 202),
            'http://api.test/api/v1/backoffice/service-providers/sp-1/services/bs-1/activate' => Http::response(['data' => ['id' => 'approval-service-activate']], 202),
            'http://api.test/api/v1/backoffice/service-providers/sp-1/services/bs-1/deactivate' => Http::response(['data' => ['id' => 'approval-service-deactivate']], 202),
        ]);

        $api = new HttpBackofficeApi;
        $api->createServiceProvider([
            'name' => '  Provider One  ',
            'code' => ' PROVIDER_ONE ',
            'type' => 'external_api',
            'baseUrl' => ' https://provider.test/api ',
            'credentialsRef' => ' ',
            'timeoutMillis' => '10000',
            'maxRetries' => '2',
            'retryBackoffMillis' => '500',
            'sandbox' => false,
            'supportsReferenceValidation' => true,
            'callbackSecretRef' => ' vault://providers/one/callback ',
        ]);
        $api->updateServiceProvider('sp-1', [
            'id' => 'sp-1',
            'name' => ' Provider One Updated ',
            'code' => 'SHOULD_NOT_SEND',
            'type' => 'INTERNAL',
            'baseUrl' => '',
            'credentialsRef' => ' vault://providers/one/api-key ',
            'timeoutMillis' => '15000',
            'maxRetries' => '3',
            'retryBackoffMillis' => '750',
            'sandbox' => true,
            'supportsReferenceValidation' => false,
            'callbackSecretRef' => '',
        ]);
        $api->activateServiceProvider('sp-1');
        $api->deactivateServiceProvider('sp-1');
        $api->createBillService(' sp-1 ', [
            'providerId' => 'ignored-path-provider-wins',
            'name' => ' Water Bills ',
            'code' => ' WATER_BILL ',
            'category' => 'water',
            'minAmount' => '1000',
            'maxAmount' => '',
        ]);
        $api->updateBillService('sp-1', 'bs-1', [
            'id' => 'bs-1',
            'providerId' => 'sp-1',
            'code' => 'SHOULD_NOT_SEND',
            'name' => ' Internet Bundles ',
            'category' => 'internet',
            'minAmount' => '',
            'maxAmount' => '250000',
        ]);
        $api->activateBillService('sp-1', 'bs-1');
        $api->deactivateBillService('sp-1', 'bs-1');

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        // Spec §6.12: the online-adapter fields (type/baseUrl/credentialsRef/callbackSecretRef/
        // timeoutMillis/maxRetries/retryBackoffMillis/sandbox) no longer exist and must NOT be
        // sent, even when the caller passes them. Create carries name + code + the validation flag.
        $this->assertSame('POST', $requests[0]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/service-providers', $requests[0]->url());
        $this->assertSame([
            'name' => 'Provider One',
            'code' => 'PROVIDER_ONE',
            'supportsReferenceValidation' => true,
        ], json_decode($requests[0]->body(), true));

        // Update carries only name + the validation flag (no code, no adapter fields).
        $this->assertSame('PUT', $requests[1]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/service-providers/sp-1', $requests[1]->url());
        $this->assertSame([
            'name' => 'Provider One Updated',
            'supportsReferenceValidation' => false,
        ], json_decode($requests[1]->body(), true));

        $this->assertSame('', $requests[2]->body());
        $this->assertSame('', $requests[3]->body());

        $this->assertSame('POST', $requests[4]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/service-providers/sp-1/services', $requests[4]->url());
        $this->assertSame([
            'providerId' => 'sp-1',
            'name' => 'Water Bills',
            'code' => 'WATER_BILL',
            'category' => 'WATER',
            'minAmount' => 1000,
        ], json_decode($requests[4]->body(), true));

        $this->assertSame('PUT', $requests[5]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/service-providers/sp-1/services/bs-1', $requests[5]->url());
        $this->assertSame([
            'name' => 'Internet Bundles',
            'category' => 'INTERNET',
            'maxAmount' => 250000,
        ], json_decode($requests[5]->body(), true));
        $this->assertSame('', $requests[6]->body());
        $this->assertSame('', $requests[7]->body());
    }

    public function test_service_provider_direct_controls_use_patch_endpoints(): void
    {
        // Spec §5.20: status + business-rules are direct PATCH (200), not maker-checker.
        Http::fake([
            'http://api.test/api/v1/backoffice/service-providers/sp-1/status' => Http::response(['data' => ['id' => 'sp-1', 'status' => 'MAINTENANCE']]),
            'http://api.test/api/v1/backoffice/service-providers/sp-1/business-rules' => Http::response(['data' => ['id' => 'sp-1']]),
        ]);

        $api = new HttpBackofficeApi;
        $api->changeServiceProviderStatus('sp-1', 'maintenance', '  scheduled maintenance  ');
        $api->updateServiceProviderBusinessRules('sp-1', [
            'processingHoursStart' => ' 08:00 ',
            'processingHoursEnd' => '',
            'processingDays' => 'MON-SAT',
            'announcedDelayHours' => '4',
            'referenceRegex' => '^[0-9]{11}$',
            'referenceMinLength' => '11',
            'referenceMaxLength' => '11',
            'referenceExample' => '',
        ]);

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        $this->assertSame('PATCH', $requests[0]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/service-providers/sp-1/status', $requests[0]->url());
        $this->assertSame([
            'status' => 'MAINTENANCE',
            'reason' => 'scheduled maintenance',
        ], json_decode($requests[0]->body(), true));

        $this->assertSame('PATCH', $requests[1]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/service-providers/sp-1/business-rules', $requests[1]->url());
        // Empty fields are dropped so only the supplied rules change.
        $this->assertSame([
            'processingHoursStart' => '08:00',
            'processingDays' => 'MON-SAT',
            'announcedDelayHours' => 4,
            'referenceRegex' => '^[0-9]{11}$',
            'referenceMinLength' => 11,
            'referenceMaxLength' => 11,
        ], json_decode($requests[1]->body(), true));
    }

    public function test_bill_payment_processing_endpoints_follow_spec(): void
    {
        // Spec §5.21: feature-flag probe, FIFO list, JSON reason actions, multipart complete/refund.
        Http::fake([
            'http://api.test/api/v1/backoffice/bill-payments?*' => Http::response([
                'data' => [['id' => 'bp-1', 'status' => 'QUEUED']],
                'pagination' => ['hasMore' => false, 'nextCursor' => null, 'limit' => 20],
            ]),
            'http://api.test/api/v1/backoffice/bill-payments/bp-1/take' => Http::response(['data' => ['id' => 'bp-1', 'status' => 'IN_PROCESSING']]),
            'http://api.test/api/v1/backoffice/bill-payments/bp-1/release' => Http::response(['data' => ['id' => 'bp-1', 'status' => 'QUEUED']]),
            'http://api.test/api/v1/backoffice/bill-payments/bp-1/requeue' => Http::response(['data' => ['id' => 'bp-1', 'status' => 'QUEUED']]),
            'http://api.test/api/v1/backoffice/bill-payments/bp-1/force-release' => Http::response(['data' => ['id' => 'bp-1', 'status' => 'QUEUED']]),
            'http://api.test/api/v1/backoffice/bill-payments/bp-1/complete' => Http::response(['data' => ['id' => 'bp-1', 'status' => 'SUCCEEDED']]),
            'http://api.test/api/v1/backoffice/bill-payments/bp-1/refund' => Http::response(['data' => ['id' => 'bp-1', 'status' => 'FAILED_REFUNDED']]),
        ]);

        $api = new HttpBackofficeApi;

        $this->assertTrue($api->billPaymentProcessingEnabled());

        $api->billPaymentsPage([
            'status' => 'queued',
            'providerId' => 'sp-1',
            'minAmount' => '1000',
            'page' => 0,
            'size' => 20,
        ]);
        $api->takeBillPayment('bp-1');
        $api->releaseBillPayment('bp-1');
        $api->requeueBillPayment('bp-1', '  provider outage  ');
        $api->forceReleaseBillPayment('bp-1', 'operator unreachable');
        $api->completeBillPayment(
            'bp-1',
            ['externalReference' => 'MWE-778812', 'internalNotes' => 'settled'],
            UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
            '22222222-0000-0000-0000-000000000002',
        );
        $api->refundBillPayment('bp-1', 'wrong meter', null);

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        // Feature-flag probe.
        $this->assertSame('GET', $requests[0]->method());
        $this->assertStringStartsWith('http://api.test/api/v1/backoffice/bill-payments?', $requests[0]->url());

        // FIFO list query maps status (uppercased), providerId, minAmount, page/size.
        $listUrl = $requests[1]->url();
        $this->assertStringContainsString('status=QUEUED', $listUrl);
        $this->assertStringContainsString('providerId=sp-1', $listUrl);
        $this->assertStringContainsString('minAmount=1000', $listUrl);

        // Take / release: no body.
        $this->assertSame('POST', $requests[2]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/bill-payments/bp-1/take', $requests[2]->url());
        $this->assertSame('', $requests[2]->body());
        $this->assertSame('POST', $requests[3]->method());
        $this->assertSame('', $requests[3]->body());

        // Requeue / force-release: JSON reason, trimmed.
        $this->assertSame('http://api.test/api/v1/backoffice/bill-payments/bp-1/requeue', $requests[4]->url());
        $this->assertSame(['reason' => 'provider outage'], json_decode($requests[4]->body(), true));
        $this->assertSame(['reason' => 'operator unreachable'], json_decode($requests[5]->body(), true));

        // Complete: multipart with the 4-eyes header.
        $this->assertSame('POST', $requests[6]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/bill-payments/bp-1/complete', $requests[6]->url());
        $this->assertStringContainsString('multipart/form-data', $requests[6]->header('Content-Type')[0]);
        $this->assertSame('22222222-0000-0000-0000-000000000002', $requests[6]->header('X-Second-Approver-Operator-Id')[0]);
        $completeBody = $requests[6]->body();
        $this->assertStringContainsString('name="externalReference"', $completeBody);
        $this->assertStringContainsString('MWE-778812', $completeBody);
        $this->assertStringContainsString('name="file"', $completeBody);

        // Refund: multipart with reason; no second-approver header.
        $this->assertSame('http://api.test/api/v1/backoffice/bill-payments/bp-1/refund', $requests[7]->url());
        $this->assertStringContainsString('multipart/form-data', $requests[7]->header('Content-Type')[0]);
        $this->assertStringContainsString('wrong meter', $requests[7]->body());
        $this->assertEmpty($requests[7]->header('X-Second-Approver-Operator-Id'));
    }

    public function test_notification_inbox_uses_shared_endpoints(): void
    {
        // Spec §5.22: the inbox lives under /api/v1/notifications/** (a shared controller),
        // NOT under /api/v1/backoffice/*. Every call is scoped server-side to the principal.
        Http::fake([
            'http://api.test/api/v1/notifications/unread' => Http::response(['data' => ['unread' => 4]]),
            'http://api.test/api/v1/notifications/read-all' => Http::response(['data' => ['updated' => 4]]),
            'http://api.test/api/v1/notifications/n-1/read' => Http::response(['data' => null]),
            'http://api.test/api/v1/notifications?*' => Http::response([
                'data' => [[
                    'id' => 'n-1',
                    'category' => 'BILL_PAYMENT',
                    'title' => 'Nouveau paiement à traiter',
                    'body' => 'Un paiement de 15 250 KMF.',
                    'data' => '{"billPaymentId":"bp-1","type":"SERVICE_PAYMENT_QUEUED"}',
                    'status' => 'UNREAD',
                    'createdAt' => '2026-05-21T06:05:00Z',
                    'readAt' => null,
                ]],
                'pagination' => ['hasMore' => false, 'nextCursor' => null, 'limit' => 20],
            ]),
        ]);

        $api = new HttpBackofficeApi;

        $this->assertSame(4, $api->unreadNotificationCount());
        $rows = $api->notifications(150); // clamped to max 100
        $this->assertCount(1, $rows);
        $this->assertSame('BILL_PAYMENT', $rows[0]['category']);
        $api->markNotificationRead('n-1');
        $this->assertSame(4, $api->markAllNotificationsRead());

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        // None of the calls must hit the /backoffice prefix.
        foreach ($requests as $request) {
            $this->assertStringNotContainsString('/api/v1/backoffice/', $request->url());
        }

        $this->assertSame('http://api.test/api/v1/notifications/unread', $requests[0]->url());
        $this->assertStringStartsWith('http://api.test/api/v1/notifications?', $requests[1]->url());
        $this->assertStringContainsString('limit=100', $requests[1]->url());
        $this->assertSame('POST', $requests[2]->method());
        $this->assertSame('http://api.test/api/v1/notifications/n-1/read', $requests[2]->url());
        $this->assertSame('http://api.test/api/v1/notifications/read-all', $requests[3]->url());
    }

    public function test_bill_provider_settlement_request_carries_provider_code(): void
    {
        // Spec §5.18/§6.8: providerCode is required and identifies the provider the
        // disbursement is for; amount is coerced to a long.
        Http::fake([
            'http://api.test/api/v1/backoffice/bill-provider-settlement/requests' => Http::response(['data' => ['approvalId' => 'apr-1']], 201),
        ]);

        $api = new HttpBackofficeApi;
        $api->requestBillProviderSettlement([
            'providerCode' => 'MWE',
            'amount' => '250000',
            'externalReference' => '',
            'notes' => 'May settlement',
        ]);

        $request = Http::recorded()->first()[0];
        $body = json_decode($request->body(), true);

        $this->assertSame('http://api.test/api/v1/backoffice/bill-provider-settlement/requests', $request->url());
        $this->assertSame('MWE', $body['providerCode']);
        $this->assertSame(250000, $body['amount']);
        $this->assertSame('May settlement', $body['notes']);
        // Empty optional fields are dropped, not sent as ''.
        $this->assertArrayNotHasKey('externalReference', $body);
    }

    public function test_bill_payment_processing_disabled_when_feature_flag_off(): void
    {
        // Spec §5.21 / §11.6: a 404 on the probe means "feature disabled", not an error.
        Http::fake([
            'http://api.test/api/v1/backoffice/bill-payments*' => Http::response(['error' => ['code' => 'NOT_FOUND', 'message' => 'Not found']], 404),
        ]);

        $api = new HttpBackofficeApi;

        $this->assertFalse($api->billPaymentProcessingEnabled());
    }

    public function test_card_write_payloads_match_backoffice_dtos(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/cards/card-1/close' => Http::response(['data' => ['id' => 'card-1']]),
            'http://api.test/api/v1/backoffice/cards/card-2/close' => Http::response(['data' => ['id' => 'card-2']]),
            'http://api.test/api/v1/backoffice/card-stock/import' => Http::response(['data' => [['id' => 'stock-1']]]),
            'http://api.test/api/v1/backoffice/card-stock/assign' => Http::response(['data' => [['id' => 'stock-1']]]),
        ]);

        $api = new HttpBackofficeApi;
        $api->closeCard('card-1', '   ');
        $api->closeCard('card-2', ' Expired card ');
        $api->importCardStock([
            'batchRef' => ' BATCH-2026-05 ',
            'producedAt' => ' 2026-05-08 ',
            'cards' => [
                [
                    'nfcUid' => 'a1b2c3d4e5f6ab',
                    'internalCardNumber' => ' LP-000001 ',
                    'authKeyEncryptedBase64' => '',
                    'authKeyVersion' => '2',
                ],
                [
                    'nfcUid' => '00112233445566',
                    'internalCardNumber' => 'LP-000002',
                    'authKeyEncryptedBase64' => ' base64key ',
                    'authKeyVersion' => 0,
                ],
            ],
        ]);
        $api->assignCardStock([
            'agentId' => ' 11111111-1111-1111-1111-111111111111 ',
            'cardStockIds' => [
                ' 22222222-2222-2222-2222-222222222222 ',
                '',
                '33333333-3333-3333-3333-333333333333',
                '22222222-2222-2222-2222-222222222222',
            ],
        ]);

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        $this->assertSame('', $requests[0]->body());
        $this->assertSame(['reason' => 'Expired card'], json_decode($requests[1]->body(), true));
        $this->assertSame([
            'batchRef' => 'BATCH-2026-05',
            'producedAt' => '2026-05-08',
            'cards' => [
                [
                    'nfcUid' => 'A1B2C3D4E5F6AB',
                    'internalCardNumber' => 'LP-000001',
                    'authKeyVersion' => 2,
                ],
                [
                    'nfcUid' => '00112233445566',
                    'internalCardNumber' => 'LP-000002',
                    'authKeyEncryptedBase64' => 'base64key',
                    'authKeyVersion' => 0,
                ],
            ],
        ], json_decode($requests[2]->body(), true));
        $this->assertSame([
            'agentId' => '11111111-1111-1111-1111-111111111111',
            'cardStockIds' => [
                '22222222-2222-2222-2222-222222222222',
                '33333333-3333-3333-3333-333333333333',
            ],
        ], json_decode($requests[3]->body(), true));
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
            'phoneCountryCode' => '269',
            'phoneNumber' => '3211234',
            'contractRef' => 'AGT-2026-001',
        ], json_decode($requests[0]->body(), true));

        $this->assertSame([
            'amount' => 500000,
            'notes' => 'float top-up',
        ], json_decode($requests[1]->body(), true));

        $this->assertSame([
            'businessName' => 'Boutique Omar',
            'legalName' => 'SARL Omar Commerce',
            'businessType' => 'SOLE_TRADER',
            'phoneCountryCode' => '269',
            'phoneNumber' => '3215678',
            'addressIsland' => 'Grande Comore',
            'addressCity' => 'Moroni',
            'category' => 'RETAIL',
        ], json_decode($requests[2]->body(), true));

        $this->assertArrayNotHasKey('address', json_decode($requests[2]->body(), true));

        $this->assertSame([
            'email' => 'ops@lipa.km',
            'password' => 'SecurePass123!',
            'fullName' => 'Ali Hassan',
            'role' => 'OPERATOR',
        ], json_decode($requests[3]->body(), true));

        $this->assertSame('', $requests[4]->body());
        $this->assertSame(['reason' => 'Duplicate request'], json_decode($requests[5]->body(), true));
    }

    public function test_limit_profile_assignment_endpoints_follow_spec(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/customers/cust-1/limit-profile' => Http::response(['data' => ['id' => 'approval-1']], 202),
            'http://api.test/api/v1/backoffice/agents/agent-1/limit-profile' => Http::response(['data' => ['id' => 'approval-2']], 202),
            'http://api.test/api/v1/backoffice/merchants/merchant-1/limit-profile' => Http::response(['data' => ['id' => 'approval-3']], 202),
        ]);

        $api = new HttpBackofficeApi;
        $api->assignCustomerLimitProfile('cust-1', ' lp-01 ');
        $api->assignAgentLimitProfile('agent-1', 'lp-02');
        $api->assignMerchantLimitProfile('merchant-1', 'lp-03');

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        $this->assertSame('PATCH', $requests[0]->method());
        $this->assertSame('PATCH', $requests[1]->method());
        $this->assertSame('PATCH', $requests[2]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/customers/cust-1/limit-profile', $requests[0]->url());
        $this->assertSame('http://api.test/api/v1/backoffice/agents/agent-1/limit-profile', $requests[1]->url());
        $this->assertSame('http://api.test/api/v1/backoffice/merchants/merchant-1/limit-profile', $requests[2]->url());
        $this->assertSame(['limitProfileId' => 'lp-01'], json_decode($requests[0]->body(), true));
        $this->assertSame(['limitProfileId' => 'lp-02'], json_decode($requests[1]->body(), true));
        $this->assertSame(['limitProfileId' => 'lp-03'], json_decode($requests[2]->body(), true));
    }

    public function test_customer_kyc_review_endpoints_follow_spec(): void
    {
        $document = [
            'id' => 'doc-1',
            'ownerActorType' => 'CUSTOMER',
            'ownerActorId' => 'cust-1',
            'documentType' => 'NATIONAL_ID',
            'contentHash' => 'abc123',
            'contentType' => 'application/pdf',
            'uploadedByActorType' => 'CUSTOMER',
            'uploadedByActorId' => 'cust-1',
            'uploadedAt' => '2026-05-08T10:15:00Z',
            'status' => 'PENDING_REVIEW',
        ];

        Http::fake([
            'http://api.test/api/v1/backoffice/customers/cust-1/kyc-documents' => Http::response(['data' => [$document]]),
            'http://api.test/api/v1/backoffice/kyc-documents/doc-1' => Http::response(['data' => $document]),
            'http://api.test/api/v1/backoffice/kyc-documents/doc-1/file' => Http::response(
                '%PDF-1.4',
                200,
                ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="doc-1.pdf"'],
            ),
            'http://api.test/api/v1/backoffice/kyc-documents/doc-1/approve' => Http::response(['data' => $document + ['status' => 'ACCEPTED']]),
            'http://api.test/api/v1/backoffice/kyc-documents/doc-1/reject' => Http::response(['data' => $document + ['status' => 'REJECTED']]),
            'http://api.test/api/v1/backoffice/customers/cust-1/kyc-level' => Http::response(['data' => ['id' => 'cust-1', 'kycLevel' => 'KYC_VERIFIED']]),
            'http://api.test/api/v1/backoffice/customers/cust-1/activate' => Http::response(['data' => ['id' => 'cust-1', 'status' => 'ACTIVE']]),
        ]);

        $api = new HttpBackofficeApi;
        $this->assertSame([$document], $api->customerKycDocuments('cust-1'));
        $this->assertSame($document, $api->kycDocument('doc-1'));
        $file = $api->downloadKycDocumentFile('doc-1');
        $api->approveKycDocument('doc-1');
        $api->rejectKycDocument('doc-1', ' blurred ');
        $api->changeCustomerKycLevel('cust-1', ' kyc_verified ', '');
        $api->activateCustomer('cust-1');

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        $this->assertSame('application/pdf', $file['contentType']);
        $this->assertSame('doc-1.pdf', $file['filename']);
        $this->assertSame('%PDF-1.4', $file['body']);

        $this->assertSame('GET', $requests[0]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/customers/cust-1/kyc-documents', $requests[0]->url());
        $this->assertSame('http://api.test/api/v1/backoffice/kyc-documents/doc-1', $requests[1]->url());
        $this->assertSame('http://api.test/api/v1/backoffice/kyc-documents/doc-1/file', $requests[2]->url());
        $this->assertTrue($requests[2]->hasHeader('Accept', '*/*'));
        $this->assertFalse($requests[2]->hasHeader('Content-Type', 'application/json'));
        $this->assertSame('', $requests[2]->body());
        $this->assertSame('http://api.test/api/v1/backoffice/kyc-documents/doc-1/approve', $requests[3]->url());
        $this->assertSame('', $requests[3]->body());
        $this->assertSame(['reason' => 'blurred'], json_decode($requests[4]->body(), true));
        $this->assertSame(['kycLevel' => 'KYC_VERIFIED'], json_decode($requests[5]->body(), true));
        $this->assertSame('http://api.test/api/v1/backoffice/customers/cust-1/activate', $requests[6]->url());
        $this->assertSame('', $requests[6]->body());
    }

    public function test_agent_merchant_kyc_review_endpoints_follow_spec(): void
    {
        $agentDoc = [
            'id' => 'adoc-1',
            'ownerActorType' => 'AGENT',
            'ownerActorId' => 'ag-1',
            'documentType' => 'NATIONAL_ID',
            'contentHash' => 'abc123',
            'contentType' => 'application/pdf',
            'uploadedByActorType' => 'BACKOFFICE_USER',
            'uploadedByActorId' => 'bo-1',
            'uploadedAt' => '2026-05-11T09:00:00Z',
            'status' => 'PENDING_REVIEW',
        ];
        $merchantDoc = ['id' => 'mdoc-1'] + $agentDoc + ['ownerActorType' => 'MERCHANT'];

        Http::fake([
            'http://api.test/api/v1/backoffice/agents/ag-1/kyc-documents' => Http::response(['data' => [$agentDoc]]),
            'http://api.test/api/v1/backoffice/agents/kyc-documents/adoc-1' => Http::response(['data' => $agentDoc]),
            'http://api.test/api/v1/backoffice/agents/kyc-documents/adoc-1/file' => Http::response(
                '%PDF-1.4',
                200,
                ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="adoc-1.pdf"'],
            ),
            'http://api.test/api/v1/backoffice/agents/kyc-documents/adoc-1/approve' => Http::response(['data' => $agentDoc + ['status' => 'ACCEPTED']]),
            'http://api.test/api/v1/backoffice/agents/kyc-documents/adoc-1/reject' => Http::response(['data' => $agentDoc + ['status' => 'REJECTED']]),
            'http://api.test/api/v1/backoffice/agents/ag-1/kyc-level' => Http::response(['data' => ['id' => 'ag-1', 'kycLevel' => 'KYC_ENHANCED']]),
            'http://api.test/api/v1/backoffice/agents/ag-1/activate' => Http::response(['data' => ['id' => 'ag-1', 'status' => 'ACTIVE']]),
            'http://api.test/api/v1/backoffice/merchants/mc-1/kyc-documents' => Http::response(['data' => $merchantDoc], 201),
        ]);

        $api = new HttpBackofficeApi;

        $this->assertSame([$agentDoc], $api->actorKycDocuments('agents', 'ag-1'));
        $this->assertSame($agentDoc, $api->actorKycDocument('agents', 'adoc-1'));
        $file = $api->downloadActorKycDocumentFile('agents', 'adoc-1');
        $api->approveActorKycDocument('agents', 'adoc-1');
        $api->rejectActorKycDocument('agents', 'adoc-1', ' blurred ');
        $api->changeActorKycLevel('agents', 'ag-1', ' kyc_enhanced ');
        $api->activateActor('agents', 'ag-1');

        $upload = UploadedFile::fake()->create('license.pdf', 64, 'application/pdf');
        $api->uploadActorKycDocument('merchants', 'mc-1', ' business_license ', $upload);

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        $this->assertSame('application/pdf', $file['contentType']);
        $this->assertSame('adoc-1.pdf', $file['filename']);
        $this->assertSame('%PDF-1.4', $file['body']);

        // Owner-scoped paths (spec §5.3b).
        $this->assertSame('http://api.test/api/v1/backoffice/agents/ag-1/kyc-documents', $requests[0]->url());
        $this->assertSame('http://api.test/api/v1/backoffice/agents/kyc-documents/adoc-1', $requests[1]->url());
        $this->assertSame('http://api.test/api/v1/backoffice/agents/kyc-documents/adoc-1/file', $requests[2]->url());
        $this->assertTrue($requests[2]->hasHeader('Accept', '*/*'));
        $this->assertSame('http://api.test/api/v1/backoffice/agents/kyc-documents/adoc-1/approve', $requests[3]->url());
        $this->assertSame('', $requests[3]->body());
        $this->assertSame(['reason' => 'blurred'], json_decode($requests[4]->body(), true));
        // ChangeActorKycLevelRequest carries only kycLevel — no nextReviewDate.
        $this->assertSame(['kycLevel' => 'KYC_ENHANCED'], json_decode($requests[5]->body(), true));
        $this->assertSame('http://api.test/api/v1/backoffice/agents/ag-1/activate', $requests[6]->url());
        $this->assertSame('', $requests[6]->body());

        // Upload is multipart/form-data with documentType + file.
        $this->assertSame('POST', $requests[7]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/merchants/mc-1/kyc-documents', $requests[7]->url());
        $this->assertTrue($requests[7]->isMultipart());
        $multipart = collect($requests[7]->data())->keyBy('name');
        $this->assertSame('BUSINESS_LICENSE', $multipart['documentType']['contents']);
        $this->assertTrue($multipart->has('file'));
    }

    public function test_actor_kyc_owner_type_is_validated(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new HttpBackofficeApi)->actorKycDocuments('customers', 'c-1');
    }

    public function test_actor_pin_reset_endpoints_follow_spec_without_body(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/customers/cust-1/auth-pin/reset' => Http::response(['data' => ['id' => 'cust-1']], 200),
            'http://api.test/api/v1/backoffice/agents/agent-1/auth-pin/reset' => Http::response(['data' => ['id' => 'agent-1']], 200),
            'http://api.test/api/v1/backoffice/merchants/merchant-1/auth-pin/reset' => Http::response(['data' => ['id' => 'merchant-1']], 200),
        ]);

        $api = new HttpBackofficeApi;
        $api->resetCustomerAuthPin('cust-1');
        $api->resetAgentAuthPin('agent-1');
        $api->resetMerchantAuthPin('merchant-1');

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        $this->assertSame('POST', $requests[0]->method());
        $this->assertSame('POST', $requests[1]->method());
        $this->assertSame('POST', $requests[2]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/customers/cust-1/auth-pin/reset', $requests[0]->url());
        $this->assertSame('http://api.test/api/v1/backoffice/agents/agent-1/auth-pin/reset', $requests[1]->url());
        $this->assertSame('http://api.test/api/v1/backoffice/merchants/merchant-1/auth-pin/reset', $requests[2]->url());
        $this->assertSame('', $requests[0]->body());
        $this->assertSame('', $requests[1]->body());
        $this->assertSame('', $requests[2]->body());
    }

    public function test_rules_limits_create_payloads_match_backoffice_dtos(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/fee-rules' => Http::response(['data' => ['id' => 'approval-fee']], 202),
            'http://api.test/api/v1/backoffice/commission-rules' => Http::response(['data' => ['id' => 'approval-commission']], 202),
            'http://api.test/api/v1/backoffice/limit-profiles' => Http::response(['data' => ['id' => 'approval-limit']], 202),
            'http://api.test/api/v1/backoffice/control-thresholds' => Http::response(['data' => ['id' => 'approval-threshold']], 202),
        ]);

        $api = new HttpBackofficeApi;
        $api->createFeeRule([
            'name' => ' Standard Cash-in ',
            'description' => ' ',
            'transactionType' => 'cash_in',
            'calculationType' => 'percentage',
            'flatAmount' => '999',
            'percentage' => '1.5',
            'minFeeAmount' => '',
            'maxFeeAmount' => '5000',
            'feeBearer' => 'sender',
            'priority' => '4',
            'validFrom' => '2026-05-08T12:30',
            'activeOnApproval' => false,
        ]);
        $api->createCommissionRule([
            'name' => ' Agent payout ',
            'transactionType' => 'cash_out',
            'agentId' => ' ',
            'calculationType' => 'flat',
            'flatAmount' => '250',
            'percentage' => '1.98',
            'settlementMode' => 'batch_daily',
            'priority' => '2',
            'validFrom' => '2026-05-08T13:00',
            'activeOnApproval' => true,
        ]);
        $api->createLimitProfile([
            'name' => ' Verified standard ',
            'applicableActorTypes' => ['customer', ' AGENT '],
            'requiredKycLevel' => 'kyc_verified',
            'minTransactionAmount' => '',
            'maxTransactionAmount' => '500000',
            'maxDailyAmount' => '1000000',
            'maxWeeklyAmount' => null,
            'maxMonthlyTransactionCount' => '200',
        ]);
        $api->createControlThreshold([
            'transactionType' => 'payment',
            'actorType' => 'merchant',
            'scopeType' => 'global',
            'scopeId' => 'scope-should-not-be-sent',
            'currency' => 'kmf',
            'pinRequiredAboveAmount' => '',
            'confirmationRequiredAboveAmount' => '100000',
            'approvalRequiredAboveAmount' => null,
            'approvalType' => '',
        ]);

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        $this->assertSame('POST', $requests[0]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/fee-rules', $requests[0]->url());
        $this->assertSame([
            'name' => 'Standard Cash-in',
            'transactionType' => 'CASH_IN',
            'calculationType' => 'PERCENTAGE',
            'feeBearer' => 'SENDER',
            'priority' => 4,
            'validFrom' => '2026-05-08T12:30:00Z',
            'activeOnApproval' => false,
            'percentage' => 1.5,
            'maxFeeAmount' => 5000,
        ], json_decode($requests[0]->body(), true));

        $this->assertSame('http://api.test/api/v1/backoffice/commission-rules', $requests[1]->url());
        $this->assertSame([
            'name' => 'Agent payout',
            'transactionType' => 'CASH_OUT',
            'calculationType' => 'FLAT',
            'settlementMode' => 'BATCH_DAILY',
            'priority' => 2,
            'validFrom' => '2026-05-08T13:00:00Z',
            'activeOnApproval' => true,
            'flatAmount' => 250,
        ], json_decode($requests[1]->body(), true));

        $this->assertSame('http://api.test/api/v1/backoffice/limit-profiles', $requests[2]->url());
        $this->assertSame([
            'name' => 'Verified standard',
            'applicableActorTypes' => ['CUSTOMER', 'AGENT'],
            'maxTransactionAmount' => 500000,
            'maxDailyAmount' => 1000000,
            'maxMonthlyTransactionCount' => 200,
            'requiredKycLevel' => 'KYC_VERIFIED',
        ], json_decode($requests[2]->body(), true));

        $this->assertSame('http://api.test/api/v1/backoffice/control-thresholds', $requests[3]->url());
        $this->assertSame([
            'transactionType' => 'PAYMENT',
            'actorType' => 'MERCHANT',
            'scopeType' => 'GLOBAL',
            'currency' => 'KMF',
            'confirmationRequiredAboveAmount' => 100000,
        ], json_decode($requests[3]->body(), true));
    }

    public function test_rules_limits_no_body_actions_follow_spec(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/fee-rules/fee-1/activate' => Http::response(['data' => ['id' => 'approval-1']], 202),
            'http://api.test/api/v1/backoffice/commission-rules/commission-1/deactivate' => Http::response(['data' => ['id' => 'approval-2']], 202),
            'http://api.test/api/v1/backoffice/limit-profiles/limit-1/activate' => Http::response(['data' => ['id' => 'approval-3']], 202),
            'http://api.test/api/v1/backoffice/control-thresholds/threshold-1/deactivate' => Http::response(['data' => ['id' => 'approval-4']], 202),
        ]);

        $api = new HttpBackofficeApi;
        $api->activateRule('fee', 'fee-1');
        $api->deactivateRule('commission', 'commission-1');
        $api->activateRule('limit', 'limit-1');
        $api->deactivateRule('threshold', 'threshold-1');

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        $this->assertSame('POST', $requests[0]->method());
        $this->assertSame('POST', $requests[1]->method());
        $this->assertSame('PATCH', $requests[2]->method());
        $this->assertSame('POST', $requests[3]->method());

        foreach ($requests as $request) {
            $this->assertSame('', $request->body());
        }
    }

    public function test_rules_limits_supersede_uses_post_supersede_endpoints(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/fee-rules/fee-1/supersede' => Http::response(['data' => ['id' => 'approval-fee-v2']], 202),
            'http://api.test/api/v1/backoffice/commission-rules/commission-1/supersede' => Http::response(['data' => ['id' => 'approval-commission-v2']], 202),
            'http://api.test/api/v1/backoffice/limit-profiles/limit-1/supersede' => Http::response(['data' => ['id' => 'approval-limit-v2']], 202),
            'http://api.test/api/v1/backoffice/control-thresholds/threshold-1/supersede' => Http::response(['data' => ['id' => 'approval-threshold-v2']], 202),
        ]);

        $api = new HttpBackofficeApi;
        $api->supersedeFeeRule('fee-1', [
            'name' => 'Standard Cash-in v2',
            'transactionType' => 'CASH_IN',
            'calculationType' => 'PERCENTAGE',
            'percentage' => '2',
            'feeBearer' => 'SENDER',
            'priority' => 4,
            'validFrom' => '2026-05-08T12:30:00Z',
            'activeOnApproval' => false,
        ]);
        $api->supersedeCommissionRule('commission-1', [
            'name' => 'Agent payout v2',
            'transactionType' => 'CASH_OUT',
            'calculationType' => 'FLAT',
            'flatAmount' => '300',
            'settlementMode' => 'BATCH_DAILY',
            'priority' => 2,
            'validFrom' => '2026-05-08T13:00:00Z',
            'activeOnApproval' => true,
        ]);
        $api->supersedeLimitProfile('limit-1', [
            'name' => 'Verified standard v2',
            'applicableActorTypes' => ['CUSTOMER'],
            'requiredKycLevel' => 'KYC_VERIFIED',
            'maxTransactionAmount' => '600000',
        ]);
        $api->supersedeControlThreshold('threshold-1', [
            'transactionType' => 'PAYMENT',
            'actorType' => 'MERCHANT',
            'scopeType' => 'GLOBAL',
            'currency' => 'KMF',
            'confirmationRequiredAboveAmount' => '150000',
        ]);

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        $this->assertSame('POST', $requests[0]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/fee-rules/fee-1/supersede', $requests[0]->url());
        $this->assertSame('POST', $requests[1]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/commission-rules/commission-1/supersede', $requests[1]->url());
        $this->assertSame('POST', $requests[2]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/limit-profiles/limit-1/supersede', $requests[2]->url());
        $this->assertSame('POST', $requests[3]->method());
        $this->assertSame('http://api.test/api/v1/backoffice/control-thresholds/threshold-1/supersede', $requests[3]->url());

        foreach ($requests as $request) {
            $this->assertNotSame('', $request->body());
        }
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

    public function test_platform_liquidity_top_up_uses_spec_endpoint_and_payload(): void
    {
        Http::fake([
            'http://api.test/api/v1/backoffice/platform-liquidity/balances' => Http::response([
                'data' => [
                    'liquidityBalance' => 320000000,
                    'fundingClearingBalance' => 8750000,
                    'currency' => 'KMF',
                ],
            ]),
            'http://api.test/api/v1/backoffice/platform-liquidity/top-up-requests' => Http::response([
                'data' => [
                    'id' => 'approval-1',
                    'type' => 'PLATFORM_LIQUIDITY_TOP_UP',
                    'status' => 'PENDING_APPROVAL',
                ],
            ], 201),
        ]);

        $api = new HttpBackofficeApi;
        $balances = $api->platformLiquidityBalances();
        $approval = $api->requestPlatformLiquidityTopUp([
            'amount' => '3500000',
            'currency' => 'KMF',
            'externalReference' => ' WIRE-2026-05-009 ',
            'source' => ' BANK_WIRE ',
            'notes' => ' Treasury funding injection ',
        ]);

        $requests = Http::recorded()->map(fn ($record) => $record[0])->values();

        $this->assertSame(320000000, $balances['liquidityBalance']);
        $this->assertSame('PLATFORM_LIQUIDITY_TOP_UP', $approval['type']);
        $this->assertSame('http://api.test/api/v1/backoffice/platform-liquidity/balances', $requests[0]->url());
        $this->assertSame('http://api.test/api/v1/backoffice/platform-liquidity/top-up-requests', $requests[1]->url());
        $this->assertSame([
            'amount' => 3500000,
            'currency' => 'KMF',
            'externalReference' => 'WIRE-2026-05-009',
            'source' => 'BANK_WIRE',
            'notes' => 'Treasury funding injection',
        ], json_decode($requests[1]->body(), true));
    }
}
