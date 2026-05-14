<?php

namespace App\Services\Mock;

use Illuminate\Support\Str;

class MockDataService
{
    // ──────────────────────────────────────────────────────────────────────────
    // Customers  (spec §5.3, CustomerResponse §7.2)
    // ──────────────────────────────────────────────────────────────────────────
    public static function customers(array $filters = []): array
    {
        $rows = [
            ['id'=>'aaa1','externalRef'=>'CUST-0001','fullName'=>'Fatima Moussa','phoneCountryCode'=>'269','phoneNumber'=>'3201234','dateOfBirth'=>'1992-05-14','nationalIdNumber'=>'KM9205141','kycLevel'=>'KYC_VERIFIED','status'=>'ACTIVE','walletId'=>'w-aaa1','limitProfileId'=>'lp-01','createdAt'=>'2025-01-10T08:00:00Z'],
            ['id'=>'aaa2','externalRef'=>'CUST-0002','fullName'=>'Omar Ali Hassan','phoneCountryCode'=>'269','phoneNumber'=>'3219876','dateOfBirth'=>'1987-11-20','nationalIdNumber'=>'KM8711201','kycLevel'=>'KYC_BASIC','status'=>'ACTIVE','walletId'=>'w-aaa2','limitProfileId'=>null,'createdAt'=>'2025-02-14T09:30:00Z'],
            ['id'=>'aaa3','externalRef'=>'CUST-0003','fullName'=>'Zainab Abdallah','phoneCountryCode'=>'269','phoneNumber'=>'3301122','dateOfBirth'=>'1999-03-07','nationalIdNumber'=>null,'kycLevel'=>'KYC_NONE','status'=>'PENDING_KYC','walletId'=>'w-aaa3','limitProfileId'=>null,'createdAt'=>'2025-03-01T10:15:00Z'],
            ['id'=>'aaa4','externalRef'=>'CUST-0004','fullName'=>'Ibrahim Said','phoneCountryCode'=>'269','phoneNumber'=>'3452211','dateOfBirth'=>'1980-09-22','nationalIdNumber'=>'KM8009221','kycLevel'=>'KYC_ENHANCED','status'=>'ACTIVE','walletId'=>'w-aaa4','limitProfileId'=>'lp-02','createdAt'=>'2025-01-25T07:45:00Z'],
            ['id'=>'aaa5','externalRef'=>'CUST-0005','fullName'=>'Mariam Soilihi','phoneCountryCode'=>'269','phoneNumber'=>'3567890','dateOfBirth'=>'1995-07-11','nationalIdNumber'=>'KM9507111','kycLevel'=>'KYC_BASIC','status'=>'SUSPENDED','walletId'=>'w-aaa5','limitProfileId'=>null,'createdAt'=>'2025-04-08T11:00:00Z'],
            ['id'=>'aaa6','externalRef'=>'CUST-0006','fullName'=>'Abdou Karimu','phoneCountryCode'=>'269','phoneNumber'=>'3678901','dateOfBirth'=>'1970-12-30','nationalIdNumber'=>'KM7012301','kycLevel'=>'KYC_VERIFIED','status'=>'ACTIVE','walletId'=>'w-aaa6','limitProfileId'=>'lp-01','createdAt'=>'2025-05-02T14:20:00Z'],
            ['id'=>'aaa7','externalRef'=>'CUST-0007','fullName'=>'Halima Youssouf','phoneCountryCode'=>'269','phoneNumber'=>'3789012','dateOfBirth'=>'2000-02-28','nationalIdNumber'=>null,'kycLevel'=>'KYC_NONE','status'=>'FROZEN','walletId'=>'w-aaa7','limitProfileId'=>null,'createdAt'=>'2025-06-15T16:30:00Z'],
            ['id'=>'aaa8','externalRef'=>'CUST-0008','fullName'=>'Madi Bacar','phoneCountryCode'=>'269','phoneNumber'=>'3890123','dateOfBirth'=>'1984-06-18','nationalIdNumber'=>'KM8406181','kycLevel'=>'KYC_VERIFIED','status'=>'CLOSED','walletId'=>'w-aaa8','limitProfileId'=>null,'createdAt'=>'2024-11-20T09:00:00Z'],
        ];

        if (!empty($filters['status'])) {
            $rows = array_filter($rows, fn($r) => $r['status'] === $filters['status']);
        }

        if (!empty($filters['search'])) {
            $s = strtolower($filters['search']);
            $rows = array_filter($rows, fn($r) =>
                str_contains(strtolower($r['fullName']), $s) ||
                str_contains(strtolower($r['externalRef']), $s) ||
                str_contains($r['phoneNumber'], $s)
            );
        }

        return array_values($rows);
    }

    public static function customer(string $id): ?array
    {
        return collect(static::customers())->firstWhere('id', $id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Agents  (spec §5.3, AgentResponse §7.2)
    // ──────────────────────────────────────────────────────────────────────────
    public static function agents(array $filters = []): array
    {
        $rows = [
            ['id'=>'ag01','externalRef'=>'AGT-0001','fullName'=>'Rachid Oumouri','phoneCountryCode'=>'269','phoneNumber'=>'3101010','zone'=>'Moroni Centre','kycLevel'=>'KYC_VERIFIED','status'=>'ACTIVE','walletId'=>'w-ag01','limitProfileId'=>'lp-01','canSellCards'=>true,'canDoCashIn'=>true,'canDoCashOut'=>true,'contractRef'=>'CTR-2025-001','createdAt'=>'2025-01-05T08:00:00Z'],
            ['id'=>'ag02','externalRef'=>'AGT-0002','fullName'=>'Djamila Hamidou','phoneCountryCode'=>'269','phoneNumber'=>'3202020','zone'=>'Mitsamiouli','kycLevel'=>'KYC_VERIFIED','status'=>'ACTIVE','walletId'=>'w-ag02','limitProfileId'=>'lp-01','canSellCards'=>true,'canDoCashIn'=>true,'canDoCashOut'=>false,'contractRef'=>'CTR-2025-002','createdAt'=>'2025-01-15T09:00:00Z'],
            ['id'=>'ag03','externalRef'=>'AGT-0003','fullName'=>'Hamza Attoumani','phoneCountryCode'=>'269','phoneNumber'=>'3303030','zone'=>'Fomboni','kycLevel'=>'KYC_BASIC','status'=>'PENDING_KYC','walletId'=>null,'limitProfileId'=>null,'canSellCards'=>false,'canDoCashIn'=>false,'canDoCashOut'=>false,'contractRef'=>null,'createdAt'=>'2025-03-10T10:00:00Z'],
            ['id'=>'ag04','externalRef'=>'AGT-0004','fullName'=>'Soula Badrouddine','phoneCountryCode'=>'269','phoneNumber'=>'3404040','zone'=>'Mutsamudu','kycLevel'=>'KYC_VERIFIED','status'=>'SUSPENDED','walletId'=>'w-ag04','limitProfileId'=>'lp-02','canSellCards'=>true,'canDoCashIn'=>true,'canDoCashOut'=>true,'contractRef'=>'CTR-2025-003','createdAt'=>'2025-02-01T11:00:00Z'],
            ['id'=>'ag05','externalRef'=>'AGT-0005','fullName'=>'Noura Said Ali','phoneCountryCode'=>'269','phoneNumber'=>'3505050','zone'=>'Dzaoudzi','kycLevel'=>'KYC_ENHANCED','status'=>'ACTIVE','walletId'=>'w-ag05','limitProfileId'=>'lp-01','canSellCards'=>true,'canDoCashIn'=>true,'canDoCashOut'=>true,'contractRef'=>'CTR-2025-004','createdAt'=>'2025-01-20T12:00:00Z'],
        ];

        if (!empty($filters['status'])) {
            $rows = array_filter($rows, fn($r) => $r['status'] === $filters['status']);
        }

        if (!empty($filters['search'])) {
            $s = strtolower($filters['search']);
            $rows = array_filter($rows, fn($r) =>
                str_contains(strtolower($r['fullName']), $s) ||
                str_contains(strtolower($r['externalRef']), $s) ||
                str_contains(strtolower($r['zone'] ?? ''), $s)
            );
        }

        return array_values($rows);
    }

    public static function agent(string $id): ?array
    {
        return collect(static::agents())->firstWhere('id', $id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Merchants  (spec §5.3, MerchantResponse §7.2)
    // ──────────────────────────────────────────────────────────────────────────
    public static function merchants(array $filters = []): array
    {
        $rows = [
            ['id'=>'mc01','externalRef'=>'MRC-0001','businessName'=>'Comoros Fresh Market','legalName'=>'SARL Comoros Fresh','businessType'=>'COMPANY','taxId'=>'KM12345678','phoneCountryCode'=>'269','phoneNumber'=>'7701010','category'=>'RETAIL','kycLevel'=>'KYC_VERIFIED','status'=>'ACTIVE','walletId'=>'w-mc01','limitProfileId'=>'lp-01','canCashOut'=>true,'canReceiveFromMerchant'=>false,'createdAt'=>'2025-01-08T08:00:00Z'],
            ['id'=>'mc02','externalRef'=>'MRC-0002','businessName'=>'Telecom Services KM','legalName'=>'Telecom Services KM SARL','businessType'=>'COMPANY','taxId'=>'KM87654321','phoneCountryCode'=>'269','phoneNumber'=>'7702020','category'=>'TELECOM','kycLevel'=>'KYC_ENHANCED','status'=>'ACTIVE','walletId'=>'w-mc02','limitProfileId'=>'lp-02','canCashOut'=>false,'canReceiveFromMerchant'=>true,'createdAt'=>'2025-01-12T09:00:00Z'],
            ['id'=>'mc03','externalRef'=>'MRC-0003','businessName'=>'Restaurant Chez Mama','legalName'=>'Mama Soule EI','businessType'=>'SOLE_TRADER','taxId'=>null,'phoneCountryCode'=>'269','phoneNumber'=>'7703030','category'=>'FOOD','kycLevel'=>'KYC_BASIC','status'=>'PENDING_KYC','walletId'=>null,'limitProfileId'=>null,'canCashOut'=>false,'canReceiveFromMerchant'=>false,'createdAt'=>'2025-03-20T10:00:00Z'],
            ['id'=>'mc04','externalRef'=>'MRC-0004','businessName'=>'NGO Espoir Comores','legalName'=>'Association Espoir','businessType'=>'NGO','taxId'=>null,'phoneCountryCode'=>'269','phoneNumber'=>'7704040','category'=>'SERVICE','kycLevel'=>'KYC_VERIFIED','status'=>'SUSPENDED','walletId'=>'w-mc04','limitProfileId'=>null,'canCashOut'=>false,'canReceiveFromMerchant'=>false,'createdAt'=>'2025-02-05T11:00:00Z'],
            ['id'=>'mc05','externalRef'=>'MRC-0005','businessName'=>'Électricité Moroni','legalName'=>'MA-MWE Moroni','businessType'=>'COMPANY','taxId'=>'KM11223344','phoneCountryCode'=>'269','phoneNumber'=>'7705050','category'=>'UTILITY','kycLevel'=>'KYC_ENHANCED','status'=>'ACTIVE','walletId'=>'w-mc05','limitProfileId'=>'lp-02','canCashOut'=>false,'canReceiveFromMerchant'=>false,'createdAt'=>'2025-01-03T07:00:00Z'],
        ];

        if (!empty($filters['status'])) {
            $rows = array_filter($rows, fn($r) => $r['status'] === $filters['status']);
        }

        if (!empty($filters['search'])) {
            $s = strtolower($filters['search']);
            $rows = array_filter($rows, fn($r) =>
                str_contains(strtolower($r['businessName']), $s) ||
                str_contains(strtolower($r['externalRef']), $s)
            );
        }

        return array_values($rows);
    }

    public static function merchant(string $id): ?array
    {
        return collect(static::merchants())->firstWhere('id', $id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Transactions  (spec §5.10, TransactionResponse §7.5)
    // ──────────────────────────────────────────────────────────────────────────
    public static function transactions(array $filters = []): array
    {
        $rows = [
            ['id'=>'tx01','type'=>'CASH_IN','status'=>'COMPLETED','initiatorType'=>'AGENT','initiatorId'=>'ag01','requestedAmount'=>50000,'feeAmount'=>500,'commissionAmount'=>250,'netAmountToDestination'=>49500,'currency'=>'KMF','createdAt'=>'2026-05-06T08:23:00Z','completedAt'=>'2026-05-06T08:23:05Z'],
            ['id'=>'tx02','type'=>'PAYMENT','status'=>'COMPLETED','initiatorType'=>'CUSTOMER','initiatorId'=>'aaa1','requestedAmount'=>12500,'feeAmount'=>125,'commissionAmount'=>0,'netAmountToDestination'=>12375,'currency'=>'KMF','createdAt'=>'2026-05-06T09:10:00Z','completedAt'=>'2026-05-06T09:10:02Z'],
            ['id'=>'tx03','type'=>'CASH_OUT','status'=>'PENDING','initiatorType'=>'CUSTOMER','initiatorId'=>'aaa2','requestedAmount'=>30000,'feeAmount'=>300,'commissionAmount'=>150,'netAmountToDestination'=>29700,'currency'=>'KMF','createdAt'=>'2026-05-06T09:45:00Z','completedAt'=>null],
            ['id'=>'tx04','type'=>'P2P_TRANSFER','status'=>'COMPLETED','initiatorType'=>'CUSTOMER','initiatorId'=>'aaa4','requestedAmount'=>5000,'feeAmount'=>0,'commissionAmount'=>0,'netAmountToDestination'=>5000,'currency'=>'KMF','createdAt'=>'2026-05-06T10:01:00Z','completedAt'=>'2026-05-06T10:01:01Z'],
            ['id'=>'tx05','type'=>'SERVICE_PAYMENT','status'=>'COMPLETED','initiatorType'=>'CUSTOMER','initiatorId'=>'aaa1','requestedAmount'=>8000,'feeAmount'=>80,'commissionAmount'=>0,'netAmountToDestination'=>7920,'currency'=>'KMF','createdAt'=>'2026-05-06T10:30:00Z','completedAt'=>'2026-05-06T10:30:03Z'],
            ['id'=>'tx06','type'=>'CASH_IN','status'=>'DECLINED','initiatorType'=>'AGENT','initiatorId'=>'ag02','requestedAmount'=>200000,'feeAmount'=>0,'commissionAmount'=>0,'netAmountToDestination'=>0,'currency'=>'KMF','declineReason'=>'INSUFFICIENT_FLOAT','createdAt'=>'2026-05-06T11:15:00Z','completedAt'=>null],
            ['id'=>'tx07','type'=>'CARD_SALE','status'=>'COMPLETED','initiatorType'=>'AGENT','initiatorId'=>'ag01','requestedAmount'=>2000,'feeAmount'=>0,'commissionAmount'=>0,'netAmountToDestination'=>2000,'currency'=>'KMF','createdAt'=>'2026-05-06T11:45:00Z','completedAt'=>'2026-05-06T11:45:01Z'],
            ['id'=>'tx08','type'=>'AGENT_FUND_IN','status'=>'COMPLETED','initiatorType'=>'BACKOFFICE_USER','initiatorId'=>'11111111-0000-0000-0000-000000000001','requestedAmount'=>500000,'feeAmount'=>0,'commissionAmount'=>0,'netAmountToDestination'=>500000,'currency'=>'KMF','createdAt'=>'2026-05-05T14:00:00Z','completedAt'=>'2026-05-05T14:00:05Z'],
            ['id'=>'tx09','type'=>'COMMISSION_PAYOUT','status'=>'COMPLETED','initiatorType'=>'SYSTEM','initiatorId'=>'system','requestedAmount'=>15750,'feeAmount'=>0,'commissionAmount'=>0,'netAmountToDestination'=>15750,'currency'=>'KMF','createdAt'=>'2026-05-05T00:01:00Z','completedAt'=>'2026-05-05T00:01:10Z'],
            ['id'=>'tx10','type'=>'REVERSAL','status'=>'COMPLETED','initiatorType'=>'BACKOFFICE_USER','initiatorId'=>'11111111-0000-0000-0000-000000000001','requestedAmount'=>12500,'feeAmount'=>0,'commissionAmount'=>0,'netAmountToDestination'=>12500,'currency'=>'KMF','reversalOfTransactionId'=>'tx02','createdAt'=>'2026-05-05T16:30:00Z','completedAt'=>'2026-05-05T16:30:05Z'],
            ['id'=>'tx11','type'=>'PLATFORM_LIQUIDITY_TOP_UP','status'=>'COMPLETED','initiatorType'=>'BACKOFFICE_USER','initiatorId'=>'11111111-0000-0000-0000-000000000001','requestedAmount'=>2500000,'feeAmount'=>0,'commissionAmount'=>0,'netAmountToDestination'=>2500000,'currency'=>'KMF','sourceWalletId'=>'w-system-liquidity-funding-clearing','destinationWalletId'=>'w-system-liquidity','createdAt'=>'2026-05-05T17:20:00Z','completedAt'=>'2026-05-05T17:20:06Z'],
        ];

        if (!empty($filters['type'])) {
            $rows = array_filter($rows, fn($r) => $r['type'] === $filters['type']);
        }

        if (!empty($filters['status'])) {
            $rows = array_filter($rows, fn($r) => $r['status'] === $filters['status']);
        }

        return array_values($rows);
    }

    public static function transaction(string $id): ?array
    {
        return collect(static::transactions())->firstWhere('id', $id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Approvals  (spec §5.4, ApprovalRequestResponse §7.3)
    // ──────────────────────────────────────────────────────────────────────────
    public static function approvals(array $filters = []): array
    {
        $rows = [
            ['id'=>'ap01','type'=>'AGENT_FUND_IN','status'=>'PENDING_APPROVAL','requestedBy'=>'11111111-0000-0000-0000-000000000002','targetEntityType'=>'AGENT','targetEntityId'=>'ag03','payload'=>json_encode(['agentId'=>'ag03','amount'=>300000,'currency'=>'KMF','notes'=>'Initial float top-up']),'expiresAt'=>'2026-05-08T08:00:00Z','createdAt'=>'2026-05-06T08:00:00Z'],
            ['id'=>'ap02','type'=>'REVERSAL','status'=>'PENDING_APPROVAL','requestedBy'=>'11111111-0000-0000-0000-000000000002','targetEntityType'=>'TRANSACTION','targetEntityId'=>'tx02','payload'=>json_encode(['reason'=>'Customer complained — duplicate charge']),'expiresAt'=>'2026-05-08T09:10:00Z','createdAt'=>'2026-05-06T09:10:00Z'],
            ['id'=>'ap03','type'=>'ACCOUNT_CLOSURE','status'=>'PENDING_APPROVAL','requestedBy'=>'11111111-0000-0000-0000-000000000002','targetEntityType'=>'CUSTOMER','targetEntityId'=>'aaa8','payload'=>json_encode(['actorType'=>'CUSTOMER','reason'=>'Customer requested account closure']),'expiresAt'=>'2026-05-09T10:00:00Z','createdAt'=>'2026-05-06T10:00:00Z'],
            ['id'=>'ap04','type'=>'FEE_RULE_CHANGE','status'=>'PENDING_APPROVAL','requestedBy'=>'11111111-0000-0000-0000-000000000001','targetEntityType'=>'FEE_RULE','targetEntityId'=>'fr01','payload'=>json_encode(['action'=>'CREATE','name'=>'Standard Cash-In Fee','transactionType'=>'CASH_IN','calculationType'=>'PERCENTAGE','percentage'=>0.01,'feeBearer'=>'SENDER']),'expiresAt'=>'2026-05-09T11:00:00Z','createdAt'=>'2026-05-06T11:00:00Z'],
            ['id'=>'ap05','type'=>'LARGE_CASH_OUT','status'=>'PENDING_APPROVAL','requestedBy'=>'11111111-0000-0000-0000-000000000002','targetEntityType'=>'TRANSACTION','targetEntityId'=>null,'payload'=>json_encode(['merchantId'=>'mc01','agentId'=>'ag01','amount'=>500000,'currency'=>'KMF']),'expiresAt'=>'2026-05-07T14:00:00Z','createdAt'=>'2026-05-06T14:00:00Z'],
            ['id'=>'ap06','type'=>'AGENT_FUND_OUT','status'=>'APPROVED','requestedBy'=>'11111111-0000-0000-0000-000000000002','targetEntityType'=>'AGENT','targetEntityId'=>'ag04','payload'=>json_encode(['agentId'=>'ag04','amount'=>150000,'currency'=>'KMF','notes'=>'Float reduction - reconciliation']),'approvedBy'=>'11111111-0000-0000-0000-000000000001','decisionAt'=>'2026-05-05T16:00:00Z','decisionReason'=>null,'expiresAt'=>'2026-05-07T15:00:00Z','createdAt'=>'2026-05-05T15:00:00Z'],
            ['id'=>'ap07','type'=>'BILL_PROVIDER_SETTLEMENT','status'=>'REJECTED','requestedBy'=>'11111111-0000-0000-0000-000000000002','targetEntityType'=>'SYSTEM','targetEntityId'=>null,'payload'=>json_encode(['amount'=>1200000,'currency'=>'KMF','notes'=>'Weekly settlement to Comores Telecom']),'rejectedBy'=>'11111111-0000-0000-0000-000000000001','decisionAt'=>'2026-05-04T12:00:00Z','decisionReason'=>'Insufficient balance in clearing account','expiresAt'=>'2026-05-06T12:00:00Z','createdAt'=>'2026-05-04T10:00:00Z'],
            ['id'=>'ap08','type'=>'PLATFORM_LIQUIDITY_TOP_UP','status'=>'PENDING_APPROVAL','requestedBy'=>'11111111-0000-0000-0000-000000000002','targetEntityType'=>'SYSTEM','targetEntityId'=>'SYSTEM_LIQUIDITY','payload'=>json_encode(['amount'=>3500000,'currency'=>'KMF','externalReference'=>'WIRE-2026-05-009','source'=>'BANK_WIRE','notes'=>'Treasury funding injection for agent float']),'expiresAt'=>'2026-05-09T17:20:00Z','createdAt'=>'2026-05-06T17:20:00Z'],
        ];

        if (isset($filters['pendingOnly']) && $filters['pendingOnly']) {
            $rows = array_filter($rows, fn($r) => $r['status'] === 'PENDING_APPROVAL');
        }

        if (!empty($filters['type'])) {
            $rows = array_filter($rows, fn($r) => $r['type'] === $filters['type']);
        }

        return array_values($rows);
    }

    public static function approval(string $id): ?array
    {
        return collect(static::approvals())->firstWhere('id', $id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Audit Events  (spec §5.5, AuditEventResponse §7.3)
    // ──────────────────────────────────────────────────────────────────────────
    public static function auditEvents(array $filters = []): array
    {
        $rows = [
            ['id'=>'ev01','eventType'=>'BACKOFFICE_LOGIN','actorId'=>'11111111-0000-0000-0000-000000000001','actorType'=>'BACKOFFICE_USER','targetEntityType'=>null,'targetEntityId'=>null,'ipAddress'=>'197.229.13.44','userAgent'=>'Mozilla/5.0 Chrome/124','correlationId'=>'c1-abc','occurredAt'=>'2026-05-06T07:58:00Z'],
            ['id'=>'ev02','eventType'=>'CUSTOMER_SUSPENDED','actorId'=>'11111111-0000-0000-0000-000000000002','actorType'=>'BACKOFFICE_USER','targetEntityType'=>'CUSTOMER','targetEntityId'=>'aaa5','ipAddress'=>'197.229.13.45','userAgent'=>'Mozilla/5.0 Firefox/125','correlationId'=>'c2-def','occurredAt'=>'2026-05-06T08:12:00Z'],
            ['id'=>'ev03','eventType'=>'APPROVAL_CREATED','actorId'=>'11111111-0000-0000-0000-000000000002','actorType'=>'BACKOFFICE_USER','targetEntityType'=>'APPROVAL_REQUEST','targetEntityId'=>'ap01','ipAddress'=>'197.229.13.45','userAgent'=>'Mozilla/5.0 Firefox/125','correlationId'=>'c3-ghi','occurredAt'=>'2026-05-06T08:00:00Z'],
            ['id'=>'ev04','eventType'=>'APPROVAL_APPROVED','actorId'=>'11111111-0000-0000-0000-000000000001','actorType'=>'BACKOFFICE_USER','targetEntityType'=>'APPROVAL_REQUEST','targetEntityId'=>'ap06','ipAddress'=>'197.229.13.44','userAgent'=>'Mozilla/5.0 Chrome/124','correlationId'=>'c4-jkl','occurredAt'=>'2026-05-05T16:00:00Z'],
            ['id'=>'ev05','eventType'=>'KYC_APPROVED','actorId'=>'11111111-0000-0000-0000-000000000002','actorType'=>'BACKOFFICE_USER','targetEntityType'=>'AGENT','targetEntityId'=>'ag01','ipAddress'=>'197.229.13.45','userAgent'=>'Mozilla/5.0 Firefox/125','correlationId'=>'c5-mno','occurredAt'=>'2026-05-05T14:30:00Z'],
            ['id'=>'ev06','eventType'=>'FEE_RULE_CREATED','actorId'=>'11111111-0000-0000-0000-000000000001','actorType'=>'BACKOFFICE_USER','targetEntityType'=>'FEE_RULE','targetEntityId'=>'fr01','ipAddress'=>'197.229.13.44','userAgent'=>'Mozilla/5.0 Chrome/124','correlationId'=>'c6-pqr','occurredAt'=>'2026-05-06T11:00:00Z'],
            ['id'=>'ev07','eventType'=>'REGULATORY_REPORT_EXPORTED','actorId'=>'11111111-0000-0000-0000-000000000003','actorType'=>'BACKOFFICE_USER','targetEntityType'=>'REPORT','targetEntityId'=>null,'ipAddress'=>'197.229.13.46','userAgent'=>'Mozilla/5.0 Safari/17','correlationId'=>'c7-stu','occurredAt'=>'2026-05-05T09:00:00Z'],
            ['id'=>'ev08','eventType'=>'WALLET_FROZEN','actorId'=>'11111111-0000-0000-0000-000000000001','actorType'=>'BACKOFFICE_USER','targetEntityType'=>'WALLET','targetEntityId'=>'w-aaa7','ipAddress'=>'197.229.13.44','userAgent'=>'Mozilla/5.0 Chrome/124','correlationId'=>'c8-vwx','occurredAt'=>'2026-05-04T15:00:00Z'],
        ];

        if (!empty($filters['eventType'])) {
            $rows = array_filter($rows, fn($r) => $r['eventType'] === $filters['eventType']);
        }

        if (!empty($filters['actorId'])) {
            $rows = array_filter($rows, fn($r) => ($r['actorId'] ?? null) === $filters['actorId']);
        }

        if (!empty($filters['from'])) {
            $from = strtotime((string) $filters['from']);

            if ($from !== false) {
                $rows = array_filter($rows, fn($r) => strtotime($r['occurredAt']) >= $from);
            }
        }

        if (!empty($filters['to'])) {
            $toValue = (string) $filters['to'];

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toValue)) {
                $toValue .= ' 23:59:59';
            }

            $to = strtotime($toValue);

            if ($to !== false) {
                $rows = array_filter($rows, fn($r) => strtotime($r['occurredAt']) <= $to);
            }
        }

        if (!empty($filters['correlationId'])) {
            $rows = array_filter($rows, fn($r) => ($r['correlationId'] ?? null) === $filters['correlationId']);
        }

        return array_values($rows);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // BO Users  (spec §5.2, BackofficeUserResponse §7.1)
    // ──────────────────────────────────────────────────────────────────────────
    public static function backofficeUsers(): array
    {
        return [
            ['id'=>'11111111-0000-0000-0000-000000000001','email'=>'admin@komopay.km','fullName'=>'Admin User','role'=>'SUPER_ADMIN','status'=>'ACTIVE','mfaEnabled'=>true,'lastLoginAt'=>'2026-05-06T07:58:00Z','createdAt'=>'2025-01-01T00:00:00Z'],
            ['id'=>'11111111-0000-0000-0000-000000000002','email'=>'supervisor@komopay.km','fullName'=>'Supervisor User','role'=>'SUPERVISOR','status'=>'ACTIVE','mfaEnabled'=>false,'lastLoginAt'=>'2026-05-06T08:12:00Z','createdAt'=>'2025-01-15T00:00:00Z'],
            ['id'=>'11111111-0000-0000-0000-000000000003','email'=>'compliance@komopay.km','fullName'=>'Compliance Officer','role'=>'COMPLIANCE','status'=>'ACTIVE','mfaEnabled'=>true,'lastLoginAt'=>'2026-05-05T09:00:00Z','createdAt'=>'2025-02-01T00:00:00Z'],
            ['id'=>'11111111-0000-0000-0000-000000000004','email'=>'operator@komopay.km','fullName'=>'Ops Operator','role'=>'OPERATOR','status'=>'SUSPENDED','mfaEnabled'=>false,'lastLoginAt'=>'2026-04-20T10:00:00Z','createdAt'=>'2025-03-01T00:00:00Z'],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Dashboard stats (aggregated mock)
    // ──────────────────────────────────────────────────────────────────────────
    public static function dashboardStats(): array
    {
        return [
            'totalCustomers'       => 18_432,
            'activeAgents'         => 147,
            'activeMerchants'      => 93,
            'transactionsToday'    => 2_841,
            'volumeToday'          => 87_450_000,
            'pendingApprovals'     => 5,
            'openReconciliation'   => 2,
            'txByType' => [
                ['type' => 'CASH_IN',          'count' => 1240, 'amount' => 42_100_000],
                ['type' => 'CASH_OUT',         'count' =>  380, 'amount' => 18_200_000],
                ['type' => 'PAYMENT',          'count' =>  820, 'amount' => 15_600_000],
                ['type' => 'P2P_TRANSFER',     'count' =>  280, 'amount' =>  9_300_000],
                ['type' => 'SERVICE_PAYMENT',  'count' =>  121, 'amount' =>  2_250_000],
            ],
            'recentTransactions' => array_slice(static::transactions(), 0, 5),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Wallets
    // ──────────────────────────────────────────────────────────────────────────
    public static function wallet(string $id): array
    {
        return [
            'id'               => $id,
            'ownerType'        => 'CUSTOMER',
            'ownerId'          => 'aaa1',
            'currency'         => 'KMF',
            'status'           => 'ACTIVE',
            'availableBalance' => 125_400,
            'frozenBalance'    => 0,
            'version'          => 42,
            'createdAt'        => '2025-01-10T08:00:00Z',
            'updatedAt'        => '2026-05-06T08:23:05Z',
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Limit Profiles  (spec §5.14, LimitProfileResponse §7.6)
    // ──────────────────────────────────────────────────────────────────────────
    public static function limitProfiles(): array
    {
        return [
            ['id'=>'lp-01','name'=>'Standard Profile','applicableActorTypes'=>['CUSTOMER','AGENT'],'maxTransactionAmount'=>500000,'minTransactionAmount'=>100,'maxDailyAmount'=>2000000,'maxWeeklyAmount'=>10000000,'maxMonthlyAmount'=>30000000,'maxDailyTransactionCount'=>50,'maxMonthlyTransactionCount'=>500,'requiredKycLevel'=>'KYC_BASIC','active'=>true,'version'=>1,'createdAt'=>'2025-01-01T00:00:00Z','updatedAt'=>'2025-01-01T00:00:00Z'],
            ['id'=>'lp-02','name'=>'Premium Profile','applicableActorTypes'=>['CUSTOMER','AGENT','MERCHANT'],'maxTransactionAmount'=>2000000,'minTransactionAmount'=>100,'maxDailyAmount'=>10000000,'maxWeeklyAmount'=>50000000,'maxMonthlyAmount'=>150000000,'maxDailyTransactionCount'=>200,'maxMonthlyTransactionCount'=>2000,'requiredKycLevel'=>'KYC_VERIFIED','active'=>true,'version'=>2,'createdAt'=>'2025-01-01T00:00:00Z','updatedAt'=>'2025-03-15T00:00:00Z'],
            ['id'=>'lp-03','name'=>'Merchant Basic','applicableActorTypes'=>['MERCHANT'],'maxTransactionAmount'=>1000000,'minTransactionAmount'=>500,'maxDailyAmount'=>5000000,'maxWeeklyAmount'=>25000000,'maxMonthlyAmount'=>75000000,'maxDailyTransactionCount'=>100,'maxMonthlyTransactionCount'=>1000,'requiredKycLevel'=>'KYC_BASIC','active'=>false,'version'=>1,'createdAt'=>'2025-02-01T00:00:00Z','updatedAt'=>'2025-02-01T00:00:00Z'],
        ];
    }

    public static function limitProfile(string $id): ?array
    {
        return collect(static::limitProfiles())->firstWhere('id', $id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Fee Rules  (spec §5.11, FeeRuleResponse §7.5)
    // ──────────────────────────────────────────────────────────────────────────
    public static function feeRules(array $filters = []): array
    {
        $rows = [
            ['id'=>'fr01','name'=>'Standard Cash-In','description'=>'1% on all CASH_IN','transactionType'=>'CASH_IN','actorType'=>null,'actorId'=>null,'cardType'=>null,'merchantCategory'=>null,'minAmount'=>null,'maxAmount'=>null,'calculationType'=>'PERCENTAGE','flatAmount'=>null,'percentage'=>0.01,'minFeeAmount'=>100,'maxFeeAmount'=>5000,'feeBearer'=>'SENDER','priority'=>10,'validFrom'=>'2025-01-01T00:00:00Z','validTo'=>null,'active'=>true,'version'=>1,'previousVersionId'=>null,'createdAt'=>'2025-01-01T00:00:00Z'],
            ['id'=>'fr02','name'=>'Standard Cash-Out','description'=>'1.5% on CASH_OUT','transactionType'=>'CASH_OUT','actorType'=>null,'actorId'=>null,'cardType'=>null,'merchantCategory'=>null,'minAmount'=>null,'maxAmount'=>null,'calculationType'=>'PERCENTAGE','flatAmount'=>null,'percentage'=>0.015,'minFeeAmount'=>200,'maxFeeAmount'=>10000,'feeBearer'=>'SENDER','priority'=>10,'validFrom'=>'2025-01-01T00:00:00Z','validTo'=>null,'active'=>true,'version'=>2,'previousVersionId'=>null,'createdAt'=>'2025-01-01T00:00:00Z'],
            ['id'=>'fr03','name'=>'Merchant Payment Tiered','description'=>'Tiered for PAYMENT','transactionType'=>'PAYMENT','actorType'=>'MERCHANT','actorId'=>null,'cardType'=>null,'merchantCategory'=>'RETAIL','minAmount'=>null,'maxAmount'=>null,'calculationType'=>'TIERED','flatAmount'=>null,'percentage'=>null,'minFeeAmount'=>null,'maxFeeAmount'=>null,'feeBearer'=>'RECEIVER','priority'=>5,'validFrom'=>'2025-02-01T00:00:00Z','validTo'=>null,'active'=>true,'version'=>1,'previousVersionId'=>null,'createdAt'=>'2025-02-01T00:00:00Z'],
            ['id'=>'fr04','name'=>'Free P2P','description'=>'No fee on P2P','transactionType'=>'P2P_TRANSFER','actorType'=>null,'actorId'=>null,'cardType'=>null,'merchantCategory'=>null,'minAmount'=>null,'maxAmount'=>null,'calculationType'=>'ZERO','flatAmount'=>null,'percentage'=>null,'minFeeAmount'=>null,'maxFeeAmount'=>null,'feeBearer'=>'SENDER','priority'=>20,'validFrom'=>'2025-01-01T00:00:00Z','validTo'=>null,'active'=>true,'version'=>1,'previousVersionId'=>null,'createdAt'=>'2025-01-01T00:00:00Z'],
            ['id'=>'fr05','name'=>'Service Payment Flat','description'=>'80 KMF flat','transactionType'=>'SERVICE_PAYMENT','actorType'=>null,'actorId'=>null,'cardType'=>null,'merchantCategory'=>null,'minAmount'=>null,'maxAmount'=>null,'calculationType'=>'FLAT','flatAmount'=>80,'percentage'=>null,'minFeeAmount'=>null,'maxFeeAmount'=>null,'feeBearer'=>'SENDER','priority'=>10,'validFrom'=>'2025-01-01T00:00:00Z','validTo'=>'2026-12-31T23:59:59Z','active'=>false,'version'=>1,'previousVersionId'=>null,'createdAt'=>'2025-01-01T00:00:00Z'],
        ];

        if (!empty($filters['transactionType'])) {
            $rows = array_filter($rows, fn($r) => $r['transactionType'] === $filters['transactionType']);
        }
        if (isset($filters['active'])) {
            $rows = array_filter($rows, fn($r) => $r['active'] === $filters['active']);
        }
        return array_values($rows);
    }

    public static function feeRule(string $id): ?array
    {
        return collect(static::feeRules())->firstWhere('id', $id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Commission Rules  (spec §5.12, CommissionRuleResponse §7.5)
    // ──────────────────────────────────────────────────────────────────────────
    public static function commissionRules(array $filters = []): array
    {
        $rows = [
            ['id'=>'cr01','name'=>'Cash-In Agent Commission','transactionType'=>'CASH_IN','agentId'=>null,'calculationType'=>'ON_FEE_AMOUNT','flatAmount'=>null,'percentage'=>0.50,'settlementMode'=>'BATCH_DAILY','currency'=>'KMF','priority'=>10,'validFrom'=>'2025-01-01T00:00:00Z','validTo'=>null,'active'=>true,'version'=>1,'previousVersionId'=>null,'createdBy'=>'11111111-0000-0000-0000-000000000001','createdAt'=>'2025-01-01T00:00:00Z','modifiedAt'=>null],
            ['id'=>'cr02','name'=>'Cash-Out Agent Commission','transactionType'=>'CASH_OUT','agentId'=>null,'calculationType'=>'ON_FEE_AMOUNT','flatAmount'=>null,'percentage'=>0.50,'settlementMode'=>'BATCH_DAILY','currency'=>'KMF','priority'=>10,'validFrom'=>'2025-01-01T00:00:00Z','validTo'=>null,'active'=>true,'version'=>1,'previousVersionId'=>null,'createdBy'=>'11111111-0000-0000-0000-000000000001','createdAt'=>'2025-01-01T00:00:00Z','modifiedAt'=>null],
            ['id'=>'cr03','name'=>'Card Sale Flat Commission','transactionType'=>'CARD_SALE','agentId'=>null,'calculationType'=>'FLAT','flatAmount'=>50,'percentage'=>null,'settlementMode'=>'BATCH_WEEKLY','currency'=>'KMF','priority'=>5,'validFrom'=>'2025-01-01T00:00:00Z','validTo'=>null,'active'=>true,'version'=>1,'previousVersionId'=>null,'createdBy'=>'11111111-0000-0000-0000-000000000001','createdAt'=>'2025-01-01T00:00:00Z','modifiedAt'=>null],
            ['id'=>'cr04','name'=>'Premium Agent Cash-In','transactionType'=>'CASH_IN','agentId'=>'ag05','calculationType'=>'ON_TRANSACTION_AMOUNT','flatAmount'=>null,'percentage'=>0.005,'settlementMode'=>'BATCH_DAILY','currency'=>'KMF','priority'=>1,'validFrom'=>'2025-03-01T00:00:00Z','validTo'=>null,'active'=>true,'version'=>1,'previousVersionId'=>null,'createdBy'=>'11111111-0000-0000-0000-000000000001','createdAt'=>'2025-03-01T00:00:00Z','modifiedAt'=>'2025-03-15T00:00:00Z'],
        ];

        if (!empty($filters['transactionType'])) {
            $rows = array_filter($rows, fn($r) => $r['transactionType'] === $filters['transactionType']);
        }
        return array_values($rows);
    }

    public static function commissionRule(string $id): ?array
    {
        return collect(static::commissionRules())->firstWhere('id', $id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Control Thresholds  (spec §5.15, ControlThresholdResponse §7.6)
    // ──────────────────────────────────────────────────────────────────────────
    public static function controlThresholds(array $filters = []): array
    {
        $rows = [
            ['id'=>'ct01','transactionType'=>'CASH_OUT','actorType'=>'MERCHANT','scopeType'=>'GLOBAL','scopeId'=>null,'currency'=>'KMF','pinRequiredAboveAmount'=>10000,'confirmationRequiredAboveAmount'=>50000,'approvalRequiredAboveAmount'=>500000,'approvalType'=>'LARGE_CASH_OUT','active'=>true,'version'=>1,'createdAt'=>'2025-01-01T00:00:00Z','updatedAt'=>'2025-01-01T00:00:00Z'],
            ['id'=>'ct02','transactionType'=>'CASH_OUT','actorType'=>'CUSTOMER','scopeType'=>'GLOBAL','scopeId'=>null,'currency'=>'KMF','pinRequiredAboveAmount'=>5000,'confirmationRequiredAboveAmount'=>25000,'approvalRequiredAboveAmount'=>250000,'approvalType'=>'LARGE_CASH_OUT','active'=>true,'version'=>1,'createdAt'=>'2025-01-01T00:00:00Z','updatedAt'=>'2025-01-01T00:00:00Z'],
            ['id'=>'ct03','transactionType'=>'PAYMENT','actorType'=>'CUSTOMER','scopeType'=>'GLOBAL','scopeId'=>null,'currency'=>'KMF','pinRequiredAboveAmount'=>2000,'confirmationRequiredAboveAmount'=>20000,'approvalRequiredAboveAmount'=>null,'approvalType'=>null,'active'=>true,'version'=>1,'createdAt'=>'2025-01-01T00:00:00Z','updatedAt'=>'2025-01-01T00:00:00Z'],
            ['id'=>'ct04','transactionType'=>'P2P_TRANSFER','actorType'=>'CUSTOMER','scopeType'=>'GLOBAL','scopeId'=>null,'currency'=>'KMF','pinRequiredAboveAmount'=>1000,'confirmationRequiredAboveAmount'=>10000,'approvalRequiredAboveAmount'=>null,'approvalType'=>null,'active'=>true,'version'=>2,'createdAt'=>'2025-01-01T00:00:00Z','updatedAt'=>'2025-04-10T00:00:00Z'],
            ['id'=>'ct05','transactionType'=>'CASH_IN','actorType'=>'AGENT','scopeType'=>'AGENT','scopeId'=>'ag05','currency'=>'KMF','pinRequiredAboveAmount'=>50000,'confirmationRequiredAboveAmount'=>200000,'approvalRequiredAboveAmount'=>1000000,'approvalType'=>'AGENT_FUND_IN','active'=>false,'version'=>1,'createdAt'=>'2025-02-01T00:00:00Z','updatedAt'=>'2025-02-01T00:00:00Z'],
        ];

        if (!empty($filters['transactionType'])) {
            $rows = array_filter($rows, fn($r) => $r['transactionType'] === $filters['transactionType']);
        }
        return array_values($rows);
    }

    public static function controlThreshold(string $id): ?array
    {
        return collect(static::controlThresholds())->firstWhere('id', $id);
    }

    public static function commissionSettlementRuns(array $filters = []): array
    {
        $rows = [
            ['id'=>'csr01','mode'=>'BATCH_DAILY','businessDay'=>'2026-05-05','triggeredByType'=>'BACKOFFICE_USER','triggeredById'=>'11111111-0000-0000-0000-000000000002','startedAt'=>'2026-05-06T00:05:00Z','completedAt'=>'2026-05-06T00:07:12Z','agentsTotal'=>42,'agentsSettled'=>42,'agentsFailed'=>0,'payoutsSettled'=>318,'amountSettled'=>1575000,'status'=>'COMPLETED','errorSummary'=>null],
            ['id'=>'csr02','mode'=>'BATCH_WEEKLY','businessDay'=>'2026-05-03','triggeredByType'=>'BACKOFFICE_JOB','triggeredById'=>null,'startedAt'=>'2026-05-04T00:10:00Z','completedAt'=>'2026-05-04T00:18:40Z','agentsTotal'=>147,'agentsSettled'=>143,'agentsFailed'=>4,'payoutsSettled'=>1260,'amountSettled'=>8425000,'status'=>'PARTIAL_FAILURE','errorSummary'=>'4 agents skipped because settlement wallet was frozen'],
            ['id'=>'csr03','mode'=>'BATCH_DAILY','businessDay'=>'2026-05-04','triggeredByType'=>'BACKOFFICE_JOB','triggeredById'=>null,'startedAt'=>'2026-05-05T00:05:00Z','completedAt'=>'2026-05-05T00:05:36Z','agentsTotal'=>0,'agentsSettled'=>0,'agentsFailed'=>0,'payoutsSettled'=>0,'amountSettled'=>0,'status'=>'NO_PAYOUTS','errorSummary'=>null],
            ['id'=>'csr04','mode'=>'BATCH_DAILY','businessDay'=>'2026-05-02','triggeredByType'=>'BACKOFFICE_USER','triggeredById'=>'11111111-0000-0000-0000-000000000001','startedAt'=>'2026-05-03T00:05:00Z','completedAt'=>'2026-05-03T00:06:10Z','agentsTotal'=>38,'agentsSettled'=>0,'agentsFailed'=>38,'payoutsSettled'=>0,'amountSettled'=>0,'status'=>'FAILED','errorSummary'=>'Ledger posting rejected by reconciliation guard'],
        ];

        if (!empty($filters['mode'])) {
            $rows = array_filter($rows, fn($r) => $r['mode'] === $filters['mode']);
        }

        if (!empty($filters['status'])) {
            $rows = array_filter($rows, fn($r) => $r['status'] === $filters['status']);
        }

        return array_values($rows);
    }

    public static function commissionSettlementRun(string $id): ?array
    {
        return collect(static::commissionSettlementRuns())->firstWhere('id', $id);
    }

    public static function commissionPendingSummary(): array
    {
        return [
            'pendingDailyCount' => 87,
            'pendingDailyAmount' => 615000,
            'pendingWeeklyCount' => 23,
            'pendingWeeklyAmount' => 1840000,
        ];
    }

    public static function billProviderSettlementBalances(): array
    {
        return [
            'providerPayableBalance' => 6750000,
            'settlementClearingBalance' => 1250000,
            'currency' => 'KMF',
        ];
    }

    public static function platformRevenueBalances(): array
    {
        return [
            'revenueBalance' => 4325000,
            'withdrawalClearingBalance' => 900000,
            'currency' => 'KMF',
        ];
    }

    public static function platformLiquidityBalances(): array
    {
        return [
            'liquidityBalance' => 320000000,
            'fundingClearingBalance' => 8750000,
            'currency' => 'KMF',
        ];
    }

    public static function cards(array $filters = []): array
    {
        $rows = [
            ['id'=>'card01','nfcUid'=>'04A1B2C3D4E5F6','internalCardNumber'=>'LP-000001','walletId'=>'w-aaa1','customerId'=>'aaa1','cardType'=>'STANDARD','status'=>'ACTIVE','pinEnabled'=>true,'issuedByAgentId'=>'ag01','issuedAt'=>'2026-01-15T09:00:00Z','activatedAt'=>'2026-01-15T09:04:00Z','expiresAt'=>'2029-01-31','lastUsedAt'=>'2026-05-06T09:10:00Z','lastUsedTerminalId'=>'term01','replacedByCardId'=>null,'replacementOfCardId'=>null],
            ['id'=>'card02','nfcUid'=>'04F1E2D3C4B5A6','internalCardNumber'=>'LP-000002','walletId'=>'w-aaa2','customerId'=>'aaa2','cardType'=>'STANDARD','status'=>'BLOCKED','pinEnabled'=>true,'issuedByAgentId'=>'ag02','issuedAt'=>'2026-02-10T11:00:00Z','activatedAt'=>'2026-02-10T11:05:00Z','expiresAt'=>'2029-02-28','lastUsedAt'=>'2026-05-01T14:30:00Z','lastUsedTerminalId'=>'term02','replacedByCardId'=>null,'replacementOfCardId'=>null],
            ['id'=>'card03','nfcUid'=>'0499AABBCCDDEE','internalCardNumber'=>'LP-000003','walletId'=>'w-aaa4','customerId'=>'aaa4','cardType'=>'PREMIUM','status'=>'LOST','pinEnabled'=>false,'issuedByAgentId'=>'ag05','issuedAt'=>'2026-03-01T08:00:00Z','activatedAt'=>'2026-03-01T08:03:00Z','expiresAt'=>'2029-03-31','lastUsedAt'=>'2026-04-20T10:20:00Z','lastUsedTerminalId'=>'term01','replacedByCardId'=>'card04','replacementOfCardId'=>null],
            ['id'=>'card04','nfcUid'=>'04112233445566','internalCardNumber'=>'LP-000004','walletId'=>'w-aaa4','customerId'=>'aaa4','cardType'=>'PREMIUM','status'=>'ISSUED','pinEnabled'=>true,'issuedByAgentId'=>'ag05','issuedAt'=>'2026-04-25T13:00:00Z','activatedAt'=>null,'expiresAt'=>'2029-04-30','lastUsedAt'=>null,'lastUsedTerminalId'=>null,'replacedByCardId'=>null,'replacementOfCardId'=>'card03'],
            ['id'=>'card05','nfcUid'=>'04ABCDEF123456','internalCardNumber'=>'LP-000005','walletId'=>'w-aaa6','customerId'=>'aaa6','cardType'=>'CORPORATE','status'=>'STOLEN','pinEnabled'=>true,'issuedByAgentId'=>'ag01','issuedAt'=>'2026-02-20T10:00:00Z','activatedAt'=>'2026-02-20T10:08:00Z','expiresAt'=>'2029-02-28','lastUsedAt'=>'2026-04-12T17:00:00Z','lastUsedTerminalId'=>'term03','replacedByCardId'=>null,'replacementOfCardId'=>null],
        ];

        if (!empty($filters['customerId'])) {
            $rows = array_filter($rows, fn($r) => $r['customerId'] === $filters['customerId']);
        }

        if (!empty($filters['status'])) {
            $rows = array_filter($rows, fn($r) => $r['status'] === $filters['status']);
        }

        if (!empty($filters['cardType'])) {
            $rows = array_filter($rows, fn($r) => $r['cardType'] === $filters['cardType']);
        }

        return array_values($rows);
    }

    public static function card(string $id): ?array
    {
        return collect(static::cards())->firstWhere('id', $id);
    }

    public static function cardStock(array $filters = []): array
    {
        $rows = [
            ['id'=>'cs01','nfcUid'=>'04A1B2C3D4E5F6','internalCardNumber'=>'LP-000001','batchRef'=>'BATCH-2026-01','producedAt'=>'2026-01-05','importedAt'=>'2026-01-10T08:00:00Z','importedByUserId'=>'11111111-0000-0000-0000-000000000001','authKeyVersion'=>1,'status'=>'SOLD','assignedAgentId'=>'ag01','assignedAt'=>'2026-01-11T08:00:00Z','soldToCustomerId'=>'aaa1','soldAt'=>'2026-01-15T09:00:00Z','cardId'=>'card01'],
            ['id'=>'cs02','nfcUid'=>'04F1E2D3C4B5A6','internalCardNumber'=>'LP-000002','batchRef'=>'BATCH-2026-01','producedAt'=>'2026-01-05','importedAt'=>'2026-01-10T08:00:00Z','importedByUserId'=>'11111111-0000-0000-0000-000000000001','authKeyVersion'=>1,'status'=>'SOLD','assignedAgentId'=>'ag02','assignedAt'=>'2026-01-12T09:30:00Z','soldToCustomerId'=>'aaa2','soldAt'=>'2026-02-10T11:00:00Z','cardId'=>'card02'],
            ['id'=>'cs03','nfcUid'=>'0499AABBCCDDEE','internalCardNumber'=>'LP-000003','batchRef'=>'BATCH-2026-02','producedAt'=>'2026-02-01','importedAt'=>'2026-02-05T08:00:00Z','importedByUserId'=>'11111111-0000-0000-0000-000000000001','authKeyVersion'=>1,'status'=>'SOLD','assignedAgentId'=>'ag05','assignedAt'=>'2026-02-08T10:00:00Z','soldToCustomerId'=>'aaa4','soldAt'=>'2026-03-01T08:00:00Z','cardId'=>'card03'],
            ['id'=>'cs04','nfcUid'=>'04112233445566','internalCardNumber'=>'LP-000004','batchRef'=>'BATCH-2026-02','producedAt'=>'2026-02-01','importedAt'=>'2026-02-05T08:00:00Z','importedByUserId'=>'11111111-0000-0000-0000-000000000001','authKeyVersion'=>1,'status'=>'ASSIGNED_TO_AGENT','assignedAgentId'=>'ag05','assignedAt'=>'2026-04-25T13:00:00Z','soldToCustomerId'=>null,'soldAt'=>null,'cardId'=>'card04'],
            ['id'=>'cs05','nfcUid'=>'04ABCDEF123456','internalCardNumber'=>'LP-000005','batchRef'=>'BATCH-2026-02','producedAt'=>'2026-02-01','importedAt'=>'2026-02-05T08:00:00Z','importedByUserId'=>'11111111-0000-0000-0000-000000000001','authKeyVersion'=>1,'status'=>'SOLD','assignedAgentId'=>'ag01','assignedAt'=>'2026-02-10T10:00:00Z','soldToCustomerId'=>'aaa6','soldAt'=>'2026-02-20T10:00:00Z','cardId'=>'card05'],
            ['id'=>'cs06','nfcUid'=>'04010203040506','internalCardNumber'=>'LP-000006','batchRef'=>'BATCH-2026-03','producedAt'=>'2026-03-05','importedAt'=>'2026-03-08T08:00:00Z','importedByUserId'=>'11111111-0000-0000-0000-000000000001','authKeyVersion'=>2,'status'=>'IN_WAREHOUSE','assignedAgentId'=>null,'assignedAt'=>null,'soldToCustomerId'=>null,'soldAt'=>null,'cardId'=>null],
            ['id'=>'cs07','nfcUid'=>'040A0B0C0D0E0F','internalCardNumber'=>'LP-000007','batchRef'=>'BATCH-2026-03','producedAt'=>'2026-03-05','importedAt'=>'2026-03-08T08:00:00Z','importedByUserId'=>'11111111-0000-0000-0000-000000000001','authKeyVersion'=>2,'status'=>'IN_WAREHOUSE','assignedAgentId'=>null,'assignedAt'=>null,'soldToCustomerId'=>null,'soldAt'=>null,'cardId'=>null],
            ['id'=>'cs08','nfcUid'=>'04FFEEDDCCBBAA','internalCardNumber'=>'LP-000008','batchRef'=>'BATCH-2026-03','producedAt'=>'2026-03-05','importedAt'=>'2026-03-08T08:00:00Z','importedByUserId'=>'11111111-0000-0000-0000-000000000001','authKeyVersion'=>2,'status'=>'SPOILED','assignedAgentId'=>null,'assignedAt'=>null,'soldToCustomerId'=>null,'soldAt'=>null,'cardId'=>null],
        ];

        if (!empty($filters['status'])) {
            $rows = array_filter($rows, fn($r) => $r['status'] === $filters['status']);
        }

        if (!empty($filters['agentId'])) {
            $rows = array_filter($rows, fn($r) => $r['assignedAgentId'] === $filters['agentId']);
        }

        if (!empty($filters['batchRef'])) {
            $rows = array_filter($rows, fn($r) => str_contains(strtolower($r['batchRef']), strtolower($filters['batchRef'])));
        }

        return array_values($rows);
    }

    public static function cardStockItem(string $id): ?array
    {
        return collect(static::cardStock())->firstWhere('id', $id);
    }

    public static function terminals(array $filters = []): array
    {
        $rows = [
            ['id'=>'term01','serialNumber'=>'LIPA-POS-0001','deviceModel'=>'Sunmi P2 Pro','androidVersion'=>'11','appVersion'=>'2.4.0','merchantId'=>'mc01','status'=>'ACTIVE','apiKeyIssuedAt'=>'2026-01-08T09:00:00Z','apiKeyExpiresAt'=>'2027-01-08T09:00:00Z','lastAuthAt'=>'2026-05-06T10:30:00Z','authFailedCount'=>0,'registeredAt'=>'2026-01-08T08:30:00Z'],
            ['id'=>'term02','serialNumber'=>'LIPA-POS-0002','deviceModel'=>'PAX A920','androidVersion'=>'10','appVersion'=>'2.3.2','merchantId'=>'mc02','status'=>'SUSPENDED','apiKeyIssuedAt'=>'2026-02-01T12:00:00Z','apiKeyExpiresAt'=>'2027-02-01T12:00:00Z','lastAuthAt'=>'2026-04-28T15:00:00Z','authFailedCount'=>4,'registeredAt'=>'2026-02-01T11:45:00Z'],
            ['id'=>'term03','serialNumber'=>'LIPA-POS-0003','deviceModel'=>'Sunmi V2s','androidVersion'=>'12','appVersion'=>'2.4.0','merchantId'=>'mc01','status'=>'REGISTERED','apiKeyIssuedAt'=>null,'apiKeyExpiresAt'=>null,'lastAuthAt'=>null,'authFailedCount'=>0,'registeredAt'=>'2026-04-20T14:00:00Z'],
            ['id'=>'term04','serialNumber'=>'LIPA-POS-0004','deviceModel'=>'PAX A50','androidVersion'=>'11','appVersion'=>'2.2.9','merchantId'=>'mc05','status'=>'REVOKED','apiKeyIssuedAt'=>'2025-11-20T09:00:00Z','apiKeyExpiresAt'=>'2026-11-20T09:00:00Z','lastAuthAt'=>'2026-03-15T08:10:00Z','authFailedCount'=>12,'registeredAt'=>'2025-11-20T08:30:00Z'],
        ];

        if (!empty($filters['merchantId'])) {
            $rows = array_filter($rows, fn($r) => $r['merchantId'] === $filters['merchantId']);
        }

        if (!empty($filters['status'])) {
            $rows = array_filter($rows, fn($r) => $r['status'] === $filters['status']);
        }

        return array_values($rows);
    }

    public static function terminal(string $id): ?array
    {
        return collect(static::terminals())->firstWhere('id', $id);
    }

    public static function serviceProviders(array $filters = []): array
    {
        $rows = [
            ['id'=>'sp01','name'=>'MWE Electricity Gateway','code'=>'MWE','type'=>'EXTERNAL_API','status'=>'ACTIVE','baseUrl'=>'https://api.mwe.km/v1','timeoutMillis'=>10000,'maxRetries'=>2,'retryBackoffMillis'=>500,'sandbox'=>false,'supportsReferenceValidation'=>true,'createdAt'=>'2026-01-15T08:00:00Z','updatedAt'=>'2026-04-28T10:20:00Z'],
            ['id'=>'sp02','name'=>'Comores Telecom Services','code'=>'COMTEL','type'=>'EXTERNAL_API','status'=>'ACTIVE','baseUrl'=>'https://partners.comtel.km/billing','timeoutMillis'=>15000,'maxRetries'=>3,'retryBackoffMillis'=>750,'sandbox'=>false,'supportsReferenceValidation'=>true,'createdAt'=>'2026-02-01T09:30:00Z','updatedAt'=>'2026-05-02T11:10:00Z'],
            ['id'=>'sp03','name'=>'Lipa Internal Airtime','code'=>'LIPA_AIRTIME','type'=>'INTERNAL','status'=>'ACTIVE','baseUrl'=>null,'timeoutMillis'=>5000,'maxRetries'=>1,'retryBackoffMillis'=>250,'sandbox'=>false,'supportsReferenceValidation'=>false,'createdAt'=>'2026-03-10T07:45:00Z','updatedAt'=>'2026-03-10T07:45:00Z'],
            ['id'=>'sp04','name'=>'Sandbox Water Utility','code'=>'WATER_SANDBOX','type'=>'EXTERNAL_API','status'=>'INACTIVE','baseUrl'=>'https://sandbox.water.km/api','timeoutMillis'=>20000,'maxRetries'=>2,'retryBackoffMillis'=>1000,'sandbox'=>true,'supportsReferenceValidation'=>false,'createdAt'=>'2026-04-05T12:00:00Z','updatedAt'=>'2026-04-25T14:35:00Z'],
        ];

        if (!empty($filters['type'])) {
            $rows = array_filter($rows, fn($r) => $r['type'] === $filters['type']);
        }

        if (!empty($filters['status'])) {
            $rows = array_filter($rows, fn($r) => $r['status'] === $filters['status']);
        }

        return array_values($rows);
    }

    public static function serviceProvider(string $id): ?array
    {
        return collect(static::serviceProviders())->firstWhere('id', $id);
    }

    public static function billServices(string $providerId = '', array $filters = []): array
    {
        $rows = [
            ['id'=>'bs01','name'=>'MWE Prepaid Electricity','code'=>'MWE_PREPAID','providerId'=>'sp01','category'=>'ELECTRICITY','status'=>'ACTIVE','minAmount'=>1000,'maxAmount'=>500000,'createdAt'=>'2026-01-16T08:00:00Z'],
            ['id'=>'bs02','name'=>'MWE Postpaid Electricity','code'=>'MWE_POSTPAID','providerId'=>'sp01','category'=>'ELECTRICITY','status'=>'ACTIVE','minAmount'=>2500,'maxAmount'=>750000,'createdAt'=>'2026-01-18T08:00:00Z'],
            ['id'=>'bs03','name'=>'Comores Telecom Airtime','code'=>'COMTEL_AIRTIME','providerId'=>'sp02','category'=>'AIRTIME','status'=>'ACTIVE','minAmount'=>500,'maxAmount'=>100000,'createdAt'=>'2026-02-03T10:00:00Z'],
            ['id'=>'bs04','name'=>'Comores Telecom Internet','code'=>'COMTEL_INTERNET','providerId'=>'sp02','category'=>'INTERNET','status'=>'INACTIVE','minAmount'=>5000,'maxAmount'=>250000,'createdAt'=>'2026-02-10T10:30:00Z'],
            ['id'=>'bs05','name'=>'Sandbox Water Bill','code'=>'WATER_BILL','providerId'=>'sp04','category'=>'WATER','status'=>'INACTIVE','minAmount'=>1000,'maxAmount'=>300000,'createdAt'=>'2026-04-06T12:30:00Z'],
            ['id'=>'bs06','name'=>'Lipa Airtime Bundle','code'=>'LIPA_AIRTIME_BUNDLE','providerId'=>'sp03','category'=>'AIRTIME','status'=>'ACTIVE','minAmount'=>500,'maxAmount'=>50000,'createdAt'=>'2026-03-11T08:00:00Z'],
        ];

        if ($providerId !== '') {
            $rows = array_filter($rows, fn($r) => $r['providerId'] === $providerId);
        }

        if (!empty($filters['category'])) {
            $rows = array_filter($rows, fn($r) => $r['category'] === $filters['category']);
        }

        if (!empty($filters['status'])) {
            $rows = array_filter($rows, fn($r) => $r['status'] === $filters['status']);
        }

        return array_values($rows);
    }

    public static function billService(string $providerId, string $id): ?array
    {
        return collect(static::billServices($providerId))->firstWhere('id', $id);
    }

    public static function reconciliationIncidents(array $filters = []): array
    {
        $rows = [
            ['id'=>'ri01','runId'=>'rr01','incidentType'=>'DOUBLE_ENTRY_MISMATCH','status'=>'OPEN','description'=>'Ledger debit and credit totals differ for settlement batch csr02.','discrepancyAmount'=>125000,'suspenseEntryRef'=>null,'investigatedBy'=>null,'investigatedAt'=>null,'resolvedBy'=>null,'resolvedAt'=>null,'resolutionNote'=>null,'closedBy'=>null,'closedAt'=>null,'closureNote'=>null,'openedAt'=>'2026-05-06T02:18:00Z'],
            ['id'=>'ri02','runId'=>'rr01','incidentType'=>'BALANCE_MISMATCH','status'=>'UNDER_INVESTIGATION','description'=>'Wallet balance snapshot does not match ledger balance for merchant mc02.','discrepancyAmount'=>85000,'suspenseEntryRef'=>'SUSP-20260506-002','investigatedBy'=>'11111111-0000-0000-0000-000000000003','investigatedAt'=>'2026-05-06T08:20:00Z','resolvedBy'=>null,'resolvedAt'=>null,'resolutionNote'=>null,'closedBy'=>null,'closedAt'=>null,'closureNote'=>null,'openedAt'=>'2026-05-06T02:20:00Z'],
            ['id'=>'ri03','runId'=>'rr03','incidentType'=>'FLOAT_IDENTITY_BREACH','status'=>'RESOLVED','description'=>'Provider payable float was above settlement clearing by a small residual amount.','discrepancyAmount'=>15000,'suspenseEntryRef'=>'SUSP-20260505-001','investigatedBy'=>'11111111-0000-0000-0000-000000000003','investigatedAt'=>'2026-05-05T08:45:00Z','resolvedBy'=>'11111111-0000-0000-0000-000000000003','resolvedAt'=>'2026-05-05T09:30:00Z','resolutionNote'=>'Residual posted to suspense for approval-backed adjustment.','closedBy'=>null,'closedAt'=>null,'closureNote'=>null,'openedAt'=>'2026-05-05T02:16:00Z'],
            ['id'=>'ri04','runId'=>'rr04','incidentType'=>'BALANCE_MISMATCH','status'=>'CLOSED','description'=>'Agent commission wallet lagged one posting during nightly close.','discrepancyAmount'=>22000,'suspenseEntryRef'=>null,'investigatedBy'=>'11111111-0000-0000-0000-000000000002','investigatedAt'=>'2026-05-04T08:10:00Z','resolvedBy'=>'11111111-0000-0000-0000-000000000002','resolvedAt'=>'2026-05-04T08:40:00Z','resolutionNote'=>'Delayed ledger posting confirmed and replayed.','closedBy'=>'11111111-0000-0000-0000-000000000003','closedAt'=>'2026-05-04T09:15:00Z','closureNote'=>'Evidence checked against transaction trace.','openedAt'=>'2026-05-04T02:12:00Z'],
        ];

        if (!empty($filters['status'])) {
            $rows = array_filter($rows, fn($r) => $r['status'] === $filters['status']);
        }

        return array_values($rows);
    }

    public static function reconciliationIncident(string $id): ?array
    {
        return collect(static::reconciliationIncidents())->firstWhere('id', $id);
    }

    public static function reconciliationRuns(array $filters = []): array
    {
        $rows = [
            ['id'=>'rr01','startedAt'=>'2026-05-06T02:00:00Z','completedAt'=>'2026-05-06T02:22:00Z','windowFrom'=>'2026-05-05T00:00:00Z','windowTo'=>'2026-05-05T23:59:59Z','status'=>'MISMATCH','transactionsChecked'=>2841,'walletsChecked'=>18432,'mismatchCount'=>2,'details'=>['source'=>'daily-close','ledgerDifference'=>210000,'walletDifference'=>85000]],
            ['id'=>'rr02','startedAt'=>'2026-05-06T10:00:00Z','completedAt'=>'2026-05-06T10:07:00Z','windowFrom'=>'2026-05-06T00:00:00Z','windowTo'=>'2026-05-06T09:59:59Z','status'=>'OK','transactionsChecked'=>932,'walletsChecked'=>18440,'mismatchCount'=>0,'details'=>['source'=>'intraday','ledgerDifference'=>0,'walletDifference'=>0]],
            ['id'=>'rr03','startedAt'=>'2026-05-05T02:00:00Z','completedAt'=>'2026-05-05T02:18:00Z','windowFrom'=>'2026-05-04T00:00:00Z','windowTo'=>'2026-05-04T23:59:59Z','status'=>'MISMATCH','transactionsChecked'=>2610,'walletsChecked'=>18390,'mismatchCount'=>1,'details'=>['source'=>'daily-close','ledgerDifference'=>15000,'walletDifference'=>0]],
            ['id'=>'rr04','startedAt'=>'2026-05-04T02:00:00Z','completedAt'=>'2026-05-04T02:14:00Z','windowFrom'=>'2026-05-03T00:00:00Z','windowTo'=>'2026-05-03T23:59:59Z','status'=>'MISMATCH','transactionsChecked'=>2502,'walletsChecked'=>18320,'mismatchCount'=>1,'details'=>['source'=>'daily-close','ledgerDifference'=>22000,'walletDifference'=>22000]],
        ];

        if (!empty($filters['status'])) {
            $rows = array_filter($rows, fn($r) => $r['status'] === $filters['status']);
        }

        return array_values($rows);
    }

    public static function reconciliationRun(string $id): ?array
    {
        return collect(static::reconciliationRuns())->firstWhere('id', $id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Wallets  (spec §5.6, WalletResponse §7.2)
    // ──────────────────────────────────────────────────────────────────────────
    public static function wallets(array $filters = []): array
    {
        $rows = [
            ['id'=>'w-aaa1','ownerType'=>'CUSTOMER','ownerId'=>'aaa1','ownerLabel'=>'Fatima Moussa','ownerRef'=>'CUST-0001','currency'=>'KMF','status'=>'ACTIVE','availableBalance'=>125400,'frozenBalance'=>0,'version'=>42,'createdAt'=>'2025-01-10T08:00:00Z','updatedAt'=>'2026-05-06T08:23:05Z'],
            ['id'=>'w-aaa2','ownerType'=>'CUSTOMER','ownerId'=>'aaa2','ownerLabel'=>'Omar Ali Hassan','ownerRef'=>'CUST-0002','currency'=>'KMF','status'=>'ACTIVE','availableBalance'=>87300,'frozenBalance'=>5000,'version'=>18,'createdAt'=>'2025-02-14T09:30:00Z','updatedAt'=>'2026-05-06T09:45:00Z'],
            ['id'=>'w-aaa5','ownerType'=>'CUSTOMER','ownerId'=>'aaa5','ownerLabel'=>'Mariam Soilihi','ownerRef'=>'CUST-0005','currency'=>'KMF','status'=>'SUSPENDED','availableBalance'=>2100,'frozenBalance'=>0,'version'=>9,'createdAt'=>'2025-04-08T11:00:00Z','updatedAt'=>'2026-04-15T14:00:00Z'],
            ['id'=>'w-aaa7','ownerType'=>'CUSTOMER','ownerId'=>'aaa7','ownerLabel'=>'Halima Youssouf','ownerRef'=>'CUST-0007','currency'=>'KMF','status'=>'FROZEN','availableBalance'=>0,'frozenBalance'=>34800,'version'=>14,'createdAt'=>'2025-06-15T16:30:00Z','updatedAt'=>'2026-05-04T15:00:00Z'],
            ['id'=>'w-aaa8','ownerType'=>'CUSTOMER','ownerId'=>'aaa8','ownerLabel'=>'Madi Bacar','ownerRef'=>'CUST-0008','currency'=>'KMF','status'=>'CLOSED','availableBalance'=>0,'frozenBalance'=>0,'version'=>21,'createdAt'=>'2024-11-20T09:00:00Z','updatedAt'=>'2025-12-30T10:00:00Z'],
            ['id'=>'w-ag01','ownerType'=>'AGENT','ownerId'=>'ag01','ownerLabel'=>'Rachid Oumouri','ownerRef'=>'AGT-0001','currency'=>'KMF','status'=>'ACTIVE','availableBalance'=>1240500,'frozenBalance'=>0,'version'=>87,'createdAt'=>'2025-01-05T08:00:00Z','updatedAt'=>'2026-05-06T11:45:01Z'],
            ['id'=>'w-ag04','ownerType'=>'AGENT','ownerId'=>'ag04','ownerLabel'=>'Soula Badrouddine','ownerRef'=>'AGT-0004','currency'=>'KMF','status'=>'SUSPENDED','availableBalance'=>320000,'frozenBalance'=>50000,'version'=>33,'createdAt'=>'2025-02-01T11:00:00Z','updatedAt'=>'2026-05-05T16:00:00Z'],
            ['id'=>'w-ag05','ownerType'=>'AGENT','ownerId'=>'ag05','ownerLabel'=>'Noura Said Ali','ownerRef'=>'AGT-0005','currency'=>'KMF','status'=>'ACTIVE','availableBalance'=>2150000,'frozenBalance'=>0,'version'=>112,'createdAt'=>'2025-01-20T12:00:00Z','updatedAt'=>'2026-05-06T08:23:05Z'],
            ['id'=>'w-mc01','ownerType'=>'MERCHANT','ownerId'=>'mc01','ownerLabel'=>'Comoros Fresh Market','ownerRef'=>'MRC-0001','currency'=>'KMF','status'=>'ACTIVE','availableBalance'=>5430000,'frozenBalance'=>0,'version'=>201,'createdAt'=>'2025-01-08T08:00:00Z','updatedAt'=>'2026-05-06T10:30:03Z'],
            ['id'=>'w-mc02','ownerType'=>'MERCHANT','ownerId'=>'mc02','ownerLabel'=>'Telecom Services KM','ownerRef'=>'MRC-0002','currency'=>'KMF','status'=>'ACTIVE','availableBalance'=>9870000,'frozenBalance'=>120000,'version'=>156,'createdAt'=>'2025-01-12T09:00:00Z','updatedAt'=>'2026-05-06T09:10:02Z'],
            ['id'=>'w-mc04','ownerType'=>'MERCHANT','ownerId'=>'mc04','ownerLabel'=>'NGO Espoir Comores','ownerRef'=>'MRC-0004','currency'=>'KMF','status'=>'SUSPENDED','availableBalance'=>15000,'frozenBalance'=>0,'version'=>12,'createdAt'=>'2025-02-05T11:00:00Z','updatedAt'=>'2026-04-20T08:00:00Z'],
        ];

        if (!empty($filters['ownerType'])) {
            $rows = array_filter($rows, fn($r) => $r['ownerType'] === $filters['ownerType']);
        }

        if (!empty($filters['status'])) {
            $rows = array_filter($rows, fn($r) => $r['status'] === $filters['status']);
        }

        if (!empty($filters['search'])) {
            $s = strtolower($filters['search']);
            $rows = array_filter($rows, fn($r) =>
                str_contains(strtolower($r['id']), $s) ||
                str_contains(strtolower($r['ownerLabel']), $s) ||
                str_contains(strtolower($r['ownerRef']), $s) ||
                str_contains(strtolower($r['ownerId']), $s)
            );
        }

        return array_values($rows);
    }

    public static function walletById(string $id): ?array
    {
        return collect(static::wallets())->firstWhere('id', $id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Regulatory Reports  (spec §5.17, §7.8)
    // ──────────────────────────────────────────────────────────────────────────
    public static function transactionSummaryReport(array $filters = []): array
    {
        $from = $filters['from'] ?? '2026-04-01T00:00:00Z';
        $to = $filters['to'] ?? '2026-05-06T23:59:59Z';
        $groupBy = $filters['groupBy'] ?? 'MONTH';

        $lines = [
            ['type'=>'CASH_IN','period'=>'2026-04-01T00:00:00Z','count'=>3120,'totalAmountKmf'=>108_500_000,'totalFeesKmf'=>1_085_000,'totalCommissionsKmf'=>540_000],
            ['type'=>'CASH_IN','period'=>'2026-05-01T00:00:00Z','count'=>1240,'totalAmountKmf'=>42_100_000,'totalFeesKmf'=>421_000,'totalCommissionsKmf'=>210_000],
            ['type'=>'CASH_OUT','period'=>'2026-04-01T00:00:00Z','count'=>980,'totalAmountKmf'=>52_400_000,'totalFeesKmf'=>786_000,'totalCommissionsKmf'=>390_000],
            ['type'=>'CASH_OUT','period'=>'2026-05-01T00:00:00Z','count'=>380,'totalAmountKmf'=>18_200_000,'totalFeesKmf'=>273_000,'totalCommissionsKmf'=>136_000],
            ['type'=>'PAYMENT','period'=>'2026-04-01T00:00:00Z','count'=>2410,'totalAmountKmf'=>44_300_000,'totalFeesKmf'=>443_000,'totalCommissionsKmf'=>0],
            ['type'=>'PAYMENT','period'=>'2026-05-01T00:00:00Z','count'=>820,'totalAmountKmf'=>15_600_000,'totalFeesKmf'=>156_000,'totalCommissionsKmf'=>0],
            ['type'=>'P2P_TRANSFER','period'=>'2026-04-01T00:00:00Z','count'=>720,'totalAmountKmf'=>24_700_000,'totalFeesKmf'=>0,'totalCommissionsKmf'=>0],
            ['type'=>'P2P_TRANSFER','period'=>'2026-05-01T00:00:00Z','count'=>280,'totalAmountKmf'=>9_300_000,'totalFeesKmf'=>0,'totalCommissionsKmf'=>0],
            ['type'=>'SERVICE_PAYMENT','period'=>'2026-04-01T00:00:00Z','count'=>340,'totalAmountKmf'=>6_750_000,'totalFeesKmf'=>27_200,'totalCommissionsKmf'=>0],
            ['type'=>'SERVICE_PAYMENT','period'=>'2026-05-01T00:00:00Z','count'=>121,'totalAmountKmf'=>2_250_000,'totalFeesKmf'=>9_680,'totalCommissionsKmf'=>0],
        ];

        if (!empty($filters['type'])) {
            $lines = array_values(array_filter($lines, fn($l) => $l['type'] === $filters['type']));
        }

        return ['from' => $from, 'to' => $to, 'groupBy' => $groupBy, 'lines' => $lines];
    }

    public static function kycSummaryReport(): array
    {
        return [
            'lines' => [
                ['actorType'=>'CUSTOMER','kycLevel'=>'KYC_NONE','status'=>'PENDING_KYC','count'=>1320],
                ['actorType'=>'CUSTOMER','kycLevel'=>'KYC_BASIC','status'=>'ACTIVE','count'=>5210],
                ['actorType'=>'CUSTOMER','kycLevel'=>'KYC_VERIFIED','status'=>'ACTIVE','count'=>10480],
                ['actorType'=>'CUSTOMER','kycLevel'=>'KYC_ENHANCED','status'=>'ACTIVE','count'=>1422],
                ['actorType'=>'AGENT','kycLevel'=>'KYC_VERIFIED','status'=>'ACTIVE','count'=>118],
                ['actorType'=>'AGENT','kycLevel'=>'KYC_BASIC','status'=>'PENDING_KYC','count'=>14],
                ['actorType'=>'AGENT','kycLevel'=>'KYC_VERIFIED','status'=>'SUSPENDED','count'=>8],
                ['actorType'=>'MERCHANT','kycLevel'=>'KYC_VERIFIED','status'=>'ACTIVE','count'=>61],
                ['actorType'=>'MERCHANT','kycLevel'=>'KYC_ENHANCED','status'=>'ACTIVE','count'=>32],
                ['actorType'=>'MERCHANT','kycLevel'=>'KYC_BASIC','status'=>'PENDING_KYC','count'=>9],
            ],
        ];
    }

    public static function amlLargeTransactions(array $filters = []): array
    {
        $threshold = (int) ($filters['thresholdKmf'] ?? 500_000);

        $rows = [
            ['id'=>'tx08','type'=>'AGENT_FUND_IN','status'=>'COMPLETED','requestedAmountKmf'=>500000,'feeAmountKmf'=>0,'sourceWalletId'=>'w-system-liquidity','destinationWalletId'=>'w-ag05','initiatorType'=>'BACKOFFICE_USER','initiatorId'=>'11111111-0000-0000-0000-000000000001','channelType'=>'BACKOFFICE_UI','createdAt'=>'2026-05-05T14:00:00Z','completedAt'=>'2026-05-05T14:00:05Z'],
            ['id'=>'tx-aml-02','type'=>'CASH_OUT','status'=>'COMPLETED','requestedAmountKmf'=>750000,'feeAmountKmf'=>11250,'sourceWalletId'=>'w-mc02','destinationWalletId'=>'w-ag01','initiatorType'=>'MERCHANT','initiatorId'=>'mc02','channelType'=>'AGENT_CHANNEL','createdAt'=>'2026-05-04T11:30:00Z','completedAt'=>'2026-05-04T11:30:08Z'],
            ['id'=>'tx-aml-03','type'=>'CASH_IN','status'=>'COMPLETED','requestedAmountKmf'=>620000,'feeAmountKmf'=>5000,'sourceWalletId'=>'w-ag05','destinationWalletId'=>'w-mc01','initiatorType'=>'AGENT','initiatorId'=>'ag05','channelType'=>'AGENT_CHANNEL','createdAt'=>'2026-05-03T15:20:00Z','completedAt'=>'2026-05-03T15:20:04Z'],
            ['id'=>'tx-aml-04','type'=>'P2P_TRANSFER','status'=>'COMPLETED','requestedAmountKmf'=>510000,'feeAmountKmf'=>0,'sourceWalletId'=>'w-aaa1','destinationWalletId'=>'w-aaa4','initiatorType'=>'CUSTOMER','initiatorId'=>'aaa1','channelType'=>'MOBILE_APP','createdAt'=>'2026-05-02T10:05:00Z','completedAt'=>'2026-05-02T10:05:02Z'],
            ['id'=>'ap05','type'=>'CASH_OUT','status'=>'PENDING_APPROVAL','requestedAmountKmf'=>500000,'feeAmountKmf'=>7500,'sourceWalletId'=>'w-mc01','destinationWalletId'=>'w-ag01','initiatorType'=>'MERCHANT','initiatorId'=>'mc01','channelType'=>'AGENT_CHANNEL','createdAt'=>'2026-05-06T14:00:00Z','completedAt'=>null],
        ];

        $rows = array_filter($rows, fn($r) => $r['requestedAmountKmf'] >= $threshold);

        return array_values($rows);
    }

    public static function floatReport(): array
    {
        return [
            'generatedAt' => '2026-05-07T06:00:00Z',
            'customerTotalBalanceKmf' => 234_500_000,
            'merchantTotalBalanceKmf' => 178_900_000,
            'agentTotalBalanceKmf' => 65_400_000,
            'actorTotalBalanceKmf' => 478_800_000,
            'systemFloatBalanceKmf' => 480_000_000,
            'systemLiquidityBalanceKmf' => 320_000_000,
            'systemRevenueBalanceKmf' => 4_325_000,
            'systemCommissionsBalanceKmf' => 615_000,
            'systemSuspenseBalanceKmf' => 100_000,
            'ledgerTotalDebitKmf' => 1_284_500_000,
            'ledgerTotalCreditKmf' => 1_284_500_000,
            'doubleEntryIntegrityOk' => true,
            'floatDiscrepancy' => 1_200_000,
        ];
    }

    public static function actorSummaryReport(): array
    {
        return [
            'lines' => [
                ['actorType'=>'CUSTOMER','status'=>'ACTIVE','count'=>17112],
                ['actorType'=>'CUSTOMER','status'=>'PENDING_KYC','count'=>1320],
                ['actorType'=>'CUSTOMER','status'=>'SUSPENDED','count'=>62],
                ['actorType'=>'CUSTOMER','status'=>'FROZEN','count'=>8],
                ['actorType'=>'CUSTOMER','status'=>'CLOSED','count'=>21],
                ['actorType'=>'AGENT','status'=>'ACTIVE','count'=>118],
                ['actorType'=>'AGENT','status'=>'PENDING_KYC','count'=>14],
                ['actorType'=>'AGENT','status'=>'SUSPENDED','count'=>8],
                ['actorType'=>'MERCHANT','status'=>'ACTIVE','count'=>93],
                ['actorType'=>'MERCHANT','status'=>'PENDING_KYC','count'=>9],
                ['actorType'=>'MERCHANT','status'=>'SUSPENDED','count'=>4],
            ],
        ];
    }

    public static function reportExports(array $filters = []): array
    {
        $rows = [
            ['id'=>'rx01','reportType'=>'TRANSACTION_SUMMARY','periodFrom'=>'2026-04-01T00:00:00Z','periodTo'=>'2026-04-30T23:59:59Z','generatedByUserId'=>'11111111-0000-0000-0000-000000000003','generatedAt'=>'2026-05-01T08:00:00Z','recordCount'=>10],
            ['id'=>'rx02','reportType'=>'KYC_SUMMARY','periodFrom'=>null,'periodTo'=>null,'generatedByUserId'=>'11111111-0000-0000-0000-000000000003','generatedAt'=>'2026-05-01T08:05:00Z','recordCount'=>10],
            ['id'=>'rx03','reportType'=>'AML_LARGE_TRANSACTIONS','periodFrom'=>'2026-04-01T00:00:00Z','periodTo'=>'2026-04-30T23:59:59Z','generatedByUserId'=>'11111111-0000-0000-0000-000000000001','generatedAt'=>'2026-05-02T09:30:00Z','recordCount'=>5],
            ['id'=>'rx04','reportType'=>'FLOAT_REPORT','periodFrom'=>null,'periodTo'=>null,'generatedByUserId'=>'11111111-0000-0000-0000-000000000003','generatedAt'=>'2026-05-05T07:00:00Z','recordCount'=>1],
            ['id'=>'rx05','reportType'=>'ACTOR_SUMMARY','periodFrom'=>null,'periodTo'=>null,'generatedByUserId'=>'11111111-0000-0000-0000-000000000003','generatedAt'=>'2026-05-06T07:30:00Z','recordCount'=>11],
        ];

        if (!empty($filters['reportType'])) {
            $rows = array_filter($rows, fn($r) => $r['reportType'] === $filters['reportType']);
        }

        return array_values($rows);
    }

    public static function reportExport(string $id): ?array
    {
        return collect(static::reportExports())->firstWhere('id', $id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Customer KYC documents  (spec §5.3a, KycDocumentResponse §7.2)
    // NOTE: storageRef is intentionally absent — file bytes are only reachable
    // via the dedicated /file endpoint (spec §5.3a "Document storage and download").
    // ──────────────────────────────────────────────────────────────────────────
    private static array $kycDocumentOverrides = [];

    public static function kycDocuments(): array
    {
        $base = [
            ['id'=>'kyc-aaa3-01','ownerActorType'=>'CUSTOMER','ownerActorId'=>'aaa3','documentType'=>'NATIONAL_ID','contentHash'=>'2b1f9c8e0d4a5b6c7d8e9f0a1b2c3d4e5f607182930a4b5c6d7e8f9001122334','contentType'=>'image/png','uploadedByActorType'=>'CUSTOMER','uploadedByActorId'=>'aaa3','uploadedAt'=>'2026-05-08T10:15:00Z','status'=>'PENDING_REVIEW'],
            ['id'=>'kyc-aaa3-02','ownerActorType'=>'CUSTOMER','ownerActorId'=>'aaa3','documentType'=>'PROOF_OF_ADDRESS','contentHash'=>'b78fa3d7d0a52f5d8e1c2b3a4f5e6d7c8b9a0102030405060708090a0b0c0d0e','contentType'=>'application/pdf','uploadedByActorType'=>'CUSTOMER','uploadedByActorId'=>'aaa3','uploadedAt'=>'2026-05-08T10:17:00Z','status'=>'PENDING_REVIEW'],
            ['id'=>'kyc-aaa7-01','ownerActorType'=>'CUSTOMER','ownerActorId'=>'aaa7','documentType'=>'PASSPORT','contentHash'=>'f1e2d3c4b5a6978869504a3b2c1d0e9f8e7d6c5b4a39281706f5e4d3c2b1a009','contentType'=>'image/png','uploadedByActorType'=>'CUSTOMER','uploadedByActorId'=>'aaa7','uploadedAt'=>'2026-05-09T14:20:00Z','status'=>'PENDING_REVIEW'],
            ['id'=>'kyc-aaa2-01','ownerActorType'=>'CUSTOMER','ownerActorId'=>'aaa2','documentType'=>'NATIONAL_ID','contentHash'=>'a1b2c3d4e5f60718293a4b5c6d7e8f900112233445566778899aabbccddeeff0','contentType'=>'image/png','uploadedByActorType'=>'CUSTOMER','uploadedByActorId'=>'aaa2','uploadedAt'=>'2026-04-12T09:00:00Z','status'=>'ACCEPTED','reviewedByUserId'=>'11111111-0000-0000-0000-000000000001','reviewedAt'=>'2026-04-13T11:30:00Z'],
            ['id'=>'kyc-aaa2-02','ownerActorType'=>'CUSTOMER','ownerActorId'=>'aaa2','documentType'=>'PROOF_OF_ADDRESS','contentHash'=>'b2c3d4e5f60718293a4b5c6d7e8f900112233445566778899aabbccddeeff0a1','contentType'=>'application/pdf','uploadedByActorType'=>'CUSTOMER','uploadedByActorId'=>'aaa2','uploadedAt'=>'2026-04-12T09:05:00Z','status'=>'REJECTED','reviewedByUserId'=>'11111111-0000-0000-0000-000000000001','reviewedAt'=>'2026-04-13T11:35:00Z','rejectionReason'=>'Utility bill is older than 3 months; please resubmit a recent one.'],
            ['id'=>'kyc-aaa1-01','ownerActorType'=>'CUSTOMER','ownerActorId'=>'aaa1','documentType'=>'NATIONAL_ID','contentHash'=>'c3d4e5f60718293a4b5c6d7e8f900112233445566778899aabbccddeeff0a1b2','contentType'=>'image/png','uploadedByActorType'=>'CUSTOMER','uploadedByActorId'=>'aaa1','uploadedAt'=>'2026-01-08T08:00:00Z','status'=>'ACCEPTED','reviewedByUserId'=>'11111111-0000-0000-0000-000000000001','reviewedAt'=>'2026-01-09T10:00:00Z'],
        ];

        $merged = [];
        foreach ($base as $doc) {
            $id = $doc['id'];
            $merged[$id] = isset(static::$kycDocumentOverrides[$id])
                ? array_replace($doc, static::$kycDocumentOverrides[$id])
                : $doc;
        }

        return array_values($merged);
    }

    public static function customerKycDocuments(string $customerId): array
    {
        return array_values(array_filter(
            static::kycDocuments(),
            fn (array $d): bool => ($d['ownerActorId'] ?? null) === $customerId,
        ));
    }

    public static function kycDocument(string $documentId): ?array
    {
        return collect(static::kycDocuments())->firstWhere('id', $documentId);
    }

    public static function recordKycDocumentDecision(string $documentId, string $status, ?string $reason = null, ?string $reviewerId = null): ?array
    {
        $doc = static::kycDocument($documentId);
        if ($doc === null) {
            return null;
        }

        $override = [
            'status' => $status,
            'reviewedByUserId' => $reviewerId ?? (string) session('bo_user.id', '11111111-0000-0000-0000-000000000001'),
            'reviewedAt' => now()->toIso8601String(),
        ];

        if ($status === 'REJECTED') {
            $override['rejectionReason'] = $reason ?? '';
        } else {
            // ACCEPTED rows never carry a rejection reason (chk_kyc_reviewed_consistency).
            $override['rejectionReason'] = null;
        }

        static::$kycDocumentOverrides[$documentId] = array_replace(
            static::$kycDocumentOverrides[$documentId] ?? [],
            $override,
        );

        return array_replace($doc, $override);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Agent & Merchant KYC/KYB documents  (spec §5.3b, KycDocumentResponse §7.2)
    // Uploaded by the BO itself: uploadedByActorType = BACKOFFICE_USER.
    // storageRef is intentionally absent — bytes only via owner-scoped /file endpoint.
    // ──────────────────────────────────────────────────────────────────────────
    private static array $actorKycDocumentOverrides = [];

    /** @var array<int, array<string, mixed>> Documents uploaded during this request lifecycle. */
    private static array $actorKycDocumentUploads = [];

    public static function actorKycDocuments(): array
    {
        $base = [
            ['id'=>'akyc-ag03-01','ownerActorType'=>'AGENT','ownerActorId'=>'ag03','documentType'=>'NATIONAL_ID','contentHash'=>'9f8e7d6c5b4a39281706f5e4d3c2b1a00112233445566778899aabbccddeeff0','contentType'=>'image/png','uploadedByActorType'=>'BACKOFFICE_USER','uploadedByActorId'=>'11111111-0000-0000-0000-000000000001','uploadedAt'=>'2026-05-11T09:00:00Z','status'=>'PENDING_REVIEW'],
            ['id'=>'akyc-ag03-02','ownerActorType'=>'AGENT','ownerActorId'=>'ag03','documentType'=>'PROOF_OF_ADDRESS','contentHash'=>'0e1d2c3b4a5968778695a4b3c2d1e0f9e8d7c6b5a493827160f5e4d3c2b1a009','contentType'=>'application/pdf','uploadedByActorType'=>'BACKOFFICE_USER','uploadedByActorId'=>'11111111-0000-0000-0000-000000000001','uploadedAt'=>'2026-05-11T09:05:00Z','status'=>'ACCEPTED','reviewedByUserId'=>'11111111-0000-0000-0000-000000000001','reviewedAt'=>'2026-05-12T10:00:00Z'],
            ['id'=>'akyc-mc03-01','ownerActorType'=>'MERCHANT','ownerActorId'=>'mc03','documentType'=>'BUSINESS_LICENSE','contentHash'=>'3c2b1a00f5e4d3c29f8e7d6c5b4a39281706112233445566778899aabbccddee','contentType'=>'application/pdf','uploadedByActorType'=>'BACKOFFICE_USER','uploadedByActorId'=>'11111111-0000-0000-0000-000000000001','uploadedAt'=>'2026-05-10T14:00:00Z','status'=>'PENDING_REVIEW'],
            ['id'=>'akyc-mc03-02','ownerActorType'=>'MERCHANT','ownerActorId'=>'mc03','documentType'=>'NATIONAL_ID','contentHash'=>'aabbccddeeff00112233445566778899a1b2c3d4e5f60718293a4b5c6d7e8f90','contentType'=>'image/jpeg','uploadedByActorType'=>'BACKOFFICE_USER','uploadedByActorId'=>'11111111-0000-0000-0000-000000000001','uploadedAt'=>'2026-05-10T14:03:00Z','status'=>'REJECTED','reviewedByUserId'=>'11111111-0000-0000-0000-000000000001','reviewedAt'=>'2026-05-11T08:30:00Z','rejectionReason'=>'Photo is blurred; please re-upload a sharp scan of the ID.'],
        ];

        $merged = [];
        foreach (array_merge($base, static::$actorKycDocumentUploads) as $doc) {
            $id = $doc['id'];
            $merged[$id] = isset(static::$actorKycDocumentOverrides[$id])
                ? array_replace($doc, static::$actorKycDocumentOverrides[$id])
                : $doc;
        }

        return array_values($merged);
    }

    public static function actorKycDocumentsFor(string $ownerActorType, string $actorId): array
    {
        return array_values(array_filter(
            static::actorKycDocuments(),
            fn (array $d): bool => ($d['ownerActorType'] ?? null) === $ownerActorType
                && ($d['ownerActorId'] ?? null) === $actorId,
        ));
    }

    public static function actorKycDocument(string $documentId): ?array
    {
        return collect(static::actorKycDocuments())->firstWhere('id', $documentId);
    }

    public static function recordActorKycDocumentUpload(string $ownerActorType, string $actorId, string $documentType, string $contentType): array
    {
        $id = 'akyc-'.$actorId.'-'.substr(md5(uniqid('', true)), 0, 8);
        $doc = [
            'id' => $id,
            'ownerActorType' => $ownerActorType,
            'ownerActorId' => $actorId,
            'documentType' => strtoupper($documentType),
            'contentHash' => hash('sha256', $id),
            'contentType' => $contentType,
            'uploadedByActorType' => 'BACKOFFICE_USER',
            'uploadedByActorId' => (string) session('bo_user.id', '11111111-0000-0000-0000-000000000001'),
            'uploadedAt' => now()->toIso8601String(),
            'status' => 'PENDING_REVIEW',
        ];

        static::$actorKycDocumentUploads[] = $doc;

        return $doc;
    }

    public static function recordActorKycDocumentDecision(string $documentId, string $status, ?string $reason = null, ?string $reviewerId = null): ?array
    {
        $doc = static::actorKycDocument($documentId);
        if ($doc === null) {
            return null;
        }

        $override = [
            'status' => $status,
            'reviewedByUserId' => $reviewerId ?? (string) session('bo_user.id', '11111111-0000-0000-0000-000000000001'),
            'reviewedAt' => now()->toIso8601String(),
        ];

        if ($status === 'REJECTED') {
            $override['rejectionReason'] = $reason ?? '';
        } else {
            $override['rejectionReason'] = null;
        }

        static::$actorKycDocumentOverrides[$documentId] = array_replace(
            static::$actorKycDocumentOverrides[$documentId] ?? [],
            $override,
        );

        return array_replace($doc, $override);
    }
}
