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
    private array $createdReportExports = [];

    private function page(array $rows, array $filters = []): array
    {
        $limit = $filters['limit'] ?? 20;
        $limit = is_numeric($limit) ? max(1, min(100, (int) $limit)) : 20;
        $offset = $filters['cursor'] ?? 0;
        $offset = is_numeric($offset) ? max(0, (int) $offset) : 0;
        $nextOffset = $offset + $limit;
        $data = array_slice(array_values($rows), $offset, $limit);

        return [
            'data' => $data,
            'pagination' => [
                'nextCursor' => $nextOffset < count($rows) ? (string) $nextOffset : null,
                'hasMore' => $nextOffset < count($rows),
                'limit' => $limit,
            ],
        ];
    }

    // ── Customers ──────────────────────────────────────────────────────────
    public function customers(array $filters = []): array { return $this->customersPage($filters)['data']; }
    public function customersPage(array $filters = []): array { return $this->page(M::customers($filters), $filters); }
    public function customer(string $id): ?array { return M::customer($id); }
    public function suspendCustomer(string $id, string $reason = ''): array { return $this->ok(); }
    public function reactivateCustomer(string $id): array { return $this->ok(); }
    public function requestCustomerClosure(string $id, string $reason = ''): array { return $this->fakeApproval('CLOSE_CUSTOMER', $id); }
    public function resetCustomerAuthPin(string $id): array { return M::customer($id) ?? $this->ok(['id' => $id]); }
    public function assignCustomerLimitProfile(string $id, string $limitProfileId): array { return $this->fakeApproval('LIMIT_PROFILE_CHANGE', $id) + ['limitProfileId' => $limitProfileId]; }

    // ── Customer KYC Review (spec §5.3a) ───────────────────────────────────
    public function customerKycDocuments(string $customerId): array { return M::customerKycDocuments($customerId); }
    public function kycDocument(string $documentId): ?array { return M::kycDocument($documentId); }
    public function downloadKycDocumentFile(string $documentId): array
    {
        $doc = M::kycDocument($documentId);
        $type = strtolower((string) ($doc['documentType'] ?? 'document'));
        $contentType = strtolower(trim((string) ($doc['contentType'] ?? 'application/octet-stream')));
        $extension = match ($contentType) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'bin',
        };

        return [
            'contentType' => $contentType,
            'filename' => "kyc-$type-$documentId.$extension",
            'body' => $this->mockKycDocumentBody($contentType, $documentId, $type),
        ];
    }

    private function mockKycDocumentBody(string $contentType, string $documentId, string $type): string
    {
        if ($contentType === 'application/pdf') {
            return $this->mockKycPdfBody($documentId, $type);
        }

        if ($contentType === 'image/jpeg') {
            return base64_decode(
                '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/ASP/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/ASP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Al//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/Iqf/2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z',
                true,
            ) ?: '';
        }

        if ($contentType === 'image/png') {
            return base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAPAAAADwCAIAAACxN37FAAACSUlEQVR42u3dMQ2AMBCG0SrAQmcU1BAS6gIdiMNBQxhQwECY+uclT0Hzjde7Mq4bYhRPgKBB0CBoEDSCBkGDoEHQIGgEDYIGQYOgQdAIGgQNggZBg6ARNAgaBA2CBkEjaBA0TB10XRv8IWgELWgELWgEDYJG0IJG0IJG0IJG0CBoBC1oBC1oBC1oBA2CRtCCRtCCRtDgTyEIGgSNoEHQIGgQNAgaQYOgQdAgaBA0ggZBg6BB0CBoBA2CBkGDoEHQCBoEDYIGQYOgEfQXW9/hjaARtKARtKARtKARNIIWNIIWNIIWNIIWNLFBg1kOEDQIGkGDoEHQIGgQNIIGQYOgQdAIGgQNggZBg6ARNAgaBA2CBkEjaBA0hAdtORBWgSFoQSNoQSNoQSNoEDSCFjSCFjSCFjROUoBZDhA0ggZBg6BB0CBoBA2CBkGDoEHQCBoEDYIGQYOgETQIGgQNggZBI2gQNMQFbTkQVoEhaEEjaEEjaEEjaBA0ghY0ghY0ghY0TlKAWQ4QNIIGQYOgQdAgaAQNggZBg6BB0AgaBA2CBkGDoBE0CBpSg16OM4BEBC1oBC1oBC1oBC1oQQsaQQsaQQsaQQsaQQta0IJG0IJG0IJG0IJG0IIWtKARtKARtKARtKAFLWhBC1rQCFrQCFrQCFrQgha0oAUtaAQtaAQtaAQtaEELWtCCFjThQYOgQdAgaAQNggZBg6BB0AgaBA2CBkEjaBA0CBoEDYJG0CBoEDQIGgSNoEHQIGgQNAgaQYOgQdAgaBA0goaJPGaVAi40KUvCAAAAAElFTkSuQmCC',
                true,
            ) ?: '';
        }

        return "MOCK KYC DOCUMENT\nid: $documentId\ntype: $type\n";
    }

    private function mockKycPdfBody(string $documentId, string $type): string
    {
        $line1 = $this->pdfText('Mock KYC document');
        $line2 = $this->pdfText("ID: $documentId");
        $line3 = $this->pdfText("Type: $type");
        $stream = "BT\n/F1 18 Tf\n72 720 Td\n($line1) Tj\n0 -28 Td\n($line2) Tj\n0 -28 Td\n($line3) Tj\nET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            "<< /Length " . strlen($stream) . " >>\nstream\n$stream" . "endstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $objectNumber = $index + 1;
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "$objectNumber 0 obj\n$object\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        return $pdf
            . "trailer\n<< /Root 1 0 R /Size " . (count($objects) + 1) . " >>\n"
            . "startxref\n$xrefOffset\n%%EOF\n";
    }

    private function pdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    public function approveKycDocument(string $documentId): array
    {
        $updated = M::recordKycDocumentDecision($documentId, 'ACCEPTED');

        return $updated ?? $this->ok(['id' => $documentId, 'status' => 'ACCEPTED']);
    }
    public function rejectKycDocument(string $documentId, string $reason): array
    {
        $updated = M::recordKycDocumentDecision($documentId, 'REJECTED', trim($reason));

        return $updated ?? $this->ok(['id' => $documentId, 'status' => 'REJECTED', 'rejectionReason' => trim($reason)]);
    }
    public function changeCustomerKycLevel(string $customerId, string $kycLevel, ?string $nextReviewDate = null): array
    {
        $customer = M::customer($customerId) ?? ['id' => $customerId];

        return array_replace($customer, [
            'kycLevel' => strtoupper(trim($kycLevel)),
            'kycNextReviewDate' => $nextReviewDate !== null && trim($nextReviewDate) !== '' ? trim($nextReviewDate) : null,
        ]);
    }
    public function activateCustomer(string $customerId): array
    {
        $customer = M::customer($customerId) ?? ['id' => $customerId];

        return array_replace($customer, ['status' => 'ACTIVE']);
    }

    // ── Agent & Merchant KYC/KYB Review (spec §5.3b) ───────────────────────
    private function actorOwnerActorType(string $ownerType): string
    {
        return strtolower(trim($ownerType)) === 'merchants' ? 'MERCHANT' : 'AGENT';
    }

    public function uploadActorKycDocument(string $ownerType, string $actorId, string $documentType, \Illuminate\Http\UploadedFile $file): array
    {
        $contentType = match (strtolower((string) $file->getClientOriginalExtension())) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => $file->getMimeType() ?: 'application/octet-stream',
        };

        return M::recordActorKycDocumentUpload(
            $this->actorOwnerActorType($ownerType),
            $actorId,
            $documentType,
            $contentType,
        );
    }

    public function actorKycDocuments(string $ownerType, string $actorId): array
    {
        return M::actorKycDocumentsFor($this->actorOwnerActorType($ownerType), $actorId);
    }

    public function actorKycDocument(string $ownerType, string $documentId): ?array
    {
        return M::actorKycDocument($documentId);
    }

    public function downloadActorKycDocumentFile(string $ownerType, string $documentId): array
    {
        $doc = M::actorKycDocument($documentId);
        $type = strtolower((string) ($doc['documentType'] ?? 'document'));
        $contentType = strtolower(trim((string) ($doc['contentType'] ?? 'application/octet-stream')));
        $extension = match ($contentType) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'bin',
        };

        return [
            'contentType' => $contentType,
            'filename' => "kyc-$type-$documentId.$extension",
            'body' => $this->mockKycDocumentBody($contentType, $documentId, $type),
        ];
    }

    public function approveActorKycDocument(string $ownerType, string $documentId): array
    {
        $updated = M::recordActorKycDocumentDecision($documentId, 'ACCEPTED');

        return $updated ?? $this->ok(['id' => $documentId, 'status' => 'ACCEPTED']);
    }

    public function rejectActorKycDocument(string $ownerType, string $documentId, string $reason): array
    {
        $updated = M::recordActorKycDocumentDecision($documentId, 'REJECTED', trim($reason));

        return $updated ?? $this->ok(['id' => $documentId, 'status' => 'REJECTED', 'rejectionReason' => trim($reason)]);
    }

    public function changeActorKycLevel(string $ownerType, string $actorId, string $kycLevel): array
    {
        $isMerchant = strtolower(trim($ownerType)) === 'merchants';
        $actor = ($isMerchant ? M::merchant($actorId) : M::agent($actorId)) ?? ['id' => $actorId];

        return array_replace($actor, ['kycLevel' => strtoupper(trim($kycLevel))]);
    }

    public function activateActor(string $ownerType, string $actorId): array
    {
        $isMerchant = strtolower(trim($ownerType)) === 'merchants';
        $actor = ($isMerchant ? M::merchant($actorId) : M::agent($actorId)) ?? ['id' => $actorId];

        // Activation creates the wallet and transitions to ACTIVE (spec §5.3b).
        return array_replace($actor, [
            'status' => 'ACTIVE',
            'walletId' => $actor['walletId'] ?? 'w-'.$actorId,
        ]);
    }

    // ── Agents ─────────────────────────────────────────────────────────────
    public function agents(array $filters = []): array { return $this->agentsPage($filters)['data']; }
    public function agentsPage(array $filters = []): array { return $this->page(M::agents($filters), $filters); }
    public function agent(string $id): ?array { return M::agent($id); }
    public function createAgent(array $payload): array { return $this->created($payload, 'AGT'); }
    public function fundAgent(string $id, string $direction, array $payload): array { return $this->ok(['direction' => $direction]); }
    public function suspendAgent(string $id, string $reason = ''): array { return $this->ok(); }
    public function reactivateAgent(string $id): array { return $this->ok(); }
    public function requestAgentClosure(string $id, string $reason = ''): array { return $this->fakeApproval('CLOSE_AGENT', $id); }
    public function resetAgentAuthPin(string $id): array { return M::agent($id) ?? $this->ok(['id' => $id]); }
    public function assignAgentLimitProfile(string $id, string $limitProfileId): array { return $this->fakeApproval('LIMIT_PROFILE_CHANGE', $id) + ['limitProfileId' => $limitProfileId]; }

    // ── Merchants ──────────────────────────────────────────────────────────
    public function merchants(array $filters = []): array { return $this->merchantsPage($filters)['data']; }
    public function merchantsPage(array $filters = []): array { return $this->page(M::merchants($filters), $filters); }
    public function merchant(string $id): ?array { return M::merchant($id); }
    public function createMerchant(array $payload): array { return $this->created($payload, 'MRC'); }
    public function setMerchantM2M(string $id, bool $enabled): array { return $this->ok(['canReceiveFromMerchant' => $enabled]); }
    public function suspendMerchant(string $id, string $reason = ''): array { return $this->ok(); }
    public function reactivateMerchant(string $id): array { return $this->ok(); }
    public function requestMerchantClosure(string $id, string $reason = ''): array { return $this->fakeApproval('CLOSE_MERCHANT', $id); }
    public function resetMerchantAuthPin(string $id): array { return M::merchant($id) ?? $this->ok(['id' => $id]); }
    public function assignMerchantLimitProfile(string $id, string $limitProfileId): array { return $this->fakeApproval('LIMIT_PROFILE_CHANGE', $id) + ['limitProfileId' => $limitProfileId]; }

    // ── Transactions ───────────────────────────────────────────────────────
    public function transactions(array $filters = []): array { return $this->transactionsPage($filters)['data']; }
    public function transactionsPage(array $filters = []): array { return $this->page(M::transactions($filters), $filters); }
    public function transaction(string $id): ?array { return M::transaction($id); }
    public function reverseTransaction(array $payload): array { return $this->fakeApproval('REVERSE_TRANSACTION', $payload['transactionId'] ?? null); }

    // ── Approvals ──────────────────────────────────────────────────────────
    public function approvals(array $filters = []): array { return $this->approvalsPage($filters)['data']; }
    public function approvalsPage(array $filters = []): array { return $this->page(M::approvals($filters), $filters); }
    public function approval(string $id): ?array { return M::approval($id); }
    public function approveRequest(string $id, array $payload = []): array { return $this->ok(['approvalId' => $id, 'status' => 'APPROVED']); }
    public function rejectRequest(string $id, array $payload): array { return $this->ok(['approvalId' => $id, 'status' => 'REJECTED']); }

    // ── Audit ──────────────────────────────────────────────────────────────
    public function auditEvents(array $filters = []): array { return $this->auditEventsPage($filters)['data']; }
    public function auditEventsPage(array $filters = []): array { return $this->page(M::auditEvents($filters), $filters); }

    // ── BO Users ───────────────────────────────────────────────────────────
    public function backofficeUsers(array $filters = []): array { return $this->backofficeUsersPage($filters)['data']; }
    public function backofficeUsersPage(array $filters = []): array { return $this->page(M::backofficeUsers(), $filters); }
    public function createBackofficeUser(array $payload): array { return $this->created($payload, 'BO'); }
    public function suspendBackofficeUser(string $id): array { return $this->ok(); }
    public function reactivateBackofficeUser(string $id): array { return $this->ok(); }
    public function closeBackofficeUser(string $id): array { return $this->ok(['status' => 'CLOSED']); }
    public function elevateBackofficeUserRole(string $id, array $payload): array
    {
        $newRole = strtoupper((string) ($payload['newRole'] ?? ''));

        if ($newRole === 'ADMIN') {
            return $this->ok([
                'status' => 'PENDING_APPROVAL',
                'approvalId' => 'apr-' . Str::random(6),
            ]);
        }

        return $this->ok([
            'status' => 'APPLIED',
            'user' => [
                'id' => $id,
                'role' => $newRole,
            ],
        ]);
    }

    // ── Dashboard ──────────────────────────────────────────────────────────
    public function dashboardStats(): array { return M::dashboardStats(); }

    // ── Wallets ────────────────────────────────────────────────────────────
    public function wallets(array $filters = []): array { return M::wallets($filters); }
    public function wallet(string $id): array { return M::wallet($id); }
    public function walletById(string $id): ?array { return M::walletById($id); }
    public function freezeWallet(string $id, string $reason = ''): array { return $this->ok(); }
    public function unfreezeWallet(string $id): array { return $this->ok(); }

    // ── Rules & Limits ─────────────────────────────────────────────────────
    public function limitProfiles(): array { return $this->limitProfilesPage()['data']; }
    public function limitProfilesPage(array $filters = []): array { return $this->page(M::limitProfiles(), $filters); }
    public function limitProfile(string $id): ?array { return M::limitProfile($id); }
    public function feeRules(array $filters = []): array { return $this->feeRulesPage($filters)['data']; }
    public function feeRulesPage(array $filters = []): array { return $this->page(M::feeRules($filters), $filters); }
    public function feeRule(string $id): ?array { return M::feeRule($id); }
    public function createFeeRule(array $payload): array { return $this->fakeApproval('FEE_RULE_CHANGE', null); }
    public function commissionRules(array $filters = []): array { return $this->commissionRulesPage($filters)['data']; }
    public function commissionRulesPage(array $filters = []): array { return $this->page(M::commissionRules($filters), $filters); }
    public function commissionRule(string $id): ?array { return M::commissionRule($id); }
    public function createCommissionRule(array $payload): array { return $this->fakeApproval('COMMISSION_RULE_CHANGE', null); }
    public function createLimitProfile(array $payload): array { return $this->fakeApproval('LIMIT_PROFILE_CHANGE', null); }
    public function controlThresholds(array $filters = []): array { return $this->controlThresholdsPage($filters)['data']; }
    public function controlThresholdsPage(array $filters = []): array { return $this->page(M::controlThresholds($filters), $filters); }
    public function controlThreshold(string $id): ?array { return M::controlThreshold($id); }
    public function createControlThreshold(array $payload): array { return $this->fakeApproval('CONTROL_THRESHOLD_CHANGE', null); }
    public function activateRule(string $kind, string $id): array { return $this->fakeApproval(strtoupper($kind) . '_ACTIVATE', $id); }
    public function deactivateRule(string $kind, string $id): array { return $this->fakeApproval(strtoupper($kind) . '_DEACTIVATE', $id); }
    public function supersedeFeeRule(string $id, array $payload): array { return $this->fakeApproval('FEE_RULE_CHANGE', $id); }
    public function supersedeCommissionRule(string $id, array $payload): array { return $this->fakeApproval('COMMISSION_RULE_CHANGE', $id); }
    public function supersedeLimitProfile(string $id, array $payload): array { return $this->fakeApproval('LIMIT_PROFILE_CHANGE', $id); }
    public function supersedeControlThreshold(string $id, array $payload): array { return $this->fakeApproval('CONTROL_THRESHOLD_CHANGE', $id); }

    // ── Treasury ───────────────────────────────────────────────────────────
    public function commissionSettlementRuns(array $filters = []): array { return $this->commissionSettlementRunsPage($filters)['data']; }
    public function commissionSettlementRunsPage(array $filters = []): array { return $this->page(M::commissionSettlementRuns($filters), $filters); }
    public function commissionSettlementRun(string $id): ?array { return M::commissionSettlementRun($id); }
    public function commissionPendingSummary(): array { return M::commissionPendingSummary(); }
    public function billProviderSettlementBalances(): array { return M::billProviderSettlementBalances(); }
    public function platformRevenueBalances(): array { return M::platformRevenueBalances(); }
    public function platformLiquidityBalances(): array { return M::platformLiquidityBalances(); }
    public function triggerCommissionSettlement(array $payload = []): array { return $this->ok(['runId' => 'csr-' . Str::random(6)]); }
    public function requestBillProviderSettlement(array $payload): array { return $this->fakeApproval('BILL_PROVIDER_SETTLEMENT', null); }
    public function requestPlatformRevenueWithdrawal(array $payload): array { return $this->fakeApproval('PLATFORM_REVENUE_WITHDRAWAL', null); }
    public function requestPlatformLiquidityTopUp(array $payload): array { return $this->fakeApproval('PLATFORM_LIQUIDITY_TOP_UP', null); }

    // ── Cards ──────────────────────────────────────────────────────────────
    public function cards(array $filters = []): array { return $this->cardsPage($filters)['data']; }
    public function cardsPage(array $filters = []): array { return $this->page(M::cards($filters), $filters); }
    public function card(string $id): ?array { return M::card($id); }
    public function cardStock(array $filters = []): array { return $this->cardStockPage($filters)['data']; }
    public function cardStockPage(array $filters = []): array { return $this->page(M::cardStock($filters), $filters); }
    public function cardStockItem(string $id): ?array { return M::cardStockItem($id); }
    public function blockCard(string $id, string $reason = ''): array { return $this->ok(); }
    public function unblockCard(string $id): array { return $this->ok(); }
    public function reportCardLost(string $id, string $reason = ''): array { return $this->ok(); }
    public function reportCardStolen(string $id, string $reason = ''): array { return $this->ok(); }
    public function closeCard(string $id, string $reason = ''): array { return $this->ok(); }
    public function importCardStock(array $payload): array { return $this->ok(['imported' => count($payload['items'] ?? [])]); }
    public function assignCardStock(array $payload): array { return $this->ok(); }

    // ── Terminals ──────────────────────────────────────────────────────────
    public function terminals(array $filters = []): array { return $this->terminalsPage($filters)['data']; }
    public function terminalsPage(array $filters = []): array { return $this->page(M::terminals($filters), $filters); }
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
    public function reconciliationIncidents(array $filters = []): array { return $this->reconciliationIncidentsPage($filters)['data']; }
    public function reconciliationIncidentsPage(array $filters = []): array { return $this->page(M::reconciliationIncidents($filters), $filters); }
    public function reconciliationIncident(string $id): ?array { return M::reconciliationIncident($id); }
    public function reconciliationRuns(array $filters = []): array { return $this->reconciliationRunsPage($filters)['data']; }
    public function reconciliationRunsPage(array $filters = []): array { return $this->page(M::reconciliationRuns($filters), $filters); }
    public function reconciliationRun(string $id): ?array { return M::reconciliationRun($id); }
    public function investigateIncident(string $id, array $payload = []): array { return $this->ok(['status' => 'INVESTIGATING']); }
    public function resolveIncident(string $id, array $payload = []): array { return $this->ok(['status' => 'RESOLVED']); }
    public function closeIncident(string $id, array $payload = []): array { return $this->ok(['status' => 'CLOSED']); }

    // ── Reports ────────────────────────────────────────────────────────────
    public function transactionSummaryReport(array $filters = []): array { return M::transactionSummaryReport($filters); }
    public function kycSummaryReport(): array { return M::kycSummaryReport(); }
    public function amlLargeTransactions(array $filters = []): array { return $this->amlLargeTransactionsPage($filters)['data']; }
    public function amlLargeTransactionsPage(array $filters = []): array { return $this->page(M::amlLargeTransactions($filters), $filters); }
    public function floatReport(): array { return M::floatReport(); }
    public function actorSummaryReport(): array { return M::actorSummaryReport(); }
    public function reportExports(array $filters = []): array
    {
        return $this->reportExportsPage($filters)['data'];
    }

    public function reportExportsPage(array $filters = []): array
    {
        $rows = array_merge($this->createdReportExports, M::reportExports());

        if (!empty($filters['reportType'])) {
            $rows = array_filter($rows, fn ($row) => $row['reportType'] === $filters['reportType']);
        }

        return $this->page($rows, $filters);
    }
    public function reportExport(string $id): ?array
    {
        foreach ($this->createdReportExports as $export) {
            if (($export['id'] ?? null) === $id) {
                return $export;
            }
        }

        return M::reportExport($id);
    }
    public function requestReportExport(array $payload): array
    {
        $reportType = strtoupper(trim((string) ($payload['reportType'] ?? 'TRANSACTION_SUMMARY')));

        $export = [
            'id' => 'exp-' . Str::random(6),
            'reportType' => $reportType,
            'periodFrom' => $this->mockReportInstant($payload['periodFrom'] ?? null, '00:00:00'),
            'periodTo' => $this->mockReportInstant($payload['periodTo'] ?? null, '23:59:59'),
            'generatedByUserId' => session('bo_user.id', '11111111-0000-0000-0000-000000000003'),
            'generatedAt' => now()->toIso8601String(),
            'recordCount' => (int) ($payload['recordCount'] ?? 0),
        ];

        array_unshift($this->createdReportExports, $export);

        return $export;
    }
    public function downloadReport(string $path, array $query = []): array
    {
        return ['url' => '/mock/exports/' . trim($path, '/') . '.csv', 'expiresAt' => now()->addHour()->toIso8601String()];
    }

    // ── helpers ────────────────────────────────────────────────────────────
    private function mockReportInstant(mixed $value, string $time): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            ? "{$value}T{$time}Z"
            : $value;
    }

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
