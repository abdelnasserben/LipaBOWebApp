<?php

namespace App\Services\Api;

use App\Services\Api\Contracts\BackofficeApiContract;
use App\Services\Mock\MockDataService as M;
use Illuminate\Support\Str;

/**
 * MockBackofficeApi
 *
 * Reads come from the centralized fixture store (MockDataService).
 * Writes/mutations are no-ops that return a plausible response shape
 * matching the API spec, so the UI flow can be exercised end-to-end
 * without a live backend.
 *
 * IMPORTANT: do not put fixture data in this file. All mock data lives
 * in MockDataService and must mirror the API spec exactly.
 */
class MockBackofficeApi implements BackofficeApiContract
{
    // ── Customers ──────────────────────────────────────────────────────────
    public function customers(array $filters = []): array { return M::customers($filters); }
    public function customer(string $id): ?array { return M::customer($id); }
    public function suspendCustomer(string $id, string $reason = ''): array { return $this->ok(); }
    public function reactivateCustomer(string $id): array { return $this->ok(); }
    public function requestCustomerClosure(string $id, string $reason = ''): array { return $this->fakeApproval('CLOSE_CUSTOMER', $id); }

    // ── Agents ─────────────────────────────────────────────────────────────
    public function agents(array $filters = []): array { return M::agents($filters); }
    public function agent(string $id): ?array { return M::agent($id); }
    public function createAgent(array $payload): array { return $this->created($payload, 'AGT'); }
    public function fundAgent(string $id, string $direction, array $payload): array { return $this->ok(['direction' => $direction]); }
    public function approveAgentKyc(string $id, array $payload = []): array { return $this->ok(); }
    public function suspendAgent(string $id, string $reason = ''): array { return $this->ok(); }
    public function reactivateAgent(string $id): array { return $this->ok(); }
    public function requestAgentClosure(string $id, string $reason = ''): array { return $this->fakeApproval('CLOSE_AGENT', $id); }

    // ── Merchants ──────────────────────────────────────────────────────────
    public function merchants(array $filters = []): array { return M::merchants($filters); }
    public function merchant(string $id): ?array { return M::merchant($id); }
    public function createMerchant(array $payload): array { return $this->created($payload, 'MRC'); }
    public function setMerchantM2M(string $id, bool $enabled): array { return $this->ok(['canReceiveFromMerchant' => $enabled]); }
    public function approveMerchantKyc(string $id, array $payload = []): array { return $this->ok(); }
    public function suspendMerchant(string $id, string $reason = ''): array { return $this->ok(); }
    public function reactivateMerchant(string $id): array { return $this->ok(); }
    public function requestMerchantClosure(string $id, string $reason = ''): array { return $this->fakeApproval('CLOSE_MERCHANT', $id); }

    // ── Transactions ───────────────────────────────────────────────────────
    public function transactions(array $filters = []): array { return M::transactions($filters); }
    public function transaction(string $id): ?array { return M::transaction($id); }
    public function reverseTransaction(array $payload): array { return $this->fakeApproval('REVERSE_TRANSACTION', $payload['transactionId'] ?? null); }

    // ── Approvals ──────────────────────────────────────────────────────────
    public function approvals(array $filters = []): array { return M::approvals($filters); }
    public function approval(string $id): ?array { return M::approval($id); }
    public function approveRequest(string $id, array $payload = []): array { return $this->ok(['approvalId' => $id, 'status' => 'APPROVED']); }
    public function rejectRequest(string $id, array $payload): array { return $this->ok(['approvalId' => $id, 'status' => 'REJECTED']); }

    // ── Audit ──────────────────────────────────────────────────────────────
    public function auditEvents(array $filters = []): array { return M::auditEvents($filters); }

    // ── BO Users ───────────────────────────────────────────────────────────
    public function backofficeUsers(): array { return M::backofficeUsers(); }
    public function createBackofficeUser(array $payload): array { return $this->created($payload, 'BO'); }
    public function suspendBackofficeUser(string $id): array { return $this->ok(); }
    public function reactivateBackofficeUser(string $id): array { return $this->ok(); }

    // ── Dashboard ──────────────────────────────────────────────────────────
    public function dashboardStats(): array { return M::dashboardStats(); }

    // ── Wallets ────────────────────────────────────────────────────────────
    public function wallets(array $filters = []): array { return M::wallets($filters); }
    public function wallet(string $id): array { return M::wallet($id); }
    public function walletById(string $id): ?array { return M::walletById($id); }
    public function freezeWallet(string $id, string $reason = ''): array { return $this->ok(); }
    public function unfreezeWallet(string $id): array { return $this->ok(); }

    // ── Rules & Limits ─────────────────────────────────────────────────────
    public function limitProfiles(): array { return M::limitProfiles(); }
    public function limitProfile(string $id): ?array { return M::limitProfile($id); }
    public function feeRules(array $filters = []): array { return M::feeRules($filters); }
    public function feeRule(string $id): ?array { return M::feeRule($id); }
    public function commissionRules(array $filters = []): array { return M::commissionRules($filters); }
    public function commissionRule(string $id): ?array { return M::commissionRule($id); }
    public function controlThresholds(array $filters = []): array { return M::controlThresholds($filters); }
    public function controlThreshold(string $id): ?array { return M::controlThreshold($id); }
    public function activateRule(string $kind, string $id): array { return $this->fakeApproval(strtoupper($kind) . '_ACTIVATE', $id); }
    public function deactivateRule(string $kind, string $id): array { return $this->fakeApproval(strtoupper($kind) . '_DEACTIVATE', $id); }

    // ── Treasury ───────────────────────────────────────────────────────────
    public function commissionSettlementRuns(array $filters = []): array { return M::commissionSettlementRuns($filters); }
    public function commissionSettlementRun(string $id): ?array { return M::commissionSettlementRun($id); }
    public function commissionPendingSummary(): array { return M::commissionPendingSummary(); }
    public function billProviderSettlementBalances(): array { return M::billProviderSettlementBalances(); }
    public function platformRevenueBalances(): array { return M::platformRevenueBalances(); }
    public function triggerCommissionSettlement(array $payload = []): array { return $this->ok(['runId' => 'csr-' . Str::random(6)]); }
    public function requestBillProviderSettlement(array $payload): array { return $this->fakeApproval('BILL_SETTLEMENT', null); }
    public function requestPlatformRevenueWithdrawal(array $payload): array { return $this->fakeApproval('PLATFORM_WITHDRAWAL', null); }

    // ── Cards ──────────────────────────────────────────────────────────────
    public function cards(array $filters = []): array { return M::cards($filters); }
    public function card(string $id): ?array { return M::card($id); }
    public function cardStock(array $filters = []): array { return M::cardStock($filters); }
    public function cardStockItem(string $id): ?array { return M::cardStockItem($id); }
    public function blockCard(string $id, string $reason = ''): array { return $this->ok(); }
    public function unblockCard(string $id): array { return $this->ok(); }
    public function reportCardLost(string $id, string $reason = ''): array { return $this->ok(); }
    public function reportCardStolen(string $id, string $reason = ''): array { return $this->ok(); }
    public function closeCard(string $id, string $reason = ''): array { return $this->ok(); }
    public function importCardStock(array $payload): array { return $this->ok(['imported' => count($payload['items'] ?? [])]); }
    public function assignCardStock(array $payload): array { return $this->ok(); }

    // ── Terminals ──────────────────────────────────────────────────────────
    public function terminals(array $filters = []): array { return M::terminals($filters); }
    public function terminal(string $id): ?array { return M::terminal($id); }
    public function createTerminal(array $payload): array { return $this->created($payload, 'TRM'); }
    public function provisionTerminal(string $id, array $payload = []): array { return $this->ok(); }
    public function suspendTerminal(string $id, string $reason = ''): array { return $this->ok(); }
    public function reactivateTerminal(string $id): array { return $this->ok(); }

    // ── Service Providers ──────────────────────────────────────────────────
    public function serviceProviders(array $filters = []): array { return M::serviceProviders($filters); }
    public function serviceProvider(string $id): ?array { return M::serviceProvider($id); }
    public function createServiceProvider(array $payload): array { return $this->created($payload, 'SP'); }
    public function updateServiceProvider(string $id, array $payload): array { return $this->ok($payload + ['id' => $id]); }
    public function activateServiceProvider(string $id): array { return $this->ok(); }
    public function deactivateServiceProvider(string $id): array { return $this->ok(); }
    public function billServices(string $providerId = '', array $filters = []): array { return M::billServices($providerId, $filters); }
    public function billService(string $providerId, string $id): ?array { return M::billService($providerId, $id); }
    public function createBillService(string $providerId, array $payload): array { return $this->created($payload + ['providerId' => $providerId], 'BS'); }
    public function updateBillService(string $providerId, string $serviceId, array $payload): array { return $this->ok($payload + ['id' => $serviceId]); }
    public function activateBillService(string $providerId, string $serviceId): array { return $this->ok(); }
    public function deactivateBillService(string $providerId, string $serviceId): array { return $this->ok(); }

    // ── Reconciliation ─────────────────────────────────────────────────────
    public function reconciliationIncidents(array $filters = []): array { return M::reconciliationIncidents($filters); }
    public function reconciliationIncident(string $id): ?array { return M::reconciliationIncident($id); }
    public function reconciliationRuns(array $filters = []): array { return M::reconciliationRuns($filters); }
    public function reconciliationRun(string $id): ?array { return M::reconciliationRun($id); }
    public function investigateIncident(string $id, array $payload = []): array { return $this->ok(['status' => 'INVESTIGATING']); }
    public function resolveIncident(string $id, array $payload = []): array { return $this->ok(['status' => 'RESOLVED']); }
    public function closeIncident(string $id, array $payload = []): array { return $this->ok(['status' => 'CLOSED']); }

    // ── Reports ────────────────────────────────────────────────────────────
    public function transactionSummaryReport(array $filters = []): array { return M::transactionSummaryReport($filters); }
    public function kycSummaryReport(): array { return M::kycSummaryReport(); }
    public function amlLargeTransactions(array $filters = []): array { return M::amlLargeTransactions($filters); }
    public function floatReport(): array { return M::floatReport(); }
    public function actorSummaryReport(): array { return M::actorSummaryReport(); }
    public function reportExports(array $filters = []): array { return M::reportExports($filters); }
    public function reportExport(string $id): ?array { return M::reportExport($id); }
    public function requestReportExport(array $payload): array
    {
        return [
            'id' => 'exp-' . Str::random(6),
            'status' => 'QUEUED',
            'requestedAt' => now()->toIso8601String(),
        ] + $payload;
    }
    public function downloadReport(string $path, array $query = []): array
    {
        return ['url' => '/mock/exports/' . trim($path, '/') . '.csv', 'expiresAt' => now()->addHour()->toIso8601String()];
    }

    // ── helpers ────────────────────────────────────────────────────────────
    private function ok(array $extra = []): array
    {
        return ['ok' => true] + $extra;
    }

    private function created(array $payload, string $prefix): array
    {
        return [
            'id' => strtolower($prefix) . '-' . Str::random(4),
            'externalRef' => $prefix . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'createdAt' => now()->toIso8601String(),
        ] + $payload;
    }

    private function fakeApproval(string $type, ?string $resourceId): array
    {
        return [
            'approvalId' => 'apr-' . Str::random(6),
            'type' => $type,
            'resourceId' => $resourceId,
            'status' => 'PENDING',
            'createdAt' => now()->toIso8601String(),
        ];
    }
}
