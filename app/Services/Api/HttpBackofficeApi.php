<?php

namespace App\Services\Api;

use App\Services\Api\Contracts\BackofficeApiContract;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * HttpBackofficeApi
 *
 * Real implementation calling the upstream KomoPay Backoffice API
 * via Laravel's Http client. Endpoints are derived from the API spec
 * (BO_Frontend_Specification.md §5–§7) and from the inline `// Real:`
 * hints that were left in the Livewire components during the mock phase.
 *
 * All methods return decoded JSON arrays. List endpoints return the raw
 * "items" or "data" array (whichever the upstream uses); detail endpoints
 * return null on 404 to keep parity with MockBackofficeApi.
 */
class HttpBackofficeApi implements BackofficeApiContract
{
    private function client(): PendingRequest
    {
        $client = Http::baseUrl(rtrim((string) config('komopay.base_url'), '/') . config('komopay.prefix'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('komopay.timeout', 15));

        if ($token = config('komopay.token')) {
            $client = $client->withToken($token);
        }
        return $client;
    }

    /** GET endpoint returning a list (unwraps `data` / `items` if present). */
    private function getList(string $path, array $query = []): array
    {
        $body = $this->client()->get($path, $query)->throw()->json();
        if (is_array($body) && isset($body['data']) && is_array($body['data'])) return $body['data'];
        if (is_array($body) && isset($body['items']) && is_array($body['items'])) return $body['items'];
        return is_array($body) ? $body : [];
    }

    /** GET endpoint returning a single resource (null on 404). */
    private function getOne(string $path): ?array
    {
        $res = $this->client()->get($path);
        if ($res->status() === 404) return null;
        return $res->throw()->json() ?? null;
    }

    private function post(string $path, array $payload = []): array
    {
        return (array) $this->client()->post($path, $payload)->throw()->json();
    }

    private function put(string $path, array $payload = []): array
    {
        return (array) $this->client()->put($path, $payload)->throw()->json();
    }

    // ── Customers ──────────────────────────────────────────────────────────
    public function customers(array $filters = []): array { return $this->getList('/customers', $filters); }
    public function customer(string $id): ?array { return $this->getOne("/customers/$id"); }
    public function suspendCustomer(string $id, string $reason = ''): array { return $this->post("/customers/$id/suspend", ['reason' => $reason]); }
    public function reactivateCustomer(string $id): array { return $this->post("/customers/$id/reactivate"); }
    public function requestCustomerClosure(string $id, string $reason = ''): array { return $this->post("/customers/$id/close-request", ['reason' => $reason]); }

    // ── Agents ─────────────────────────────────────────────────────────────
    public function agents(array $filters = []): array { return $this->getList('/agents', $filters); }
    public function agent(string $id): ?array { return $this->getOne("/agents/$id"); }
    public function createAgent(array $payload): array { return $this->post('/agents', $payload); }
    public function fundAgent(string $id, string $direction, array $payload): array { return $this->post("/agents/$id/$direction", $payload); }
    public function approveAgentKyc(string $id, array $payload = []): array { return $this->post("/agents/$id/approve-kyc", $payload); }
    public function suspendAgent(string $id, string $reason = ''): array { return $this->post("/agents/$id/suspend", ['reason' => $reason]); }
    public function reactivateAgent(string $id): array { return $this->post("/agents/$id/reactivate"); }
    public function requestAgentClosure(string $id, string $reason = ''): array { return $this->post("/agents/$id/close-request", ['reason' => $reason]); }

    // ── Merchants ──────────────────────────────────────────────────────────
    public function merchants(array $filters = []): array { return $this->getList('/merchants', $filters); }
    public function merchant(string $id): ?array { return $this->getOne("/merchants/$id"); }
    public function createMerchant(array $payload): array { return $this->post('/merchants', $payload); }
    public function setMerchantM2M(string $id, bool $enabled): array { return $this->post("/merchants/$id/m2m/" . ($enabled ? 'enable' : 'disable')); }
    public function approveMerchantKyc(string $id, array $payload = []): array { return $this->post("/merchants/$id/approve-kyc", $payload); }
    public function suspendMerchant(string $id, string $reason = ''): array { return $this->post("/merchants/$id/suspend", ['reason' => $reason]); }
    public function reactivateMerchant(string $id): array { return $this->post("/merchants/$id/reactivate"); }
    public function requestMerchantClosure(string $id, string $reason = ''): array { return $this->post("/merchants/$id/close-request", ['reason' => $reason]); }

    // ── Transactions ───────────────────────────────────────────────────────
    public function transactions(array $filters = []): array { return $this->getList('/transactions', $filters); }
    public function transaction(string $id): ?array { return $this->getOne("/transactions/$id"); }
    public function reverseTransaction(array $payload): array { return $this->post('/transactions/reversals', $payload); }

    // ── Approvals ──────────────────────────────────────────────────────────
    public function approvals(array $filters = []): array { return $this->getList('/approvals', $filters); }
    public function approval(string $id): ?array { return $this->getOne("/approvals/$id"); }
    public function approveRequest(string $id, array $payload = []): array { return $this->post("/approvals/$id/approve", $payload); }
    public function rejectRequest(string $id, array $payload): array { return $this->post("/approvals/$id/reject", $payload); }

    // ── Audit ──────────────────────────────────────────────────────────────
    public function auditEvents(array $filters = []): array { return $this->getList('/audit-events', $filters); }

    // ── BO Users ───────────────────────────────────────────────────────────
    public function backofficeUsers(): array { return $this->getList('/users'); }
    public function createBackofficeUser(array $payload): array { return $this->post('/users', $payload); }
    public function suspendBackofficeUser(string $id): array { return $this->post("/users/$id/suspend"); }
    public function reactivateBackofficeUser(string $id): array { return $this->post("/users/$id/reactivate"); }

    // ── Dashboard ──────────────────────────────────────────────────────────
    public function dashboardStats(): array { return (array) $this->client()->get('/dashboard/stats')->throw()->json(); }

    // ── Wallets ────────────────────────────────────────────────────────────
    public function wallets(array $filters = []): array { return $this->getList('/wallets', $filters); }
    public function wallet(string $id): array { return (array) ($this->getOne("/wallets/$id") ?? []); }
    public function walletById(string $id): ?array { return $this->getOne("/wallets/$id"); }
    public function freezeWallet(string $id, string $reason = ''): array { return $this->post("/wallets/$id/freeze", ['reason' => $reason]); }
    public function unfreezeWallet(string $id): array { return $this->post("/wallets/$id/unfreeze"); }

    // ── Rules & Limits ─────────────────────────────────────────────────────
    public function limitProfiles(): array { return $this->getList('/limit-profiles'); }
    public function limitProfile(string $id): ?array { return $this->getOne("/limit-profiles/$id"); }
    public function feeRules(array $filters = []): array { return $this->getList('/fee-rules', $filters); }
    public function feeRule(string $id): ?array { return $this->getOne("/fee-rules/$id"); }
    public function commissionRules(array $filters = []): array { return $this->getList('/commission-rules', $filters); }
    public function commissionRule(string $id): ?array { return $this->getOne("/commission-rules/$id"); }
    public function controlThresholds(array $filters = []): array { return $this->getList('/control-thresholds', $filters); }
    public function controlThreshold(string $id): ?array { return $this->getOne("/control-thresholds/$id"); }
    public function activateRule(string $kind, string $id): array { return $this->post("/" . $this->ruleKindPath($kind) . "/$id/activate"); }
    public function deactivateRule(string $kind, string $id): array { return $this->post("/" . $this->ruleKindPath($kind) . "/$id/deactivate"); }

    private function ruleKindPath(string $kind): string
    {
        return match (strtolower($kind)) {
            'fee' => 'fee-rules',
            'commission' => 'commission-rules',
            'limit' => 'limit-profiles',
            'threshold' => 'control-thresholds',
            default => $kind,
        };
    }

    // ── Treasury ───────────────────────────────────────────────────────────
    public function commissionSettlementRuns(array $filters = []): array { return $this->getList('/commission-settlements', $filters); }
    public function commissionSettlementRun(string $id): ?array { return $this->getOne("/commission-settlements/$id"); }
    public function commissionPendingSummary(): array { return (array) $this->client()->get('/commission-settlements/pending-summary')->throw()->json(); }
    public function billProviderSettlementBalances(): array { return $this->getList('/bill-provider-settlement/balances'); }
    public function platformRevenueBalances(): array { return $this->getList('/platform-revenue/balances'); }
    public function triggerCommissionSettlement(array $payload = []): array { return $this->post('/commission-settlements/trigger', $payload); }
    public function requestBillProviderSettlement(array $payload): array { return $this->post('/bill-provider-settlement/requests', $payload); }
    public function requestPlatformRevenueWithdrawal(array $payload): array { return $this->post('/platform-revenue/withdrawal-requests', $payload); }

    // ── Cards ──────────────────────────────────────────────────────────────
    public function cards(array $filters = []): array { return $this->getList('/cards', $filters); }
    public function card(string $id): ?array { return $this->getOne("/cards/$id"); }
    public function cardStock(array $filters = []): array { return $this->getList('/card-stock', $filters); }
    public function cardStockItem(string $id): ?array { return $this->getOne("/card-stock/$id"); }
    public function blockCard(string $id, string $reason = ''): array { return $this->post("/cards/$id/block", ['reason' => $reason]); }
    public function unblockCard(string $id): array { return $this->post("/cards/$id/unblock"); }
    public function reportCardLost(string $id, string $reason = ''): array { return $this->post("/cards/$id/report-lost", ['reason' => $reason]); }
    public function reportCardStolen(string $id, string $reason = ''): array { return $this->post("/cards/$id/report-stolen", ['reason' => $reason]); }
    public function closeCard(string $id, string $reason = ''): array { return $this->post("/cards/$id/close", ['reason' => $reason]); }
    public function importCardStock(array $payload): array { return $this->post('/card-stock/import', $payload); }
    public function assignCardStock(array $payload): array { return $this->post('/card-stock/assign', $payload); }

    // ── Terminals ──────────────────────────────────────────────────────────
    public function terminals(array $filters = []): array { return $this->getList('/terminals', $filters); }
    public function terminal(string $id): ?array { return $this->getOne("/terminals/$id"); }
    public function createTerminal(array $payload): array { return $this->post('/terminals', $payload); }
    public function provisionTerminal(string $id, array $payload = []): array { return $this->post("/terminals/$id/provision", $payload); }
    public function suspendTerminal(string $id, string $reason = ''): array { return $this->post("/terminals/$id/suspend", ['reason' => $reason]); }
    public function reactivateTerminal(string $id): array { return $this->post("/terminals/$id/reactivate"); }

    // ── Service Providers ──────────────────────────────────────────────────
    public function serviceProviders(array $filters = []): array { return $this->getList('/service-providers', $filters); }
    public function serviceProvider(string $id): ?array { return $this->getOne("/service-providers/$id"); }
    public function createServiceProvider(array $payload): array { return $this->post('/service-providers', $payload); }
    public function updateServiceProvider(string $id, array $payload): array { return $this->put("/service-providers/$id", $payload); }
    public function activateServiceProvider(string $id): array { return $this->post("/service-providers/$id/activate"); }
    public function deactivateServiceProvider(string $id): array { return $this->post("/service-providers/$id/deactivate"); }
    public function billServices(string $providerId = '', array $filters = []): array
    {
        $path = $providerId !== '' ? "/service-providers/$providerId/services" : '/bill-services';
        return $this->getList($path, $filters);
    }
    public function billService(string $providerId, string $id): ?array { return $this->getOne("/service-providers/$providerId/services/$id"); }
    public function createBillService(string $providerId, array $payload): array { return $this->post("/service-providers/$providerId/services", $payload); }
    public function updateBillService(string $providerId, string $serviceId, array $payload): array { return $this->put("/service-providers/$providerId/services/$serviceId", $payload); }
    public function activateBillService(string $providerId, string $serviceId): array { return $this->post("/service-providers/$providerId/services/$serviceId/activate"); }
    public function deactivateBillService(string $providerId, string $serviceId): array { return $this->post("/service-providers/$providerId/services/$serviceId/deactivate"); }

    // ── Reconciliation ─────────────────────────────────────────────────────
    public function reconciliationIncidents(array $filters = []): array { return $this->getList('/reconciliation/incidents', $filters); }
    public function reconciliationIncident(string $id): ?array { return $this->getOne("/reconciliation/incidents/$id"); }
    public function reconciliationRuns(array $filters = []): array { return $this->getList('/reconciliation/runs', $filters); }
    public function reconciliationRun(string $id): ?array { return $this->getOne("/reconciliation/runs/$id"); }
    public function investigateIncident(string $id, array $payload = []): array { return $this->post("/reconciliation/incidents/$id/investigate", $payload); }
    public function resolveIncident(string $id, array $payload = []): array { return $this->post("/reconciliation/incidents/$id/resolve", $payload); }
    public function closeIncident(string $id, array $payload = []): array { return $this->post("/reconciliation/incidents/$id/close", $payload); }

    // ── Reports ────────────────────────────────────────────────────────────
    public function transactionSummaryReport(array $filters = []): array { return (array) $this->client()->get('/reports/transaction-summary', $filters)->throw()->json(); }
    public function kycSummaryReport(): array { return (array) $this->client()->get('/reports/kyc-summary')->throw()->json(); }
    public function amlLargeTransactions(array $filters = []): array { return $this->getList('/reports/aml/large-transactions', $filters); }
    public function floatReport(): array { return (array) $this->client()->get('/reports/float')->throw()->json(); }
    public function actorSummaryReport(): array { return (array) $this->client()->get('/reports/actor-summary')->throw()->json(); }
    public function reportExports(array $filters = []): array { return $this->getList('/reports/exports', $filters); }
    public function reportExport(string $id): ?array { return $this->getOne("/reports/exports/$id"); }
    public function requestReportExport(array $payload): array { return $this->post('/reports/exports', $payload); }
    public function downloadReport(string $path, array $query = []): array
    {
        return (array) $this->client()->get('/reports/' . ltrim($path, '/'), $query + ['format' => 'csv'])->throw()->json();
    }
}
