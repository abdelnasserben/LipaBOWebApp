<?php

namespace Tests\Feature;

use App\Services\Api\Contracts\BackofficeApiContract;
use App\Services\Api\MockBackofficeApi;
use Livewire\Livewire;
use Tests\TestCase;

class BillPaymentWorklistTest extends TestCase
{
    public function test_take_keeps_in_processing_actions_available_when_detail_has_no_assignment(): void
    {
        $this->withoutVite();

        $this->app->instance(BackofficeApiContract::class, new class extends MockBackofficeApi
        {
            private bool $taken = false;

            public function serviceProviders(array $filters = []): array
            {
                return [['id' => 'sp-test', 'name' => 'Provider Test']];
            }

            public function billPaymentsPage(array $filters = []): array
            {
                return [
                    'data' => [$this->payment()],
                    'pagination' => ['nextCursor' => null, 'hasMore' => false, 'limit' => 20],
                ];
            }

            public function billPayment(string $id): ?array
            {
                return $this->payment();
            }

            public function takeBillPayment(string $id): array
            {
                $this->taken = true;

                return ['id' => $id, 'status' => 'IN_PROCESSING'];
            }

            private function payment(): array
            {
                return [
                    'id' => 'bp-take',
                    'customerId' => 'cust-test',
                    'serviceId' => 'bs-test',
                    'providerId' => 'sp-test',
                    'reference' => '21000123456',
                    'requestedAmount' => 15000,
                    'feeAmount' => 250,
                    'netAmount' => 14750,
                    'heldAmount' => 15250,
                    'currency' => 'KMF',
                    'status' => $this->taken ? 'IN_PROCESSING' : 'QUEUED',
                    'externalReference' => null,
                    'internalNotes' => null,
                    'processedByOperatorId' => null,
                    'secondApproverOperatorId' => null,
                    'proofRef' => null,
                    'retryCount' => 0,
                    'transactionId' => null,
                    'assignment' => null,
                    'queuedAt' => '2026-05-21T06:05:00Z',
                    'processingStartedAt' => $this->taken ? '2026-05-21T06:10:00Z' : null,
                    'completedAt' => null,
                    'createdAt' => '2026-05-21T06:05:00Z',
                    'updatedAt' => '2026-05-21T06:10:00Z',
                ];
            }
        });

        Livewire::withQueryParams([])
            ->test('bill-payments.bill-payment-worklist')
            ->set('selected', [
                'id' => 'bp-take',
                'customerId' => 'cust-test',
                'serviceId' => 'bs-test',
                'providerId' => 'sp-test',
                'reference' => '21000123456',
                'requestedAmount' => 15000,
                'feeAmount' => 250,
                'netAmount' => 14750,
                'heldAmount' => 15250,
                'currency' => 'KMF',
                'status' => 'QUEUED',
                'externalReference' => null,
                'internalNotes' => null,
                'processedByOperatorId' => null,
                'secondApproverOperatorId' => null,
                'proofRef' => null,
                'retryCount' => 0,
                'transactionId' => null,
                'assignment' => null,
                'queuedAt' => '2026-05-21T06:05:00Z',
                'processingStartedAt' => null,
                'completedAt' => null,
                'createdAt' => '2026-05-21T06:05:00Z',
                'updatedAt' => '2026-05-21T06:05:00Z',
            ])
            ->call('take', 'bp-take')
            ->assertSet('selected.status', 'IN_PROCESSING')
            ->assertSet('selected.processedByOperatorId', '11111111-0000-0000-0000-000000000001')
            ->assertSee('Mark succeeded')
            ->assertSee('Fail + refund')
            ->assertSee('Retry later')
            ->assertDontSee('No actions available for this status.');
    }

    protected function setUp(): void
    {
        parent::setUp();

        session([
            'bo_user' => [
                'id' => '11111111-0000-0000-0000-000000000001',
                'email' => 'operator@test.local',
                'fullName' => 'Operator Test',
                'role' => 'OPERATOR',
                'permissions' => [
                    'BILL_PAYMENT_PROCESS_VIEW',
                    'BILL_PAYMENT_PROCESS',
                    'BILL_PAYMENT_COMPLETE',
                    'BILL_PAYMENT_REFUND',
                    'BILL_PAYMENT_REQUEUE',
                ],
            ],
        ]);
    }
}
