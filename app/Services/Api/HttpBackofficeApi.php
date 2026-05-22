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

    private function binaryClient(): PendingRequest
    {
        $client = Http::baseUrl($this->baseUrl())
            ->accept('*/*')
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

    private function binaryRequest(string $method, string $path, array $options = []): Response
    {
        if (isset($options['query']) && is_array($options['query'])) {
            $options['query'] = $this->cleanQuery($options['query']);
        }

        try {
            $response = $this->binaryClient()->send(strtoupper($method), $path, $options);
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

    /**
     * Phone country codes must be submitted as digits only (e.g. "269", not "+269").
     * The UI may show a "+" for readability, but it is stripped before transport.
     */
    private function phoneCountryCodeValue(array $payload, string $key): string
    {
        return preg_replace('/\D+/', '', $this->stringValue($payload, $key)) ?? '';
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
        return $this->getPage($path, $query)['data'];
    }

    private function getPage(string $path, array $query = []): array
    {
        $body = $this->request('GET', $path, ['query' => $query])->json();

        if (is_array($body) && isset($body['data']) && is_array($body['data'])) {
            $rows = $body['data'];
        } elseif (is_array($body) && isset($body['items']) && is_array($body['items'])) {
            $rows = $body['items'];
        } else {
            $rows = is_array($body) ? $body : [];
        }

        $pagination = is_array($body['pagination'] ?? null) ? $body['pagination'] : [];

        return [
            'data' => $rows,
            'pagination' => [
                'nextCursor' => is_string($pagination['nextCursor'] ?? null) ? $pagination['nextCursor'] : null,
                'hasMore' => (bool) ($pagination['hasMore'] ?? false),
                'limit' => (int) ($pagination['limit'] ?? count($rows)),
            ],
        ];
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

            $pageResult = $this->getPage($path, $pageQuery);
            $pageRows = $pageResult['data'];

            $rows = array_merge($rows, $pageRows);

            $cursor = $pageResult['pagination']['nextCursor'];
            $hasMore = (bool) $pageResult['pagination']['hasMore'];
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
        return $this->customersPage($filters)['data'];
    }

    public function customersPage(array $filters = []): array
    {
        $page = $this->getPage('/customers', array_intersect_key($filters, array_flip(['cursor', 'limit', 'status'])));

        $page['data'] = $this->searchRows($page['data'], (string) ($filters['search'] ?? ''), [
            'id',
            'externalRef',
            'fullName',
            'phoneNumber',
            'nationalIdNumber',
        ]);

        return $page;
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

    public function resetCustomerAuthPin(string $id): array
    {
        return $this->post("/customers/$id/auth-pin/reset");
    }

    public function assignCustomerLimitProfile(string $id, string $limitProfileId): array
    {
        return $this->patch("/customers/$id/limit-profile", [
            'limitProfileId' => trim($limitProfileId),
        ]);
    }

    // Customer KYC review (spec §5.3a)
    public function customerKycDocuments(string $customerId): array
    {
        return $this->getList("/customers/$customerId/kyc-documents");
    }

    public function kycDocument(string $documentId): ?array
    {
        return $this->getOne("/kyc-documents/$documentId");
    }

    public function downloadKycDocumentFile(string $documentId): array
    {
        $response = $this->binaryRequest('GET', "/kyc-documents/$documentId/file");
        $disposition = (string) $response->header('Content-Disposition');
        $filename = "kyc-$documentId.bin";

        if (preg_match('/filename="?([^";]+)"?/i', $disposition, $matches) === 1) {
            $filename = trim($matches[1]);
        }

        return [
            'contentType' => $response->header('Content-Type') ?: 'application/octet-stream',
            'filename' => $filename,
            'body' => $response->body(),
        ];
    }

    public function approveKycDocument(string $documentId): array
    {
        return $this->post("/kyc-documents/$documentId/approve");
    }

    public function rejectKycDocument(string $documentId, string $reason): array
    {
        return $this->post("/kyc-documents/$documentId/reject", [
            'reason' => trim($reason),
        ]);
    }

    public function changeCustomerKycLevel(string $customerId, string $kycLevel, ?string $nextReviewDate = null): array
    {
        return $this->post("/customers/$customerId/kyc-level", $this->cleanPayload([
            'kycLevel' => strtoupper(trim($kycLevel)),
            'nextReviewDate' => $nextReviewDate !== null && trim($nextReviewDate) !== ''
                ? trim($nextReviewDate)
                : null,
        ]));
    }

    public function activateCustomer(string $customerId): array
    {
        return $this->post("/customers/$customerId/activate");
    }

    // Agent & Merchant KYC/KYB review (spec §5.3b)
    private function actorOwnerSegment(string $ownerType): string
    {
        $segment = strtolower(trim($ownerType));

        // The spec exposes these dossiers only under /agents/* and /merchants/*.
        if (!in_array($segment, ['agents', 'merchants'], true)) {
            throw new \InvalidArgumentException("Unsupported KYC owner type: $ownerType");
        }

        return $segment;
    }

    public function uploadActorKycDocument(string $ownerType, string $actorId, string $documentType, \Illuminate\Http\UploadedFile $file): array
    {
        $segment = $this->actorOwnerSegment($ownerType);

        // multipart/form-data with documentType + file (spec §5.3b "Upload and file rules").
        // The backend byte-sniffs the content, so the declared MIME and extension are not trusted.
        // A dedicated client is used: the shared client()'s asJson() body format would
        // otherwise override the multipart encoding.
        $client = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asMultipart()
            ->timeout((int) config('komopay.timeout', 15));

        if ($token = $this->bearerToken()) {
            $client = $client->withToken($token);
        }

        try {
            $response = $client->post("/$segment/$actorId/kyc-documents", [
                ['name' => 'documentType', 'contents' => strtoupper(trim($documentType))],
                [
                    'name' => 'file',
                    'contents' => file_get_contents($file->getRealPath()),
                    'filename' => $file->getClientOriginalName(),
                    'headers' => ['Content-Type' => $file->getMimeType() ?: 'application/octet-stream'],
                ],
            ]);
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

        $body = $response->json();

        if (is_array($body) && isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }

        return is_array($body) ? $body : [];
    }

    public function actorKycDocuments(string $ownerType, string $actorId): array
    {
        $segment = $this->actorOwnerSegment($ownerType);

        return $this->getList("/$segment/$actorId/kyc-documents");
    }

    public function actorKycDocument(string $ownerType, string $documentId): ?array
    {
        $segment = $this->actorOwnerSegment($ownerType);

        return $this->getOne("/$segment/kyc-documents/$documentId");
    }

    public function downloadActorKycDocumentFile(string $ownerType, string $documentId): array
    {
        $segment = $this->actorOwnerSegment($ownerType);
        $response = $this->binaryRequest('GET', "/$segment/kyc-documents/$documentId/file");
        $disposition = (string) $response->header('Content-Disposition');
        $filename = "kyc-$documentId.bin";

        if (preg_match('/filename="?([^";]+)"?/i', $disposition, $matches) === 1) {
            $filename = trim($matches[1]);
        }

        return [
            'contentType' => $response->header('Content-Type') ?: 'application/octet-stream',
            'filename' => $filename,
            'body' => $response->body(),
        ];
    }

    public function approveActorKycDocument(string $ownerType, string $documentId): array
    {
        $segment = $this->actorOwnerSegment($ownerType);

        return $this->post("/$segment/kyc-documents/$documentId/approve");
    }

    public function rejectActorKycDocument(string $ownerType, string $documentId, string $reason): array
    {
        $segment = $this->actorOwnerSegment($ownerType);

        return $this->post("/$segment/kyc-documents/$documentId/reject", [
            'reason' => trim($reason),
        ]);
    }

    public function changeActorKycLevel(string $ownerType, string $actorId, string $kycLevel): array
    {
        $segment = $this->actorOwnerSegment($ownerType);

        // ChangeActorKycLevelRequest carries only kycLevel — agents/merchants have no nextReviewDate.
        return $this->post("/$segment/$actorId/kyc-level", [
            'kycLevel' => strtoupper(trim($kycLevel)),
        ]);
    }

    public function activateActor(string $ownerType, string $actorId): array
    {
        $segment = $this->actorOwnerSegment($ownerType);

        return $this->post("/$segment/$actorId/activate");
    }

    // Agents
    public function agents(array $filters = []): array
    {
        return $this->agentsPage($filters)['data'];
    }

    public function agentsPage(array $filters = []): array
    {
        $page = $this->getPage('/agents', array_intersect_key($filters, array_flip(['cursor', 'limit', 'status'])));

        $page['data'] = $this->searchRows($page['data'], (string) ($filters['search'] ?? ''), [
            'id',
            'externalRef',
            'fullName',
            'phoneNumber',
            'zone',
        ]);

        return $page;
    }

    public function agent(string $id): ?array
    {
        return $this->getOne("/agents/$id");
    }

    public function createAgent(array $payload): array
    {
        return $this->post('/agents', $this->cleanPayload([
            'fullName' => $this->stringValue($payload, 'fullName'),
            'phoneCountryCode' => $this->phoneCountryCodeValue($payload, 'phoneCountryCode'),
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

    public function resetAgentAuthPin(string $id): array
    {
        return $this->post("/agents/$id/auth-pin/reset");
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
        return $this->merchantsPage($filters)['data'];
    }

    public function merchantsPage(array $filters = []): array
    {
        $page = $this->getPage('/merchants', array_intersect_key($filters, array_flip(['cursor', 'limit', 'status'])));

        $page['data'] = $this->searchRows($page['data'], (string) ($filters['search'] ?? ''), [
            'id',
            'externalRef',
            'businessName',
            'legalName',
            'phoneNumber',
            'taxId',
        ]);

        return $page;
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
            'phoneCountryCode' => $this->phoneCountryCodeValue($payload, 'phoneCountryCode'),
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

    public function resetMerchantAuthPin(string $id): array
    {
        return $this->post("/merchants/$id/auth-pin/reset");
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
        return $this->transactionsPage($filters)['data'];
    }

    public function transactionsPage(array $filters = []): array
    {
        return $this->getPage('/transactions', $filters);
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
        return $this->approvalsPage($filters)['data'];
    }

    public function approvalsPage(array $filters = []): array
    {
        $page = $this->getPage('/approvals', array_intersect_key($filters, array_flip(['cursor', 'limit', 'pendingOnly'])));

        $page['data'] = $this->filterRows($page['data'], array_intersect_key($filters, array_flip(['type'])));

        return $page;
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
        return $this->auditEventsPage($filters)['data'];
    }

    public function auditEventsPage(array $filters = []): array
    {
        if (array_key_exists('from', $filters)) {
            $filters['from'] = $this->dateQueryToInstant($filters['from'], '00:00:00');
        }

        if (array_key_exists('to', $filters)) {
            $filters['to'] = $this->dateQueryToInstant($filters['to'], '23:59:59');
        }

        return $this->getPage('/audit', $filters);
    }

    // Backoffice users
    public function backofficeUsers(array $filters = []): array
    {
        return $this->backofficeUsersPage($filters)['data'];
    }

    public function backofficeUsersPage(array $filters = []): array
    {
        return $this->getPage('/users', array_intersect_key($filters, array_flip(['cursor', 'limit'])));
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
        return $this->limitProfilesPage()['data'];
    }

    public function limitProfilesPage(array $filters = []): array
    {
        return $this->getPage('/limit-profiles', array_intersect_key($filters, array_flip(['cursor', 'limit'])));
    }

    public function limitProfile(string $id): ?array
    {
        return $this->getOne("/limit-profiles/$id");
    }

    public function feeRules(array $filters = []): array
    {
        return $this->feeRulesPage($filters)['data'];
    }

    public function feeRulesPage(array $filters = []): array
    {
        $query = array_intersect_key($filters, array_flip(['cursor', 'limit']));
        $page = $this->getPage('/fee-rules', $query);
        $page['data'] = $this->filterRows($page['data'], array_diff_key($filters, array_flip(['cursor', 'limit'])));

        return $page;
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
        return $this->commissionRulesPage($filters)['data'];
    }

    public function commissionRulesPage(array $filters = []): array
    {
        $query = array_intersect_key($filters, array_flip(['cursor', 'limit']));
        $page = $this->getPage('/commission-rules', $query);
        $page['data'] = $this->filterRows($page['data'], array_diff_key($filters, array_flip(['cursor', 'limit'])));

        return $page;
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
        return $this->controlThresholdsPage($filters)['data'];
    }

    public function controlThresholdsPage(array $filters = []): array
    {
        $query = array_intersect_key($filters, array_flip(['cursor', 'limit']));
        $page = $this->getPage('/control-thresholds', $query);
        $page['data'] = $this->filterRows($page['data'], array_diff_key($filters, array_flip(['cursor', 'limit'])));

        return $page;
    }

    public function controlThreshold(string $id): ?array
    {
        return $this->getOne("/control-thresholds/$id");
    }

    public function createControlThreshold(array $payload): array
    {
        return $this->post('/control-thresholds', $this->controlThresholdPayload($payload));
    }

    public function supersedeFeeRule(string $id, array $payload): array
    {
        return $this->post("/fee-rules/$id/supersede", $this->feeRulePayload($payload));
    }

    public function supersedeCommissionRule(string $id, array $payload): array
    {
        return $this->post("/commission-rules/$id/supersede", $this->commissionRulePayload($payload));
    }

    public function supersedeLimitProfile(string $id, array $payload): array
    {
        return $this->post("/limit-profiles/$id/supersede", $this->limitProfilePayload($payload));
    }

    public function supersedeControlThreshold(string $id, array $payload): array
    {
        return $this->post("/control-thresholds/$id/supersede", $this->controlThresholdPayload($payload));
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
        return $this->commissionSettlementRunsPage($filters)['data'];
    }

    public function commissionSettlementRunsPage(array $filters = []): array
    {
        return $this->getPage('/commission-settlements/runs', $filters);
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
        return $this->cardsPage($filters)['data'];
    }

    public function cardsPage(array $filters = []): array
    {
        $query = [];
        foreach (['customerId', 'cursor', 'limit'] as $key) {
            if (array_key_exists($key, $filters)) {
                $query[$key] = $filters[$key];
            }
        }

        $page = $this->getPage('/cards', $query);
        $page['data'] = $this->filterRows($page['data'], array_intersect_key($filters, array_flip(['status', 'cardType'])));

        return $page;
    }

    public function card(string $id): ?array
    {
        return $this->getOne("/cards/$id");
    }

    public function cardStock(array $filters = []): array
    {
        return $this->cardStockPage($filters)['data'];
    }

    public function cardStockPage(array $filters = []): array
    {
        return $this->getPage('/card-stock', $filters);
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
        return $this->terminalsPage($filters)['data'];
    }

    public function terminalsPage(array $filters = []): array
    {
        $query = [];
        foreach (['merchantId', 'cursor', 'limit'] as $key) {
            if (array_key_exists($key, $filters)) {
                $query[$key] = $filters[$key];
            }
        }

        $page = $this->getPage('/terminals', $query);
        $page['data'] = $this->filterRows($page['data'], array_intersect_key($filters, array_flip(['status'])));

        return $page;
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
        return $this->post('/service-providers', $this->serviceProviderPayload($payload, true));
    }

    public function updateServiceProvider(string $id, array $payload): array
    {
        return $this->put("/service-providers/$id", $this->serviceProviderPayload($payload, false));
    }

    public function activateServiceProvider(string $id): array
    {
        return $this->post("/service-providers/$id/activate");
    }

    public function deactivateServiceProvider(string $id): array
    {
        return $this->post("/service-providers/$id/deactivate");
    }

    public function changeServiceProviderStatus(string $id, string $status, string $reason = ''): array
    {
        // Direct operational control (spec §5.20): PATCH applies immediately and returns the
        // updated provider with 200 — no maker-checker approval.
        return $this->patch("/service-providers/$id/status", $this->cleanPayload([
            'status' => strtoupper(trim($status)),
            'reason' => $this->optionalStringValue(['reason' => $reason], 'reason'),
        ]));
    }

    public function updateServiceProviderBusinessRules(string $id, array $payload): array
    {
        // All fields are optional — only the supplied ones change (spec §6.12). Empty values are
        // dropped so the operator can edit one rule without resetting the rest.
        return $this->patch("/service-providers/$id/business-rules", $this->cleanPayload([
            'processingHoursStart' => $this->optionalStringValue($payload, 'processingHoursStart'),
            'processingHoursEnd' => $this->optionalStringValue($payload, 'processingHoursEnd'),
            'processingDays' => $this->optionalStringValue($payload, 'processingDays'),
            'announcedDelayHours' => $this->optionalLongValue($payload, 'announcedDelayHours'),
            'referenceRegex' => $this->optionalStringValue($payload, 'referenceRegex'),
            'referenceMinLength' => $this->optionalLongValue($payload, 'referenceMinLength'),
            'referenceMaxLength' => $this->optionalLongValue($payload, 'referenceMaxLength'),
            'referenceExample' => $this->optionalStringValue($payload, 'referenceExample'),
        ]));
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
        $providerId = trim($providerId);

        return $this->post("/service-providers/$providerId/services", $this->billServicePayload($payload, true, $providerId));
    }

    public function updateBillService(string $providerId, string $serviceId, array $payload): array
    {
        return $this->put("/service-providers/$providerId/services/$serviceId", $this->billServicePayload($payload, false));
    }

    public function activateBillService(string $providerId, string $serviceId): array
    {
        return $this->post("/service-providers/$providerId/services/$serviceId/activate");
    }

    public function deactivateBillService(string $providerId, string $serviceId): array
    {
        return $this->post("/service-providers/$providerId/services/$serviceId/deactivate");
    }

    private function serviceProviderPayload(array $payload, bool $creating): array
    {
        // Spec §6.12: the online-adapter fields (type, baseUrl, credentialsRef, callbackSecretRef,
        // timeoutMillis, maxRetries, retryBackoffMillis, sandbox) no longer exist in BO payloads
        // and must not be sent. Create/Update carry only identity + the reference-validation flag.
        $mapped = [
            'name' => $this->stringValue($payload, 'name'),
        ];

        if ($creating) {
            $mapped['code'] = $this->stringValue($payload, 'code');
        }

        $mapped['supportsReferenceValidation'] = (bool) ($payload['supportsReferenceValidation'] ?? false);

        return $this->cleanPayload($mapped);
    }

    private function billServicePayload(array $payload, bool $creating, ?string $providerId = null): array
    {
        $mapped = [
            'name' => $this->stringValue($payload, 'name'),
            'category' => $this->enumValue($payload, 'category'),
            'minAmount' => $this->optionalLongValue($payload, 'minAmount'),
            'maxAmount' => $this->optionalLongValue($payload, 'maxAmount'),
        ];

        if ($creating) {
            $mapped = [
                'providerId' => $providerId ?? $this->stringValue($payload, 'providerId'),
                'name' => $mapped['name'],
                'code' => $this->stringValue($payload, 'code'),
                'category' => $mapped['category'],
                'minAmount' => $mapped['minAmount'],
                'maxAmount' => $mapped['maxAmount'],
            ];
        }

        return $this->cleanPayload($mapped);
    }

    // Bill-payment processing (operator worklist, spec §5.21)
    public function billPaymentProcessingEnabled(): bool
    {
        // Probe one read endpoint: when komopay.billpay.enabled is false the whole
        // /bill-payments/** tree returns 404 (feature disabled, not "not found").
        try {
            $this->request('GET', '/bill-payments', ['query' => ['size' => 1]]);

            return true;
        } catch (BackofficeApiException $e) {
            if ($e->status === 404) {
                return false;
            }

            // 401/403/etc. are not "feature disabled" — let the caller see them.
            throw $e;
        }
    }

    public function billPayments(array $filters = []): array
    {
        return $this->billPaymentsPage($filters)['data'];
    }

    public function billPaymentsPage(array $filters = []): array
    {
        // List is page/size based; the UI displays each page newest first.
        $query = $this->billPaymentQuery($filters);

        return $this->getPage('/bill-payments', $query);
    }

    public function billPayment(string $id): ?array
    {
        return $this->getOne("/bill-payments/$id");
    }

    public function takeBillPayment(string $id): array
    {
        return $this->post("/bill-payments/$id/take");
    }

    public function releaseBillPayment(string $id): array
    {
        return $this->post("/bill-payments/$id/release");
    }

    public function completeBillPayment(string $id, array $payload, \Illuminate\Http\UploadedFile $file, ?string $secondApproverOperatorId = null): array
    {
        // multipart/form-data: mandatory proof file + provider reference (spec §5.21 "Complete").
        // The backend byte-sniffs the file, so the declared MIME/extension are not trusted.
        $parts = [
            ['name' => 'externalReference', 'contents' => trim((string) ($payload['externalReference'] ?? ''))],
            [
                'name' => 'file',
                'contents' => file_get_contents($file->getRealPath()),
                'filename' => $file->getClientOriginalName(),
                'headers' => ['Content-Type' => $file->getMimeType() ?: 'application/octet-stream'],
            ],
        ];

        $notes = trim((string) ($payload['internalNotes'] ?? ''));
        if ($notes !== '') {
            $parts[] = ['name' => 'internalNotes', 'contents' => $notes];
        }

        // 4-eyes header, only meaningful at/above the threshold; harmless below it.
        $headers = [];
        $secondApproverOperatorId = $secondApproverOperatorId !== null ? trim($secondApproverOperatorId) : '';
        if ($secondApproverOperatorId !== '') {
            $headers['X-Second-Approver-Operator-Id'] = $secondApproverOperatorId;
        }

        return $this->multipart("/bill-payments/$id/complete", $parts, $headers);
    }

    public function refundBillPayment(string $id, string $reason, ?\Illuminate\Http\UploadedFile $file = null): array
    {
        // multipart/form-data: required reason, optional proof file (spec §5.21 "Refund").
        $parts = [
            ['name' => 'reason', 'contents' => trim($reason)],
        ];

        if ($file !== null) {
            $parts[] = [
                'name' => 'file',
                'contents' => file_get_contents($file->getRealPath()),
                'filename' => $file->getClientOriginalName(),
                'headers' => ['Content-Type' => $file->getMimeType() ?: 'application/octet-stream'],
            ];
        }

        return $this->multipart("/bill-payments/$id/refund", $parts);
    }

    public function requeueBillPayment(string $id, string $reason): array
    {
        return $this->post("/bill-payments/$id/requeue", ['reason' => trim($reason)]);
    }

    public function forceReleaseBillPayment(string $id, string $reason): array
    {
        return $this->post("/bill-payments/$id/force-release", ['reason' => trim($reason)]);
    }

    public function downloadBillPaymentProof(string $id): array
    {
        $response = $this->binaryRequest('GET', "/bill-payments/$id/proof");
        $disposition = (string) $response->header('Content-Disposition');
        $filename = "bill-payment-proof-$id.bin";

        if (preg_match('/filename="?([^";]+)"?/i', $disposition, $matches) === 1) {
            $filename = trim($matches[1]);
        }

        return [
            'contentType' => $response->header('Content-Type') ?: 'application/octet-stream',
            'filename' => $filename,
            'body' => $response->body(),
        ];
    }

    private function billPaymentQuery(array $filters): array
    {
        return [
            'status' => $this->optionalEnumValue($filters, 'status'),
            'providerId' => $this->optionalStringValue($filters, 'providerId'),
            'customerId' => $this->optionalStringValue($filters, 'customerId'),
            'minAmount' => $this->optionalUnsignedIntegerQueryValue($filters['minAmount'] ?? null),
            'maxAmount' => $this->optionalUnsignedIntegerQueryValue($filters['maxAmount'] ?? null),
            'fromDate' => $this->dateQueryToInstant($filters['fromDate'] ?? null, '00:00:00'),
            'toDate' => $this->dateQueryToInstant($filters['toDate'] ?? null, '23:59:59'),
            'page' => $filters['page'] ?? $filters['cursor'] ?? null,
            'size' => $filters['size'] ?? $filters['limit'] ?? null,
        ];
    }

    /**
     * POST a multipart/form-data body. A dedicated client is used because the shared
     * client()'s asJson() body format would otherwise override the multipart encoding.
     *
     * @param  array<int, array<string, mixed>>  $parts
     * @param  array<string, string>  $headers
     */
    private function multipart(string $path, array $parts, array $headers = []): array
    {
        $client = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asMultipart()
            ->timeout((int) config('komopay.timeout', 15));

        if ($token = $this->bearerToken()) {
            $client = $client->withToken($token);
        }

        if ($headers !== []) {
            $client = $client->withHeaders($headers);
        }

        try {
            $response = $client->post($path, $parts);
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

        $body = $response->json();

        if (is_array($body) && isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }

        return is_array($body) ? $body : [];
    }

    // Reconciliation
    public function reconciliationIncidents(array $filters = []): array
    {
        return $this->reconciliationIncidentsPage($filters)['data'];
    }

    public function reconciliationIncidentsPage(array $filters = []): array
    {
        return $this->getPage('/reconciliation/incidents', $filters);
    }

    public function reconciliationIncident(string $id): ?array
    {
        return $this->getOne("/reconciliation/incidents/$id");
    }

    public function reconciliationRuns(array $filters = []): array
    {
        return $this->reconciliationRunsPage($filters)['data'];
    }

    public function reconciliationRunsPage(array $filters = []): array
    {
        $query = array_intersect_key($filters, array_flip(['cursor', 'limit']));
        $page = $this->getPage('/reconciliation/runs', $query);

        $page['data'] = $this->filterRows($page['data'], array_diff_key($filters, array_flip(['cursor', 'limit'])));

        return $page;
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
        return $this->amlLargeTransactionsPage($filters)['data'];
    }

    public function amlLargeTransactionsPage(array $filters = []): array
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

        return $this->getPage('/reports/aml/large-transactions', $query);
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
        return $this->reportExportsPage($filters)['data'];
    }

    public function reportExportsPage(array $filters = []): array
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

        return $this->getPage('/reports/exports', $query);
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
