<?php

namespace App\Services\Api;

use App\Exceptions\BackofficeApiException;
use App\Services\Api\Contracts\BackofficeApiContract;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Real implementation calling the upstream Lipa Backoffice API.
 *
 * Non-2xx responses are converted into BackofficeApiException so the UI
 * can show operator-friendly alerts instead of Laravel debug pages.
 */
class HttpBackofficeApi implements BackofficeApiContract
{
    private function client(): PendingRequest
    {
        $client = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('komopay.timeout', 15));

        if ($token = $this->bearerToken()) {
            $client = $client->withToken($token);
        }

        return $client;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('komopay.base_url'), '/').'/'.trim((string) config('komopay.prefix'), '/');
    }

    private function bearerToken(): ?string
    {
        if (function_exists('session') && session()->isStarted() && session()->has('bo_access_token')) {
            return (string) session('bo_access_token');
        }

        $configured = config('komopay.token');

        return $configured ? (string) $configured : null;
    }

    private function request(string $method, string $path, array $options = []): Response
    {
        if (isset($options['query']) && is_array($options['query'])) {
            $options['query'] = $this->cleanQuery($options['query']);
        }

        try {
            $response = $this->client()->send(strtoupper($method), $path, $options);
        } catch (ConnectionException $e) {
            throw new BackofficeApiException(
                status: 0,
                errorCode: 'NETWORK_ERROR',
                message: 'Could not reach the Backoffice API. Please check your connection and try again.',
                previous: $e,
            );
        }

        if ($response->failed()) {
            throw BackofficeApiException::fromResponse($response);
        }

        return $response;
    }

    private function cleanQuery(array $query): array
    {
        return array_filter($query, fn ($value) => $value !== null && $value !== '');
    }

    private function cleanPayload(array $payload): array
    {
        return array_filter($payload, fn ($value) => $value !== null);
    }

    private function stringValue(array $payload, string $key): string
    {
        return trim((string) ($payload[$key] ?? ''));
    }

    private function optionalStringValue(array $payload, string $key): ?string
    {
        $value = $this->stringValue($payload, $key);

        return $value === '' ? null : $value;
    }

    private function optionalEnumValue(array $payload, string $key): ?string
    {
        $value = $this->optionalStringValue($payload, $key);

        return $value === null ? null : strtoupper($value);
    }

    private function dateQueryToInstant(mixed $value, string $time): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return $value;
        }

        return "{$value}T{$time}Z";
    }

    private function optionalUnsignedIntegerQueryValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', trim($value));

            if ($value === '') {
                return null;
            }
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    private function enumValue(array $payload, string $key): string
    {
        return strtoupper($this->stringValue($payload, $key));
    }

    private function longValue(array $payload, string $key): mixed
    {
        $value = $payload[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $value;
    }

    private function optionalLongValue(array $payload, string $key): mixed
    {
        $value = $payload[$key] ?? null;

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $value;
    }

    private function optionalDecimalValue(array $payload, string $key): mixed
    {
        $value = $payload[$key] ?? null;

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }

    private function instantValue(array $payload, string $key): mixed
    {
        $value = $this->optionalStringValue($payload, $key);

        if ($value === null) {
            return null;
        }

        try {
            $timezone = (string) config('app.timezone', 'UTC');
            $date = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) === 1
                ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, $timezone)
                : CarbonImmutable::parse($value, $timezone);

            return $date->utc()->format('Y-m-d\TH:i:s\Z');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function enumListValue(array $payload, string $key): array
    {
        $values = is_array($payload[$key] ?? null) ? $payload[$key] : [];

        $values = array_map(
            fn (mixed $value): string => strtoupper(trim((string) $value)),
            $values,
        );

        return array_values(array_unique(array_filter($values, fn (string $value): bool => $value !== '')));
    }

    private function stringListValue(array $payload, string $key): array
    {
        $values = is_array($payload[$key] ?? null) ? $payload[$key] : [];

        $values = array_map(
            fn (mixed $value): string => trim((string) $value),
            $values,
        );

        return array_values(array_unique(array_filter($values, fn (string $value): bool => $value !== '')));
    }

    private function getList(string $path, array $query = []): array
    {
        $body = $this->request('GET', $path, ['query' => $query])->json();

        if (is_array($body) && isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }

        if (is_array($body) && isset($body['items']) && is_array($body['items'])) {
            return $body['items'];
        }

        return is_array($body) ? $body : [];
    }

    private function getPagedList(string $path, array $query = [], int $maxPages = 3): array
    {
        $rows = [];
        $cursor = $query['cursor'] ?? null;
        $page = 0;

        do {
            $pageQuery = $query;
            if ($cursor) {
                $pageQuery['cursor'] = $cursor;
            }
            $pageQuery['limit'] = $pageQuery['limit'] ?? 100;

            $body = $this->request('GET', $path, ['query' => $pageQuery])->json();
            $pageRows = [];

            if (is_array($body) && isset($body['data']) && is_array($body['data'])) {
                $pageRows = $body['data'];
            } elseif (is_array($body) && isset($body['items']) && is_array($body['items'])) {
                $pageRows = $body['items'];
            } elseif (is_array($body)) {
                $pageRows = $body;
            }

            $rows = array_merge($rows, $pageRows);

            $pagination = is_array($body['pagination'] ?? null) ? $body['pagination'] : [];
            $cursor = is_string($pagination['nextCursor'] ?? null) ? $pagination['nextCursor'] : null;
            $hasMore = (bool) ($pagination['hasMore'] ?? false);
            $page++;
        } while ($hasMore && $cursor && $page < $maxPages);

        return $rows;
    }

    private function getOne(string $path): ?array
    {
        try {
            $body = $this->request('GET', $path)->json();
        } catch (BackofficeApiException $e) {
            if ($e->status === 404) {
                return null;
            }

            throw $e;
        }

        if (is_array($body) && isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }

        return is_array($body) ? $body : null;
    }

    private function post(string $path, array $payload = []): array
    {
        $body = $this->request('POST', $path, $payload === [] ? [] : ['json' => $payload])->json();

        if (is_array($body) && isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }

        return is_array($body) ? $body : [];
    }

    private function postQuery(string $path, array $query = []): array
    {
        $body = $this->request('POST', $path, ['query' => $query])->json();

        if (is_array($body) && isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }

        return is_array($body) ? $body : [];
    }

    private function put(string $path, array $payload = []): array
    {
        $body = $this->request('PUT', $path, ['json' => $payload])->json();

        if (is_array($body) && isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }

        return is_array($body) ? $body : [];
    }

    private function patch(string $path, array $payload = []): array
    {
        $body = $this->request('PATCH', $path, $payload === [] ? [] : ['json' => $payload])->json();

        if (is_array($body) && isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }

        return is_array($body) ? $body : [];
    }

    private function getEnvelope(string $path, array $query = []): array
    {
        $body = $this->request('GET', $path, ['query' => $query])->json();

        if (is_array($body) && isset($body['data'])) {
            return is_array($body['data']) ? $body['data'] : [];
        }

        return is_array($body) ? $body : [];
    }

    private function firstById(array $rows, string $id): ?array
    {
        foreach ($rows as $row) {
            if (is_array($row) && (string) ($row['id'] ?? '') === $id) {
                return $row;
            }
        }

        return null;
    }

    private function filterRows(array $rows, array $filters): array
    {
        $filters = $this->cleanQuery($filters);

        if ($filters === []) {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) use ($filters) {
            foreach ($filters as $key => $value) {
                if ((string) ($row[$key] ?? '') !== (string) $value) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function searchRows(array $rows, string $search, array $keys): array
    {
        $needle = strtolower(trim($search));

        if ($needle === '') {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) use ($needle, $keys) {
            $haystack = [];
            foreach ($keys as $key) {
                $haystack[] = (string) ($row[$key] ?? '');
            }

            return str_contains(strtolower(implode(' ', $haystack)), $needle);
        }));
    }

    // Customers
    public function customers(array $filters = []): array
    {
        $rows = $this->getList('/customers', array_intersect_key($filters, array_flip(['cursor', 'limit', 'status'])));

        return $this->searchRows($rows, (string) ($filters['search'] ?? ''), [
            'id',
            'externalRef',
            'fullName',
            'phoneNumber',
            'nationalIdNumber',
        ]);
    }

    public function customer(string $id): ?array
    {
        return $this->getOne("/customers/$id");
    }

    public function suspendCustomer(string $id, string $reason = ''): array
    {
        return $this->post("/customers/$id/suspend");
    }

    public function reactivateCustomer(string $id): array
    {
        return $this->post("/customers/$id/reactivate");
    }

    public function requestCustomerClosure(string $id, string $reason = ''): array
    {
        return $this->post("/customers/$id/close-request", ['reason' => $reason]);
    }

    public function assignCustomerLimitProfile(string $id, string $limitProfileId): array
    {
        return $this->patch("/customers/$id/limit-profile", [
            'limitProfileId' => trim($limitProfileId),
        ]);
    }

    // Agents
    public function agents(array $filters = []): array
    {
        $rows = $this->getList('/agents', array_intersect_key($filters, array_flip(['cursor', 'limit', 'status'])));

        return $this->searchRows($rows, (string) ($filters['search'] ?? ''), [
            'id',
            'externalRef',
            'fullName',
            'phoneNumber',
            'zone',
        ]);
    }

    public function agent(string $id): ?array
    {
        return $this->getOne("/agents/$id");
    }

    public function createAgent(array $payload): array
    {
        return $this->post('/agents', $this->cleanPayload([
            'fullName' => $this->stringValue($payload, 'fullName'),
            'phoneCountryCode' => $this->stringValue($payload, 'phoneCountryCode'),
            'phoneNumber' => $this->stringValue($payload, 'phoneNumber'),
            'zone' => $this->optionalStringValue($payload, 'zone'),
            'contractRef' => $this->optionalStringValue($payload, 'contractRef'),
        ]));
    }

    public function fundAgent(string $id, string $direction, array $payload): array
    {
        return $this->post("/agents/$id/$direction", $this->cleanPayload([
            'amount' => $this->longValue($payload, 'amount'),
            'notes' => $this->optionalStringValue($payload, 'notes'),
        ]));
    }

    public function approveAgentKyc(string $id, array $payload = []): array
    {
        return $this->post("/agents/$id/approve-kyc", $this->cleanPayload([
            'kycLevel' => $this->enumValue($payload, 'kycLevel'),
        ]));
    }

    public function suspendAgent(string $id, string $reason = ''): array
    {
        return $this->post("/agents/$id/suspend");
    }

    public function reactivateAgent(string $id): array
    {
        return $this->post("/agents/$id/reactivate");
    }

    public function requestAgentClosure(string $id, string $reason = ''): array
    {
        return $this->post("/agents/$id/close-request", ['reason' => $reason]);
    }

    public function assignAgentLimitProfile(string $id, string $limitProfileId): array
    {
        return $this->patch("/agents/$id/limit-profile", [
            'limitProfileId' => trim($limitProfileId),
        ]);
    }

    // Merchants
    public function merchants(array $filters = []): array
    {
        $rows = $this->getList('/merchants', array_intersect_key($filters, array_flip(['cursor', 'limit', 'status'])));

        return $this->searchRows($rows, (string) ($filters['search'] ?? ''), [
            'id',
            'externalRef',
            'businessName',
            'legalName',
            'phoneNumber',
            'taxId',
        ]);
    }

    public function merchant(string $id): ?array
    {
        return $this->getOne("/merchants/$id");
    }

    public function createMerchant(array $payload): array
    {
        $address = is_array($payload['address'] ?? null) ? $payload['address'] : [];

        return $this->post('/merchants', $this->cleanPayload([
            'businessName' => $this->stringValue($payload, 'businessName'),
            'legalName' => $this->stringValue($payload, 'legalName'),
            'businessType' => $this->enumValue($payload, 'businessType'),
            'taxId' => $this->optionalStringValue($payload, 'taxId'),
            'phoneCountryCode' => $this->stringValue($payload, 'phoneCountryCode'),
            'phoneNumber' => $this->stringValue($payload, 'phoneNumber'),
            'addressIsland' => $this->optionalStringValue($payload, 'addressIsland') ?? $this->optionalStringValue($address, 'island'),
            'addressCity' => $this->optionalStringValue($payload, 'addressCity') ?? $this->optionalStringValue($address, 'city'),
            'addressDistrict' => $this->optionalStringValue($payload, 'addressDistrict') ?? $this->optionalStringValue($address, 'district'),
            'category' => $this->enumValue($payload, 'category'),
        ]));
    }

    public function setMerchantM2M(string $id, bool $enabled): array
    {
        return $this->post("/merchants/$id/m2m/".($enabled ? 'enable' : 'disable'));
    }

    public function approveMerchantKyc(string $id, array $payload = []): array
    {
        return $this->post("/merchants/$id/approve-kyc", $this->cleanPayload([
            'kycLevel' => $this->enumValue($payload, 'kycLevel'),
        ]));
    }

    public function suspendMerchant(string $id, string $reason = ''): array
    {
        return $this->post("/merchants/$id/suspend");
    }

    public function reactivateMerchant(string $id): array
    {
        return $this->post("/merchants/$id/reactivate");
    }

    public function requestMerchantClosure(string $id, string $reason = ''): array
    {
        return $this->post("/merchants/$id/close-request", ['reason' => $reason]);
    }

    public function assignMerchantLimitProfile(string $id, string $limitProfileId): array
    {
        return $this->patch("/merchants/$id/limit-profile", [
            'limitProfileId' => trim($limitProfileId),
        ]);
    }

    // Transactions
    public function transactions(array $filters = []): array
    {
        return $this->getList('/transactions', $filters);
    }

    public function transaction(string $id): ?array
    {
        return $this->getOne("/transactions/$id");
    }

    public function reverseTransaction(array $payload): array
    {
        return $this->post('/transactions/reversals', $payload);
    }

    // Approvals
    public function approvals(array $filters = []): array
    {
        $rows = $this->getList('/approvals', array_intersect_key($filters, array_flip(['cursor', 'limit', 'pendingOnly'])));

        return $this->filterRows($rows, array_intersect_key($filters, array_flip(['type'])));
    }

    public function approval(string $id): ?array
    {
        return $this->getOne("/approvals/$id");
    }

    public function approveRequest(string $id, array $payload = []): array
    {
        return $this->post("/approvals/$id/approve", $this->cleanPayload([
            'reason' => $this->optionalStringValue($payload, 'reason'),
        ]));
    }

    public function rejectRequest(string $id, array $payload): array
    {
        return $this->post("/approvals/$id/reject", [
            'reason' => $this->stringValue($payload, 'reason'),
        ]);
    }

    // Audit
    public function auditEvents(array $filters = []): array
    {
        if (array_key_exists('from', $filters)) {
            $filters['from'] = $this->dateQueryToInstant($filters['from'], '00:00:00');
        }

        if (array_key_exists('to', $filters)) {
            $filters['to'] = $this->dateQueryToInstant($filters['to'], '23:59:59');
        }

        return $this->getList('/audit', $filters);
    }

    // Backoffice users
    public function backofficeUsers(): array
    {
        return $this->getList('/users');
    }

    public function createBackofficeUser(array $payload): array
    {
        return $this->post('/users', [
            'email' => $this->stringValue($payload, 'email'),
            'password' => (string) ($payload['password'] ?? ''),
            'fullName' => $this->stringValue($payload, 'fullName'),
            'role' => $this->enumValue($payload, 'role'),
        ]);
    }

    public function suspendBackofficeUser(string $id): array
    {
        return $this->post("/users/$id/suspend");
    }

    public function reactivateBackofficeUser(string $id): array
    {
        return $this->post("/users/$id/reactivate");
    }

    public function closeBackofficeUser(string $id): array
    {
        return $this->post("/users/$id/close");
    }

    public function elevateBackofficeUserRole(string $id, array $payload): array
    {
        return $this->post("/users/$id/elevate-role", [
            'newRole' => $this->enumValue($payload, 'newRole'),
        ]);
    }

    // Dashboard
    public function dashboardStats(): array
    {
        $todayFrom = now()->startOfDay()->toIso8601String();
        $todayTo = now()->endOfDay()->toIso8601String();

        $customers = $this->getPagedList('/customers', ['limit' => 100], 2);
        $agents = $this->getPagedList('/agents', ['limit' => 100], 2);
        $merchants = $this->getPagedList('/merchants', ['limit' => 100], 2);
        $transactions = $this->getPagedList('/transactions', [
            'from' => $todayFrom,
            'to' => $todayTo,
            'limit' => 100,
        ], 2);
        $pendingApprovals = $this->getPagedList('/approvals', [
            'pendingOnly' => true,
            'limit' => 100,
        ], 2);
        $openIncidents = array_merge(
            $this->getPagedList('/reconciliation/incidents', ['status' => 'OPEN', 'limit' => 100], 1),
            $this->getPagedList('/reconciliation/incidents', ['status' => 'UNDER_INVESTIGATION', 'limit' => 100], 1),
        );

        $volumeToday = array_sum(array_map(fn ($tx) => (int) ($tx['requestedAmount'] ?? 0), $transactions));
        $byType = [];

        foreach ($transactions as $tx) {
            $type = (string) ($tx['type'] ?? 'UNKNOWN');
            $byType[$type] ??= ['type' => $type, 'count' => 0, 'amount' => 0];
            $byType[$type]['count']++;
            $byType[$type]['amount'] += (int) ($tx['requestedAmount'] ?? 0);
        }

        usort($transactions, fn ($a, $b) => strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? '')));

        return [
            'totalCustomers' => count($customers),
            'activeAgents' => count(array_filter($agents, fn ($agent) => ($agent['status'] ?? null) === 'ACTIVE')),
            'activeMerchants' => count(array_filter($merchants, fn ($merchant) => ($merchant['status'] ?? null) === 'ACTIVE')),
            'transactionsToday' => count($transactions),
            'volumeToday' => $volumeToday,
            'pendingApprovals' => count($pendingApprovals),
            'openReconciliation' => count($openIncidents),
            'txByType' => array_values($byType),
            'recentTransactions' => array_slice($transactions, 0, 8),
        ];
    }

    // Wallets
    public function wallets(array $filters = []): array
    {
        $wallets = [];
        $actors = [
            'CUSTOMER' => $this->getPagedList('/customers', ['limit' => 100], 2),
            'AGENT' => $this->getPagedList('/agents', ['limit' => 100], 2),
            'MERCHANT' => $this->getPagedList('/merchants', ['limit' => 100], 2),
        ];

        foreach ($actors as $ownerType => $rows) {
            foreach ($rows as $owner) {
                $walletId = $owner['walletId'] ?? null;
                if (! is_string($walletId) || $walletId === '') {
                    continue;
                }

                $wallet = $this->walletById($walletId);
                if (! $wallet) {
                    continue;
                }

                $wallets[] = $wallet + [
                    'ownerType' => $ownerType,
                    'ownerId' => $owner['id'] ?? '',
                    'ownerLabel' => $owner['fullName'] ?? $owner['businessName'] ?? $owner['legalName'] ?? '-',
                    'ownerRef' => $owner['externalRef'] ?? '-',
                ];
            }
        }

        $search = strtolower((string) ($filters['search'] ?? ''));
        $ownerType = $filters['ownerType'] ?? null;
        $status = $filters['status'] ?? null;

        return array_values(array_filter($wallets, function (array $wallet) use ($search, $ownerType, $status) {
            if ($ownerType && ($wallet['ownerType'] ?? null) !== $ownerType) {
                return false;
            }

            if ($status && ($wallet['status'] ?? null) !== $status) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', [
                $wallet['id'] ?? '',
                $wallet['ownerId'] ?? '',
                $wallet['ownerLabel'] ?? '',
                $wallet['ownerRef'] ?? '',
            ]));

            return str_contains($haystack, $search);
        }));
    }

    public function wallet(string $id): array
    {
        return (array) ($this->getOne("/wallets/$id") ?? []);
    }

    public function walletById(string $id): ?array
    {
        return $this->getOne("/wallets/$id");
    }

    public function freezeWallet(string $id, string $reason = ''): array
    {
        return $this->post("/wallets/$id/freeze");
    }

    public function unfreezeWallet(string $id): array
    {
        return $this->post("/wallets/$id/unfreeze");
    }

    // Rules and limits
    public function limitProfiles(): array
    {
        return $this->getList('/limit-profiles');
    }

    public function limitProfile(string $id): ?array
    {
        return $this->getOne("/limit-profiles/$id");
    }

    public function feeRules(array $filters = []): array
    {
        return $this->filterRows($this->getList('/fee-rules'), $filters);
    }

    public function feeRule(string $id): ?array
    {
        return $this->getOne("/fee-rules/$id");
    }

    public function createFeeRule(array $payload): array
    {
        return $this->post('/fee-rules', $this->feeRulePayload($payload));
    }

    public function commissionRules(array $filters = []): array
    {
        return $this->filterRows($this->getList('/commission-rules'), $filters);
    }

    public function commissionRule(string $id): ?array
    {
        return $this->getOne("/commission-rules/$id");
    }

    public function createCommissionRule(array $payload): array
    {
        return $this->post('/commission-rules', $this->commissionRulePayload($payload));
    }

    public function createLimitProfile(array $payload): array
    {
        return $this->post('/limit-profiles', $this->limitProfilePayload($payload));
    }

    public function controlThresholds(array $filters = []): array
    {
        return $this->filterRows($this->getList('/control-thresholds'), $filters);
    }

    public function controlThreshold(string $id): ?array
    {
        return $this->getOne("/control-thresholds/$id");
    }

    public function createControlThreshold(array $payload): array
    {
        return $this->post('/control-thresholds', $this->controlThresholdPayload($payload));
    }

    public function activateRule(string $kind, string $id): array
    {
        if (strtolower($kind) === 'limit') {
            return $this->patch("/limit-profiles/$id/activate");
        }

        return $this->post('/'.$this->ruleKindPath($kind)."/$id/activate");
    }

    public function deactivateRule(string $kind, string $id): array
    {
        if (strtolower($kind) === 'limit') {
            return $this->patch("/limit-profiles/$id/deactivate");
        }

        return $this->post('/'.$this->ruleKindPath($kind)."/$id/deactivate");
    }

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

    private function feeRulePayload(array $payload): array
    {
        $calculationType = $this->enumValue($payload, 'calculationType');

        $mapped = [
            'name' => $this->stringValue($payload, 'name'),
            'description' => $this->optionalStringValue($payload, 'description'),
            'transactionType' => $this->enumValue($payload, 'transactionType'),
            'actorType' => $this->optionalEnumValue($payload, 'actorType'),
            'actorId' => $this->optionalStringValue($payload, 'actorId'),
            'cardType' => $this->optionalEnumValue($payload, 'cardType'),
            'merchantCategory' => $this->optionalEnumValue($payload, 'merchantCategory'),
            'minAmount' => $this->optionalLongValue($payload, 'minAmount'),
            'maxAmount' => $this->optionalLongValue($payload, 'maxAmount'),
            'zone' => $this->optionalStringValue($payload, 'zone'),
            'serviceProviderId' => $this->optionalStringValue($payload, 'serviceProviderId'),
            'promoCode' => $this->optionalStringValue($payload, 'promoCode'),
            'calculationType' => $calculationType,
            'feeBearer' => $this->enumValue($payload, 'feeBearer'),
            'priority' => $this->longValue($payload, 'priority'),
            'validFrom' => $this->instantValue($payload, 'validFrom'),
            'validTo' => $this->instantValue($payload, 'validTo'),
            'activeOnApproval' => (bool) ($payload['activeOnApproval'] ?? false),
        ];

        if (in_array($calculationType, ['FLAT', 'MAX_OF', 'MIN_OF'], true)) {
            $mapped['flatAmount'] = $this->optionalLongValue($payload, 'flatAmount');
        }

        if (in_array($calculationType, ['PERCENTAGE', 'MAX_OF', 'MIN_OF'], true)) {
            $mapped['percentage'] = $this->optionalDecimalValue($payload, 'percentage');
            $mapped['minFeeAmount'] = $this->optionalLongValue($payload, 'minFeeAmount');
            $mapped['maxFeeAmount'] = $this->optionalLongValue($payload, 'maxFeeAmount');
        }

        if ($calculationType === 'TIERED' && is_array($payload['tiers'] ?? null)) {
            $mapped['tiers'] = array_map(
                fn (array $tier): array => $this->cleanPayload([
                    'minAmount' => $this->longValue($tier, 'minAmount'),
                    'maxAmount' => $this->optionalLongValue($tier, 'maxAmount'),
                    'flatAmount' => $this->longValue($tier, 'flatAmount'),
                ]),
                $payload['tiers'],
            );
        }

        return $this->cleanPayload($mapped);
    }

    private function commissionRulePayload(array $payload): array
    {
        $calculationType = $this->enumValue($payload, 'calculationType');

        $mapped = [
            'name' => $this->stringValue($payload, 'name'),
            'transactionType' => $this->enumValue($payload, 'transactionType'),
            'agentId' => $this->optionalStringValue($payload, 'agentId'),
            'calculationType' => $calculationType,
            'settlementMode' => $this->enumValue($payload, 'settlementMode'),
            'priority' => $this->longValue($payload, 'priority'),
            'validFrom' => $this->instantValue($payload, 'validFrom'),
            'validTo' => $this->instantValue($payload, 'validTo'),
            'activeOnApproval' => (bool) ($payload['activeOnApproval'] ?? false),
        ];

        if ($calculationType === 'FLAT') {
            $mapped['flatAmount'] = $this->optionalLongValue($payload, 'flatAmount');
        } else {
            $mapped['percentage'] = $this->optionalDecimalValue($payload, 'percentage');
        }

        return $this->cleanPayload($mapped);
    }

    private function limitProfilePayload(array $payload): array
    {
        $mapped = [
            'name' => $this->stringValue($payload, 'name'),
            'applicableActorTypes' => $this->enumListValue($payload, 'applicableActorTypes'),
            'maxTransactionAmount' => $this->optionalLongValue($payload, 'maxTransactionAmount'),
            'minTransactionAmount' => $this->optionalLongValue($payload, 'minTransactionAmount'),
            'maxDailyAmount' => $this->optionalLongValue($payload, 'maxDailyAmount'),
            'maxWeeklyAmount' => $this->optionalLongValue($payload, 'maxWeeklyAmount'),
            'maxMonthlyAmount' => $this->optionalLongValue($payload, 'maxMonthlyAmount'),
            'maxDailyTransactionCount' => $this->optionalLongValue($payload, 'maxDailyTransactionCount'),
            'maxMonthlyTransactionCount' => $this->optionalLongValue($payload, 'maxMonthlyTransactionCount'),
            'requiredKycLevel' => $this->enumValue($payload, 'requiredKycLevel'),
        ];

        if (is_array($payload['operationLimits'] ?? null)) {
            $operationLimits = [];

            foreach ($payload['operationLimits'] as $transactionType => $limits) {
                if (! is_array($limits)) {
                    continue;
                }

                $operationLimits[strtoupper((string) $transactionType)] = $this->cleanPayload([
                    'maxTransactionAmount' => $this->optionalLongValue($limits, 'maxTransactionAmount'),
                    'minTransactionAmount' => $this->optionalLongValue($limits, 'minTransactionAmount'),
                    'maxDailyAmount' => $this->optionalLongValue($limits, 'maxDailyAmount'),
                    'maxWeeklyAmount' => $this->optionalLongValue($limits, 'maxWeeklyAmount'),
                    'maxMonthlyAmount' => $this->optionalLongValue($limits, 'maxMonthlyAmount'),
                    'maxDailyTransactionCount' => $this->optionalLongValue($limits, 'maxDailyTransactionCount'),
                    'maxMonthlyTransactionCount' => $this->optionalLongValue($limits, 'maxMonthlyTransactionCount'),
                ]);
            }

            if ($operationLimits !== []) {
                $mapped['operationLimits'] = $operationLimits;
            }
        }

        return $this->cleanPayload($mapped);
    }

    private function controlThresholdPayload(array $payload): array
    {
        $scopeType = $this->enumValue($payload, 'scopeType');

        $mapped = [
            'transactionType' => $this->enumValue($payload, 'transactionType'),
            'actorType' => $this->enumValue($payload, 'actorType'),
            'scopeType' => $scopeType,
            'currency' => $this->optionalEnumValue($payload, 'currency'),
            'pinRequiredAboveAmount' => $this->optionalLongValue($payload, 'pinRequiredAboveAmount'),
            'confirmationRequiredAboveAmount' => $this->optionalLongValue($payload, 'confirmationRequiredAboveAmount'),
            'approvalRequiredAboveAmount' => $this->optionalLongValue($payload, 'approvalRequiredAboveAmount'),
            'approvalType' => $this->optionalEnumValue($payload, 'approvalType'),
        ];

        if ($scopeType !== 'GLOBAL') {
            $mapped['scopeId'] = $this->optionalStringValue($payload, 'scopeId');
        }

        return $this->cleanPayload($mapped);
    }

    // Treasury
    public function commissionSettlementRuns(array $filters = []): array
    {
        return $this->getList('/commission-settlements/runs', $filters);
    }

    public function commissionSettlementRun(string $id): ?array
    {
        return $this->firstById($this->commissionSettlementRuns(['limit' => 100]), $id);
    }

    public function commissionPendingSummary(): array
    {
        return $this->getEnvelope('/commission-settlements/pending');
    }

    public function billProviderSettlementBalances(): array
    {
        return $this->getList('/bill-provider-settlement/balances');
    }

    public function platformRevenueBalances(): array
    {
        return $this->getList('/platform-revenue/balances');
    }

    public function platformLiquidityBalances(): array
    {
        return $this->getList('/platform-liquidity/balances');
    }

    public function triggerCommissionSettlement(array $payload = []): array
    {
        return $this->post('/commission-settlements/trigger', $payload);
    }

    public function requestBillProviderSettlement(array $payload): array
    {
        return $this->post('/bill-provider-settlement/requests', $payload);
    }

    public function requestPlatformRevenueWithdrawal(array $payload): array
    {
        return $this->post('/platform-revenue/withdrawal-requests', $payload);
    }

    public function requestPlatformLiquidityTopUp(array $payload): array
    {
        return $this->post('/platform-liquidity/top-up-requests', $this->cleanPayload([
            'amount' => $this->longValue($payload, 'amount'),
            'currency' => $this->optionalStringValue($payload, 'currency'),
            'externalReference' => $this->stringValue($payload, 'externalReference'),
            'source' => $this->stringValue($payload, 'source'),
            'notes' => $this->optionalStringValue($payload, 'notes'),
        ]));
    }

    // Cards
    public function cards(array $filters = []): array
    {
        $query = [];
        if (isset($filters['customerId'])) {
            $query['customerId'] = $filters['customerId'];
        }

        $rows = $this->getList('/cards', $query);

        return $this->filterRows($rows, array_intersect_key($filters, array_flip(['status', 'cardType'])));
    }

    public function card(string $id): ?array
    {
        return $this->getOne("/cards/$id");
    }

    public function cardStock(array $filters = []): array
    {
        return $this->getList('/card-stock', $filters);
    }

    public function cardStockItem(string $id): ?array
    {
        return $this->getOne("/card-stock/$id");
    }

    public function blockCard(string $id, string $reason = ''): array
    {
        return $this->post("/cards/$id/block");
    }

    public function unblockCard(string $id): array
    {
        return $this->post("/cards/$id/unblock");
    }

    public function reportCardLost(string $id, string $reason = ''): array
    {
        return $this->post("/cards/$id/report-lost");
    }

    public function reportCardStolen(string $id, string $reason = ''): array
    {
        return $this->post("/cards/$id/report-stolen");
    }

    public function closeCard(string $id, string $reason = ''): array
    {
        return $this->post("/cards/$id/close", $this->cleanPayload([
            'reason' => trim($reason) === '' ? null : trim($reason),
        ]));
    }

    public function importCardStock(array $payload): array
    {
        return $this->post('/card-stock/import', $this->cleanPayload([
            'batchRef' => $this->stringValue($payload, 'batchRef'),
            'producedAt' => $this->optionalStringValue($payload, 'producedAt'),
            'cards' => $this->cardBatchEntries($payload['cards'] ?? []),
        ]));
    }

    public function assignCardStock(array $payload): array
    {
        return $this->post('/card-stock/assign', $this->cleanPayload([
            'agentId' => $this->stringValue($payload, 'agentId'),
            'cardStockIds' => $this->stringListValue($payload, 'cardStockIds'),
        ]));
    }

    private function cardBatchEntries(mixed $cards): array
    {
        if (! is_array($cards)) {
            return [];
        }

        $entries = [];

        foreach ($cards as $card) {
            if (! is_array($card)) {
                continue;
            }

            $entries[] = $this->cleanPayload([
                'nfcUid' => strtoupper($this->stringValue($card, 'nfcUid')),
                'internalCardNumber' => $this->stringValue($card, 'internalCardNumber'),
                'authKeyEncryptedBase64' => $this->optionalStringValue($card, 'authKeyEncryptedBase64'),
                'authKeyVersion' => $this->longValue($card, 'authKeyVersion'),
            ]);
        }

        return $entries;
    }

    // Terminals
    public function terminals(array $filters = []): array
    {
        $query = [];
        if (isset($filters['merchantId'])) {
            $query['merchantId'] = $filters['merchantId'];
        }

        $rows = $this->getList('/terminals', $query);

        return $this->filterRows($rows, array_intersect_key($filters, array_flip(['status'])));
    }

    public function terminal(string $id): ?array
    {
        return $this->getOne("/terminals/$id");
    }

    public function createTerminal(array $payload): array
    {
        return $this->post('/terminals', $this->cleanPayload([
            'serialNumber' => $this->stringValue($payload, 'serialNumber'),
            'deviceModel' => $this->optionalStringValue($payload, 'deviceModel'),
            'androidVersion' => $this->optionalStringValue($payload, 'androidVersion'),
            'appVersion' => $this->optionalStringValue($payload, 'appVersion'),
            'merchantId' => $this->stringValue($payload, 'merchantId'),
        ]));
    }

    public function provisionTerminal(string $id, array $payload = []): array
    {
        return $this->post("/terminals/$id/provision");
    }

    public function suspendTerminal(string $id, string $reason = ''): array
    {
        return $this->post("/terminals/$id/suspend");
    }

    public function reactivateTerminal(string $id): array
    {
        return $this->post("/terminals/$id/reactivate");
    }

    // Service providers
    public function serviceProviders(array $filters = []): array
    {
        return $this->filterRows($this->getList('/service-providers'), $filters);
    }

    public function serviceProvider(string $id): ?array
    {
        return $this->getOne("/service-providers/$id");
    }

    public function createServiceProvider(array $payload): array
    {
        return $this->post('/service-providers', $payload);
    }

    public function updateServiceProvider(string $id, array $payload): array
    {
        return $this->put("/service-providers/$id", $payload);
    }

    public function activateServiceProvider(string $id): array
    {
        return $this->post("/service-providers/$id/activate");
    }

    public function deactivateServiceProvider(string $id): array
    {
        return $this->post("/service-providers/$id/deactivate");
    }

    public function billServices(string $providerId = '', array $filters = []): array
    {
        if ($providerId === '') {
            $services = [];

            foreach ($this->serviceProviders() as $provider) {
                $id = $provider['id'] ?? null;
                if (is_string($id) && $id !== '') {
                    $services = array_merge($services, $this->billServices($id, $filters));
                }
            }

            return $services;
        }

        $path = "/service-providers/$providerId/services";

        return $this->filterRows($this->getList($path), $filters);
    }

    public function billService(string $providerId, string $id): ?array
    {
        return $this->firstById($this->billServices($providerId), $id);
    }

    public function createBillService(string $providerId, array $payload): array
    {
        return $this->post("/service-providers/$providerId/services", $payload);
    }

    public function updateBillService(string $providerId, string $serviceId, array $payload): array
    {
        return $this->put("/service-providers/$providerId/services/$serviceId", $payload);
    }

    public function activateBillService(string $providerId, string $serviceId): array
    {
        return $this->post("/service-providers/$providerId/services/$serviceId/activate");
    }

    public function deactivateBillService(string $providerId, string $serviceId): array
    {
        return $this->post("/service-providers/$providerId/services/$serviceId/deactivate");
    }

    // Reconciliation
    public function reconciliationIncidents(array $filters = []): array
    {
        return $this->getList('/reconciliation/incidents', $filters);
    }

    public function reconciliationIncident(string $id): ?array
    {
        return $this->getOne("/reconciliation/incidents/$id");
    }

    public function reconciliationRuns(array $filters = []): array
    {
        $query = array_intersect_key($filters, array_flip(['cursor', 'limit']));
        $rows = $this->getList('/reconciliation/runs', $query);

        return $this->filterRows($rows, array_diff_key($filters, array_flip(['cursor', 'limit'])));
    }

    public function reconciliationRun(string $id): ?array
    {
        return $this->getOne("/reconciliation/runs/$id");
    }

    public function investigateIncident(string $id, array $payload = []): array
    {
        return $this->post("/reconciliation/incidents/$id/investigate", $payload);
    }

    public function resolveIncident(string $id, array $payload = []): array
    {
        return $this->post("/reconciliation/incidents/$id/resolve", $payload);
    }

    public function closeIncident(string $id, array $payload = []): array
    {
        return $this->post("/reconciliation/incidents/$id/close", $payload);
    }

    // Reports
    public function transactionSummaryReport(array $filters = []): array
    {
        $query = $this->reportDateRangeQuery($filters);

        if (array_key_exists('groupBy', $filters)) {
            $query['groupBy'] = $this->optionalEnumValue($filters, 'groupBy');
        }

        $query['format'] = 'json';
        $report = $this->getEnvelope('/reports/transactions/summary', $query);
        $type = $this->optionalEnumValue($filters, 'type');

        if ($type && isset($report['lines']) && is_array($report['lines'])) {
            $report['lines'] = array_values(array_filter(
                $report['lines'],
                fn ($line) => is_array($line) && ($line['type'] ?? null) === $type,
            ));
        }

        return $report;
    }

    public function kycSummaryReport(): array
    {
        return $this->getEnvelope('/reports/kyc/summary', ['format' => 'json']);
    }

    public function amlLargeTransactions(array $filters = []): array
    {
        $query = $this->reportDateRangeQuery($filters);

        foreach (['cursor', 'limit'] as $key) {
            if (array_key_exists($key, $filters)) {
                $query[$key] = $filters[$key];
            }
        }

        if (array_key_exists('thresholdKmf', $filters)) {
            $query['thresholdKmf'] = $this->optionalUnsignedIntegerQueryValue($filters['thresholdKmf']);
        }

        return $this->getList('/reports/aml/large-transactions', $query);
    }

    public function floatReport(): array
    {
        return $this->getEnvelope('/reports/float');
    }

    public function actorSummaryReport(): array
    {
        return $this->getEnvelope('/reports/actors/summary', ['format' => 'json']);
    }

    public function reportExports(array $filters = []): array
    {
        $query = [];

        foreach (['cursor', 'limit'] as $key) {
            if (array_key_exists($key, $filters)) {
                $query[$key] = $filters[$key];
            }
        }

        if (array_key_exists('reportType', $filters)) {
            $query['reportType'] = $this->optionalEnumValue($filters, 'reportType');
        }

        return $this->getList('/reports/exports', $query);
    }

    public function reportExport(string $id): ?array
    {
        return $this->firstById($this->reportExports(['limit' => 100]), $id);
    }

    public function requestReportExport(array $payload): array
    {
        return $this->postQuery('/reports/exports', $this->reportExportQuery($payload));
    }

    public function downloadReport(string $path, array $query = []): array
    {
        $reportType = strtoupper(str_replace('-', '_', trim($path, '/')));
        $aliases = [
            'TRANSACTIONS_SUMMARY' => 'TRANSACTION_SUMMARY',
            'ACTORS_SUMMARY' => 'ACTOR_SUMMARY',
            'FLOAT' => 'FLOAT_REPORT',
        ];

        $reportType = $aliases[$reportType] ?? $reportType;
        $paths = [
            'TRANSACTION_SUMMARY' => '/reports/transactions/summary',
            'KYC_SUMMARY' => '/reports/kyc/summary',
            'ACTOR_SUMMARY' => '/reports/actors/summary',
        ];

        if (! isset($paths[$reportType])) {
            return [];
        }

        $response = $this->request('GET', $paths[$reportType], [
            'query' => $this->downloadReportQuery($reportType, $query),
        ]);

        return [
            'contentType' => $response->header('Content-Type'),
            'body' => $response->body(),
        ];
    }

    private function reportDateRangeQuery(array $filters): array
    {
        $query = [];

        if (array_key_exists('from', $filters)) {
            $query['from'] = $this->dateQueryToInstant($filters['from'], '00:00:00');
        }

        if (array_key_exists('to', $filters)) {
            $query['to'] = $this->dateQueryToInstant($filters['to'], '23:59:59');
        }

        return $query;
    }

    private function reportExportQuery(array $payload): array
    {
        $query = [
            'reportType' => $this->enumValue($payload, 'reportType'),
            'periodFrom' => $this->dateQueryToInstant($payload['periodFrom'] ?? null, '00:00:00'),
            'periodTo' => $this->dateQueryToInstant($payload['periodTo'] ?? null, '23:59:59'),
            'recordCount' => $this->optionalLongValue($payload, 'recordCount'),
        ];

        return $this->cleanQuery($query);
    }

    private function downloadReportQuery(string $reportType, array $query): array
    {
        $mapped = [];

        if ($reportType === 'TRANSACTION_SUMMARY') {
            $mapped = $this->reportDateRangeQuery($query);

            if (array_key_exists('groupBy', $query)) {
                $mapped['groupBy'] = $this->optionalEnumValue($query, 'groupBy');
            }
        }

        $mapped['format'] = 'csv';

        return $mapped;
    }
}
