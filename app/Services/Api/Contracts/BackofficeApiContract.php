<?php

namespace App\Services\Api\Contracts;

/**
 * BackofficeApiContract
 *
 * Single source of truth for everything the BO UI needs from the
 * upstream KomoPay Backoffice API. Two implementations exist:
 *
 *   - MockBackofficeApi  → in-memory fixtures (MockDataService)
 *   - HttpBackofficeApi  → real HTTP API (config/komopay.php)
 *
 * Selection happens in AppServiceProvider via KOMOPAY_USE_MOCK_API.
 *
 * All shapes (keys, casing, enums) match the Backoffice API spec
 * (BO_Frontend_Specification.md §5–§7). UI components MUST consume
 * this contract — they must NEVER touch MockDataService directly.
 */
interface BackofficeApiContract
{
    // ── Customers ──────────────────────────────────────────────────────────
    public function customers(array $filters = []): array;
    public function customersPage(array $filters = []): array;
    public function customer(string $id): ?array;
    public function suspendCustomer(string $id, string $reason = ''): array;
    public function reactivateCustomer(string $id): array;
    public function requestCustomerClosure(string $id, string $reason = ''): array;
    public function resetCustomerAuthPin(string $id): array;
    public function assignCustomerLimitProfile(string $id, string $limitProfileId): array;

    // ── Customer KYC Review (spec §5.3a) ───────────────────────────────────
    public function customerKycDocuments(string $customerId): array;
    public function kycDocument(string $documentId): ?array;
    public function downloadKycDocumentFile(string $documentId): array; // ['contentType' => string, 'filename' => string, 'body' => string]
    public function approveKycDocument(string $documentId): array;
    public function rejectKycDocument(string $documentId, string $reason): array;
    public function changeCustomerKycLevel(string $customerId, string $kycLevel, ?string $nextReviewDate = null): array;
    public function activateCustomer(string $customerId): array;

    // ── Agent & Merchant KYC/KYB Review (spec §5.3b) ───────────────────────
    // $ownerType is 'agents' or 'merchants'. Documents are uploaded by the BO itself.
    public function uploadActorKycDocument(string $ownerType, string $actorId, string $documentType, \Illuminate\Http\UploadedFile $file): array;
    public function actorKycDocuments(string $ownerType, string $actorId): array;
    public function actorKycDocument(string $ownerType, string $documentId): ?array;
    public function downloadActorKycDocumentFile(string $ownerType, string $documentId): array; // ['contentType' => string, 'filename' => string, 'body' => string]
    public function approveActorKycDocument(string $ownerType, string $documentId): array;
    public function rejectActorKycDocument(string $ownerType, string $documentId, string $reason): array;
    public function changeActorKycLevel(string $ownerType, string $actorId, string $kycLevel): array;
    public function activateActor(string $ownerType, string $actorId): array;

    // ── Agents ─────────────────────────────────────────────────────────────
    public function agents(array $filters = []): array;
    public function agentsPage(array $filters = []): array;
    public function agent(string $id): ?array;
    public function createAgent(array $payload): array;
    public function fundAgent(string $id, string $direction, array $payload): array; // direction = fund-in|fund-out
    public function suspendAgent(string $id, string $reason = ''): array;
    public function reactivateAgent(string $id): array;
    public function requestAgentClosure(string $id, string $reason = ''): array;
    public function resetAgentAuthPin(string $id): array;
    public function assignAgentLimitProfile(string $id, string $limitProfileId): array;

    // ── Merchants ──────────────────────────────────────────────────────────
    public function merchants(array $filters = []): array;
    public function merchantsPage(array $filters = []): array;
    public function merchant(string $id): ?array;
    public function createMerchant(array $payload): array;
    public function setMerchantM2M(string $id, bool $enabled): array;
    public function suspendMerchant(string $id, string $reason = ''): array;
    public function reactivateMerchant(string $id): array;
    public function requestMerchantClosure(string $id, string $reason = ''): array;
    public function resetMerchantAuthPin(string $id): array;
    public function assignMerchantLimitProfile(string $id, string $limitProfileId): array;

    // ── Transactions ───────────────────────────────────────────────────────
    public function transactions(array $filters = []): array;
    public function transactionsPage(array $filters = []): array;
    public function transaction(string $id): ?array;
    public function reverseTransaction(array $payload): array;

    // ── Approvals ──────────────────────────────────────────────────────────
    public function approvals(array $filters = []): array;
    public function approvalsPage(array $filters = []): array;
    public function approval(string $id): ?array;
    public function approveRequest(string $id, array $payload = []): array;
    public function rejectRequest(string $id, array $payload): array;

    // ── Audit ──────────────────────────────────────────────────────────────
    public function auditEvents(array $filters = []): array;
    public function auditEventsPage(array $filters = []): array;

    // ── BO Users ───────────────────────────────────────────────────────────
    public function backofficeUsers(array $filters = []): array;
    public function backofficeUsersPage(array $filters = []): array;
    public function createBackofficeUser(array $payload): array;
    public function suspendBackofficeUser(string $id): array;
    public function reactivateBackofficeUser(string $id): array;
    public function closeBackofficeUser(string $id): array;
    public function elevateBackofficeUserRole(string $id, array $payload): array;

    // ── Dashboard ──────────────────────────────────────────────────────────
    public function dashboardStats(): array;

    // ── Wallets ────────────────────────────────────────────────────────────
    public function wallets(array $filters = []): array;
    public function wallet(string $id): array;
    public function walletById(string $id): ?array;
    public function freezeWallet(string $id, string $reason = ''): array;
    public function unfreezeWallet(string $id): array;

    // ── Rules & Limits ─────────────────────────────────────────────────────
    public function limitProfiles(): array;
    public function limitProfilesPage(array $filters = []): array;
    public function limitProfile(string $id): ?array;
    public function feeRules(array $filters = []): array;
    public function feeRulesPage(array $filters = []): array;
    public function feeRule(string $id): ?array;
    public function createFeeRule(array $payload): array;
    public function commissionRules(array $filters = []): array;
    public function commissionRulesPage(array $filters = []): array;
    public function commissionRule(string $id): ?array;
    public function createCommissionRule(array $payload): array;
    public function createLimitProfile(array $payload): array;
    public function controlThresholds(array $filters = []): array;
    public function controlThresholdsPage(array $filters = []): array;
    public function controlThreshold(string $id): ?array;
    public function createControlThreshold(array $payload): array;
    public function activateRule(string $kind, string $id): array;   // kind: fee|commission|limit|threshold
    public function deactivateRule(string $kind, string $id): array;

    // Versioned modification — never destructive. Each produces a 4-eyes approval.
    // Spec §11.5: POST {entity}/{id}/supersede returns 202 ApiResponse<ApprovalRequestResponse>.
    public function supersedeFeeRule(string $id, array $payload): array;
    public function supersedeCommissionRule(string $id, array $payload): array;
    public function supersedeLimitProfile(string $id, array $payload): array;
    public function supersedeControlThreshold(string $id, array $payload): array;

    // ── Treasury ───────────────────────────────────────────────────────────
    public function commissionSettlementRuns(array $filters = []): array;
    public function commissionSettlementRunsPage(array $filters = []): array;
    public function commissionSettlementRun(string $id): ?array;
    public function commissionPendingSummary(): array;
    public function billProviderSettlementBalances(): array;
    public function platformRevenueBalances(): array;
    public function platformLiquidityBalances(): array;
    public function triggerCommissionSettlement(array $payload = []): array;
    public function requestBillProviderSettlement(array $payload): array;
    public function requestPlatformRevenueWithdrawal(array $payload): array;
    public function requestPlatformLiquidityTopUp(array $payload): array;

    // ── Cards ──────────────────────────────────────────────────────────────
    public function cards(array $filters = []): array;
    public function cardsPage(array $filters = []): array;
    public function card(string $id): ?array;
    public function cardStock(array $filters = []): array;
    public function cardStockPage(array $filters = []): array;
    public function cardStockItem(string $id): ?array;
    public function blockCard(string $id, string $reason = ''): array;
    public function unblockCard(string $id): array;
    public function reportCardLost(string $id, string $reason = ''): array;
    public function reportCardStolen(string $id, string $reason = ''): array;
    public function closeCard(string $id, string $reason = ''): array;
    public function importCardStock(array $payload): array;
    public function assignCardStock(array $payload): array;

    // ── Terminals ──────────────────────────────────────────────────────────
    public function terminals(array $filters = []): array;
    public function terminalsPage(array $filters = []): array;
    public function terminal(string $id): ?array;
    public function createTerminal(array $payload): array;
    public function provisionTerminal(string $id, array $payload = []): array;
    public function suspendTerminal(string $id, string $reason = ''): array;
    public function reactivateTerminal(string $id): array;

    // ── Service Providers ──────────────────────────────────────────────────
    public function serviceProviders(array $filters = []): array;
    public function serviceProvider(string $id): ?array;
    public function createServiceProvider(array $payload): array;
    public function updateServiceProvider(string $id, array $payload): array;
    public function activateServiceProvider(string $id): array;
    public function deactivateServiceProvider(string $id): array;
    public function billServices(string $providerId = '', array $filters = []): array;
    public function billService(string $providerId, string $id): ?array;
    public function createBillService(string $providerId, array $payload): array;
    public function updateBillService(string $providerId, string $serviceId, array $payload): array;
    public function activateBillService(string $providerId, string $serviceId): array;
    public function deactivateBillService(string $providerId, string $serviceId): array;

    // ── Reconciliation ─────────────────────────────────────────────────────
    public function reconciliationIncidents(array $filters = []): array;
    public function reconciliationIncidentsPage(array $filters = []): array;
    public function reconciliationIncident(string $id): ?array;
    public function reconciliationRuns(array $filters = []): array;
    public function reconciliationRunsPage(array $filters = []): array;
    public function reconciliationRun(string $id): ?array;
    public function investigateIncident(string $id, array $payload = []): array;
    public function resolveIncident(string $id, array $payload = []): array;
    public function closeIncident(string $id, array $payload = []): array;

    // ── Reports ────────────────────────────────────────────────────────────
    public function transactionSummaryReport(array $filters = []): array;
    public function kycSummaryReport(): array;
    public function amlLargeTransactions(array $filters = []): array;
    public function amlLargeTransactionsPage(array $filters = []): array;
    public function floatReport(): array;
    public function actorSummaryReport(): array;
    public function reportExports(array $filters = []): array;
    public function reportExportsPage(array $filters = []): array;
    public function reportExport(string $id): ?array;
    public function requestReportExport(array $payload): array;
    public function downloadReport(string $path, array $query = []): array;
}
