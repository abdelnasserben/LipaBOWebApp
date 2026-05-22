<?php

use Livewire\Component;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use App\Enums\Backoffice\BillPaymentStatus;
use App\Exceptions\BackofficeApiException;
use App\Livewire\Concerns\UsesBackofficeEnums;
use App\Livewire\Concerns\WithApiCursorPagination;
use App\Services\Api\UsesBackofficeApi;
use App\Support\BackofficeEnums;

/**
 * Bill-Payment Processing — Operator Worklist (spec §5.21).
 *
 * Manual/deferred model: a customer payment is held + queued; an operator takes it,
 * executes on the provider's platform, uploads proof, and completes (settling the funds).
 * Every action applies directly (200) — this is NOT a maker-checker flow. The only
 * second-approver step is the in-line 4-eyes on complete above the threshold.
 *
 * The whole feature is gated by the upstream flag komopay.billpay.enabled: while it is
 * false, every /bill-payments/** route returns 404 — treated here as "feature disabled".
 */
new class extends Component {
    use UsesBackofficeApi;
    use UsesBackofficeEnums;
    use WithApiCursorPagination;
    use WithFileUploads;

    // 4-eyes threshold (komopay.billpay.four-eyes-threshold-kmf, default 100 000 KMF).
    private const FOUR_EYES_THRESHOLD_KMF = 100000;

    #[Url(as: 'status')]
    public string $statusFilter = '';
    public string $providerFilter = '';
    public string $customerFilter = '';
    public ?int $minAmount = null;
    public ?int $maxAmount = null;
    public string $fromDate = '';
    public string $toDate = '';

    public ?array $selected = null;

    // Complete form (multipart).
    public bool $showCompleteModal = false;
    public string $externalReference = '';
    public string $internalNotes = '';
    public $proofFile = null;
    public string $secondApproverOperatorId = '';

    // Refund form (multipart).
    public bool $showRefundModal = false;
    public string $refundReason = '';
    public $refundFile = null;

    // Reason-only actions (requeue / force-release).
    public bool $showReasonModal = false;
    public string $reasonAction = ''; // 'requeue' | 'force-release'
    public string $reason = '';

    public string $notification = '';
    public string $notificationType = 'success';

    public function hasPermission(string $permission): bool
    {
        $permissions = session('bo_user.permissions', []);

        return is_array($permissions) && in_array($permission, $permissions, true);
    }

    public function currentOperatorId(): string
    {
        return (string) session('bo_user.id', '');
    }

    public function paymentStatus(?array $payment = null): string
    {
        $payment ??= $this->selected ?? [];

        return strtoupper(trim((string) ($payment['status'] ?? '')));
    }

    public function isAssignedToCurrentOperator(?array $payment = null): bool
    {
        $payment ??= $this->selected ?? [];

        if ($this->paymentStatus($payment) !== BillPaymentStatus::IN_PROCESSING->value) {
            return false;
        }

        $operatorId = $this->currentOperatorId();

        return $operatorId !== '' && $this->sameOperatorId($this->paymentOwnerId($payment), $operatorId);
    }

    public function isAssignedToAnotherOperator(?array $payment = null): bool
    {
        $payment ??= $this->selected ?? [];

        if ($this->paymentStatus($payment) !== BillPaymentStatus::IN_PROCESSING->value) {
            return false;
        }

        $ownerId = $this->paymentOwnerId($payment);

        return $ownerId !== '' && ! $this->sameOperatorId($ownerId, $this->currentOperatorId());
    }

    private function paymentOwnerId(array $payment): string
    {
        $assignment = $payment['assignment'] ?? null;

        if (is_array($assignment) && trim((string) ($assignment['operatorId'] ?? '')) !== '') {
            return trim((string) $assignment['operatorId']);
        }

        return trim((string) ($payment['processedByOperatorId'] ?? ''));
    }

    private function sameOperatorId(string $left, string $right): bool
    {
        return strtolower(trim($left)) === strtolower(trim($right));
    }

    private function normalizeBillPaymentPayload(array $payload): array
    {
        if (! isset($payload['id']) && isset($payload['billPaymentId'])) {
            $payload['id'] = $payload['billPaymentId'];
        }

        if (! isset($payload['proofRef']) && isset($payload['proofId'])) {
            $payload['proofRef'] = $payload['proofId'];
        }

        $status = $this->paymentStatus($payload);
        if ($status !== '' && $status !== BillPaymentStatus::IN_PROCESSING->value && ! array_key_exists('assignment', $payload)) {
            $payload['assignment'] = null;
        }

        return $payload;
    }

    private function markActionOwnedByCurrentOperator(array $payment): array
    {
        if ($this->paymentStatus($payment) !== BillPaymentStatus::IN_PROCESSING->value) {
            return $payment;
        }

        $operatorId = $this->currentOperatorId();
        if ($operatorId === '') {
            return $payment;
        }

        if (trim((string) ($payment['processedByOperatorId'] ?? '')) === '') {
            $payment['processedByOperatorId'] = $operatorId;
        }

        $assignment = $payment['assignment'] ?? [];
        $assignment = is_array($assignment) ? $assignment : [];

        if (trim((string) ($assignment['operatorId'] ?? '')) === '') {
            $assignment['operatorId'] = $operatorId;
        }

        if (trim((string) ($assignment['status'] ?? '')) === '') {
            $assignment['status'] = 'ACTIVE';
        }

        $payment['assignment'] = $assignment;

        return $payment;
    }

    private function notify(string $message, string $type = 'success'): void
    {
        $this->notification = $message;
        $this->notificationType = $type;
    }

    private function clearNotification(): void
    {
        $this->notification = '';
        $this->notificationType = 'success';
    }

    public function updated($name): void
    {
        // Any filter change rewinds to the first page.
        if (in_array($name, ['statusFilter', 'providerFilter', 'customerFilter', 'minAmount', 'maxAmount', 'fromDate', 'toDate'], true)) {
            $this->resetCursorPage('bill-payments');
        }
    }

    public function clearFilters(): void
    {
        $this->statusFilter = '';
        $this->providerFilter = '';
        $this->customerFilter = '';
        $this->minAmount = null;
        $this->maxAmount = null;
        $this->fromDate = '';
        $this->toDate = '';
        $this->resetCursorPage('bill-payments');
    }

    public function select(string $id): void
    {
        $this->clearNotification();

        try {
            $this->selected = $this->api()->billPayment($id);
        } catch (BackofficeApiException $e) {
            $this->handle($e);
        }
    }

    public function closeDrawer(): void
    {
        $this->selected = null;
        $this->showCompleteModal = false;
        $this->showRefundModal = false;
        $this->showReasonModal = false;
    }

    private function refreshSelected(?array $fallback = null): void
    {
        if (!$this->selected) {
            return;
        }

        try {
            $fresh = $this->api()->billPayment($this->selected['id']);
            $this->selected = $this->mergeSelectedPayment($fresh, $fallback);
        } catch (BackofficeApiException) {
            $this->selected = $this->mergeSelectedPayment(null, $fallback);
        }
    }

    private function refreshSelectedAfterAction(string $id, array $actionResult): void
    {
        if (!$this->selected || ($this->selected['id'] ?? null) !== $id) {
            return;
        }

        try {
            $fresh = $this->api()->billPayment($id);
            $this->selected = $this->mergeSelectedPayment($fresh, $actionResult);
        } catch (BackofficeApiException) {
            $this->selected = $this->mergeSelectedPayment(null, $actionResult);
        }
    }

    private function mergeSelectedPayment(?array $fresh, ?array $fallback = null): array
    {
        $current = $this->selected ?? [];
        $fresh = is_array($fresh) ? $this->normalizeBillPaymentPayload($fresh) : [];
        $fallback = is_array($fallback) ? $this->normalizeBillPaymentPayload($fallback) : [];

        return array_replace($current, $fresh, $fallback);
    }

    private function sortBillPaymentsDescending(array $rows): array
    {
        usort($rows, fn (array $a, array $b): int => strcmp(
            (string) ($b['createdAt'] ?? $b['queuedAt'] ?? ''),
            (string) ($a['createdAt'] ?? $a['queuedAt'] ?? ''),
        ));

        return $rows;
    }

    // ── Assignment lock ────────────────────────────────────────────────────

    public function take(string $id): void
    {
        $this->run(fn () => $this->markActionOwnedByCurrentOperator($this->api()->takeBillPayment($id)), 'Payment taken. You hold it for 30 minutes.', $id);
    }

    public function release(string $id): void
    {
        $this->run(fn () => $this->api()->releaseBillPayment($id), 'Assignment released. The payment is back in the queue.', $id);
    }

    public function openReasonModal(string $action): void
    {
        $this->clearNotification();
        $this->resetValidation();
        $this->reasonAction = $action;
        $this->reason = '';
        $this->showReasonModal = true;
    }

    public function confirmReasonAction(): void
    {
        if (!$this->selected) {
            return;
        }

        $this->validate(['reason' => 'required|string|max:500']);
        $id = $this->selected['id'];

        if ($this->reasonAction === 'requeue') {
            $this->run(fn () => $this->api()->requeueBillPayment($id, $this->reason), 'Payment requeued. The funds stay held.', $id);
        } elseif ($this->reasonAction === 'force-release') {
            $this->run(fn () => $this->api()->forceReleaseBillPayment($id, $this->reason), 'Assignment force-released. The payment is back in the queue.', $id);
        }

        if ($this->notificationType === 'success') {
            $this->showReasonModal = false;
        }
    }

    // ── Complete (settle funds) ──────────────────────────────────────────────

    public function openCompleteModal(): void
    {
        $this->clearNotification();
        $this->resetValidation();
        $this->externalReference = '';
        $this->internalNotes = '';
        $this->proofFile = null;
        $this->secondApproverOperatorId = '';
        $this->showCompleteModal = true;
    }

    public function requiresSecondApprover(): bool
    {
        return (int) ($this->selected['heldAmount'] ?? 0) >= self::FOUR_EYES_THRESHOLD_KMF;
    }

    public function fourEyesThreshold(): int
    {
        return self::FOUR_EYES_THRESHOLD_KMF;
    }

    public function complete(): void
    {
        if (!$this->selected) {
            return;
        }

        $rules = [
            'externalReference' => 'required|string|max:120',
            'internalNotes' => 'nullable|string|max:1000',
            'proofFile' => ['required', 'file', 'max:10240', 'mimes:jpeg,jpg,png,pdf'],
        ];

        // 4-eyes above threshold: a different operator id is mandatory (server is authoritative).
        if ($this->requiresSecondApprover()) {
            $rules['secondApproverOperatorId'] = 'required|string';
        }

        $this->validate($rules, [
            'proofFile.required' => 'A proof file is mandatory to complete a payment.',
            'proofFile.mimes' => 'Proof must be a JPEG, PNG, or PDF.',
            'proofFile.max' => 'Proof must be 10 MB or smaller.',
            'secondApproverOperatorId.required' => 'A second approver is required above ' . number_format(self::FOUR_EYES_THRESHOLD_KMF, 0, ',', ' ') . ' KMF.',
        ]);

        $id = $this->selected['id'];
        $secondApprover = $this->requiresSecondApprover() ? trim($this->secondApproverOperatorId) : null;

        try {
            $result = $this->api()->completeBillPayment($id, [
                'externalReference' => $this->externalReference,
                'internalNotes' => $this->internalNotes,
            ], $this->proofFile, $secondApprover);

            $this->refreshSelectedAfterAction($id, $result);
            $this->showCompleteModal = false;
            $this->notify('Payment completed and funds settled.');
        } catch (BackofficeApiException $e) {
            $this->handle($e);
        }
    }

    // ── Refund ────────────────────────────────────────────────────────────

    public function openRefundModal(): void
    {
        $this->clearNotification();
        $this->resetValidation();
        $this->refundReason = '';
        $this->refundFile = null;
        $this->showRefundModal = true;
    }

    public function refund(): void
    {
        if (!$this->selected) {
            return;
        }

        $this->validate([
            'refundReason' => 'required|string|max:500',
            'refundFile' => ['nullable', 'file', 'max:10240', 'mimes:jpeg,jpg,png,pdf'],
        ], [
            'refundFile.mimes' => 'Proof must be a JPEG, PNG, or PDF.',
            'refundFile.max' => 'Proof must be 10 MB or smaller.',
        ]);

        $id = $this->selected['id'];

        try {
            $result = $this->api()->refundBillPayment($id, $this->refundReason, $this->refundFile);
            $this->refreshSelectedAfterAction($id, $result);
            $this->showRefundModal = false;
            $this->notify('Payment refunded. The hold was released and the customer reimbursed.');
        } catch (BackofficeApiException $e) {
            $this->handle($e);
        }
    }

    /**
     * Run a one-shot action that returns the updated payment, then refresh the drawer.
     */
    private function run(callable $action, string $successMessage, string $id): void
    {
        $this->clearNotification();

        try {
            $result = $action();
            $this->refreshSelectedAfterAction($id, $result);
            $this->notify($successMessage);
        } catch (BackofficeApiException $e) {
            $this->handle($e);
        }
    }

    private function handle(BackofficeApiException $e): void
    {
        if ($e->status === 401) {
            throw $e; // session expiry is handled centrally by UsesBackofficeApi::exception()
        }

        // Several errors mean the row moved underneath us — refresh so the operator sees the truth.
        if (in_array($e->errorCode, [
            'BILL_PAYMENT_ALREADY_ASSIGNED',
            'BILL_PAYMENT_OPERATOR_MISMATCH',
            'BILL_PAYMENT_INVALID_TRANSITION',
            'BILL_PAYMENT_ASSIGNMENT_NOT_FOUND',
            'BILL_PAYMENT_ASSIGNMENT_NOT_ACTIVE',
        ], true)) {
            $this->refreshSelected();
        }

        $this->notify($e->userMessage(), 'danger');
    }

    public function render(): \Illuminate\View\View
    {
        // Feature flag: probe one read endpoint. 404 => feature disabled, hide the whole section.
        try {
            $enabled = $this->api()->billPaymentProcessingEnabled();
        } catch (BackofficeApiException $e) {
            if ($e->status === 401) {
                throw $e;
            }
            $enabled = false;
        }

        if (!$enabled) {
            return view('livewire.bill-payments.bill-payment-worklist', [
                'enabled' => false,
                'rows' => [],
                'paginator' => null,
                'statusOptions' => [],
                'providerNames' => [],
                'providers' => [],
            ]);
        }

        $providers = $this->api()->serviceProviders();
        $providerNames = collect($providers)->mapWithKeys(fn ($p) => [$p['id'] => $p['name']])->all();

        $filters = [
            'status' => $this->statusFilter ?: null,
            'providerId' => $this->providerFilter ?: null,
            'customerId' => trim($this->customerFilter) ?: null,
            'minAmount' => $this->minAmount,
            'maxAmount' => $this->maxAmount,
            'fromDate' => $this->fromDate ?: null,
            'toDate' => $this->toDate ?: null,
        ];

        $page = $this->api()->billPaymentsPage($filters + $this->cursorPageQuery('bill-payments'));
        $rows = $this->sortBillPaymentsDescending($page['data']);
        $paginator = $this->cursorPaginator('bill-payments', $page, count($rows), 'payments');

        return view('livewire.bill-payments.bill-payment-worklist', [
            'enabled' => true,
            'rows' => $rows,
            'paginator' => $paginator,
            'statusOptions' => BackofficeEnums::options(BillPaymentStatus::class),
            'providerNames' => $providerNames,
            'providers' => $providers,
        ]);
    }
};
?>

<div>
    <x-page-header
        title="Bill Payments"
        subtitle="Operator worklist — manual processing of held customer bill payments (within 4 business hours)"
    />

    @if($notification)
        <div class="alert alert-{{ $notificationType }} mb-4">
            <x-icon name="{{ $notificationType === 'danger' ? 'alert-triangle' : 'check' }}" size="15" />
            {{ $notification }}
        </div>
    @endif

    @if(!$enabled)
        <div class="card">
            <div class="empty-state">
                <x-icon name="lock" size="20" />
                <div class="empty-state-title">Bill-payment processing is disabled</div>
                <div class="empty-state-text">This feature is turned off on the backend (<x-mono>komopay.billpay.enabled=false</x-mono>). It will appear here once enabled.</div>
            </div>
        </div>
    @else
        <div class="card">
            {{-- Filters --}}
            <div class="filter-bar flex-wrap">
                <select wire:model.live="statusFilter" class="filter-select">
                    <option value="">Queue (all)</option>
                    @foreach($statusOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <select wire:model.live="providerFilter" class="filter-select">
                    <option value="">All providers</option>
                    @foreach($providers as $provider)
                        <option value="{{ $provider['id'] }}">{{ $provider['name'] }}</option>
                    @endforeach
                </select>
                <input wire:model.live.debounce.400ms="customerFilter" type="text" class="filter-select" placeholder="Customer ID" />
                <input wire:model.live.debounce.400ms="minAmount" type="number" min="1" class="filter-select" placeholder="Min held" />
                <input wire:model.live.debounce.400ms="maxAmount" type="number" min="1" class="filter-select" placeholder="Max held" />
                <input wire:model.live="fromDate" type="date" class="filter-select" />
                <input wire:model.live="toDate" type="date" class="filter-select" />
                <button class="btn btn-ghost btn-sm" wire:click="clearFilters">Clear</button>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Queued</th>
                            <th>Customer</th>
                            <th>Provider</th>
                            <th>Reference</th>
                            <th>Held</th>
                            <th>Assigned to</th>
                            <th>Retry</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="table-row-link" wire:click="select('{{ $row['id'] }}')">
                                <td><x-mono>{{ \Carbon\Carbon::parse($row['createdAt'])->format('d M, H:i') }}</x-mono></td>
                                <td><x-mono>{{ $row['customerId'] }}</x-mono></td>
                                <td><span class="text-xs font-medium">{{ $providerNames[$row['providerId']] ?? $row['providerId'] }}</span></td>
                                <td><x-mono>{{ $row['reference'] }}</x-mono></td>
                                <td><x-amount :value="$row['heldAmount']" size="12" /></td>
                                <td>
                                    @if(!empty($row['assignment']['operatorId']))
                                        <x-mono>{{ \Illuminate\Support\Str::limit($row['assignment']['operatorId'], 8, '…') }}</x-mono>
                                    @else
                                        <span class="text-[var(--text-tertiary)]">—</span>
                                    @endif
                                </td>
                                <td><x-mono>{{ $row['retryCount'] ?? 0 }}</x-mono></td>
                                <td><x-badge :status="$row['status']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><div class="empty-state"><div class="empty-state-title">No bill payments in this view</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($paginator)
                <x-cursor-pagination :paginator="$paginator" />
            @endif
        </div>
    @endif

    {{-- Detail drawer --}}
    @if($selected)
        @php
            $st = $this->paymentStatus($selected);
            $assignment = $selected['assignment'] ?? null;
            $assignedToMe = $this->isAssignedToCurrentOperator($selected);
            $assignedToOther = $this->isAssignedToAnotherOperator($selected);
        @endphp
        <div class="drawer-overlay" wire:click="closeDrawer"></div>
        <div class="drawer">
            <div class="drawer-header">
                <span class="drawer-title">Payment {{ \Illuminate\Support\Str::limit($selected['id'], 12, '…') }}</span>
                <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
            </div>
            <div class="drawer-body">
                <div class="mb-4 flex items-center gap-2">
                    <x-badge :status="$st" />
                    @if($assignedToMe)<span class="badge badge-active badge-no-dot">Assigned to you</span>@endif
                    @if($assignedToOther)<span class="badge badge-locked badge-no-dot">Locked by another operator</span>@endif
                </div>

                @if($assignment && ($assignment['status'] ?? '') === 'ACTIVE' && !empty($assignment['expiresAt']))
                    <div class="alert {{ \Carbon\Carbon::parse($assignment['expiresAt'])->isPast() ? 'alert-danger' : 'alert-warning' }} mb-4">
                        <x-icon name="activity" size="15" />
                        <span>
                            Lock {{ \Carbon\Carbon::parse($assignment['expiresAt'])->isPast() ? 'expired' : 'expires' }}
                            <strong>{{ \Carbon\Carbon::parse($assignment['expiresAt'])->diffForHumans() }}</strong>
                            ({{ \Carbon\Carbon::parse($assignment['expiresAt'])->format('H:i') }})
                        </span>
                    </div>
                @endif

                <div class="drawer-section">
                    <div class="drawer-section-title">Payment</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selected['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Customer</span><span class="drawer-field-value">{{ $selected['customerId'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Provider</span><span class="drawer-field-value">{{ $providerNames[$selected['providerId']] ?? $selected['providerId'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Service</span><span class="drawer-field-value">{{ $selected['serviceId'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Reference</span><span class="drawer-field-value">{{ $selected['reference'] }}</span></div>
                </div>

                <div class="drawer-section">
                    <div class="drawer-section-title">Amounts</div>
                    <div class="drawer-field"><span class="drawer-field-label">Requested</span><span class="drawer-field-value"><x-amount :value="$selected['requestedAmount']" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Fee</span><span class="drawer-field-value"><x-amount :value="$selected['feeAmount']" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Net</span><span class="drawer-field-value"><x-amount :value="$selected['netAmount']" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Held</span><span class="drawer-field-value"><x-amount :value="$selected['heldAmount']" size="12" /></span></div>
                    @if($this->requiresSecondApprover())
                        <div class="drawer-field"><span class="drawer-field-label">4-eyes</span><span class="drawer-field-value"><span class="badge badge-pending-approval badge-no-dot">Required on complete</span></span></div>
                    @endif
                </div>

                <div class="drawer-section">
                    <div class="drawer-section-title">Processing</div>
                    <div class="drawer-field"><span class="drawer-field-label">External Ref</span><span class="drawer-field-value">{{ $selected['externalReference'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Operator</span><span class="drawer-field-value">{{ $selected['processedByOperatorId'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">2nd Approver</span><span class="drawer-field-value">{{ $selected['secondApproverOperatorId'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Retry Count</span><span class="drawer-field-value">{{ $selected['retryCount'] ?? 0 }}</span></div>
                    @if(!empty($selected['internalNotes']))
                        <div class="drawer-field"><span class="drawer-field-label">Notes</span><span class="drawer-field-value">{{ $selected['internalNotes'] }}</span></div>
                    @endif
                </div>

                <div class="drawer-section">
                    <div class="drawer-section-title">Timeline</div>
                    <div class="drawer-field"><span class="drawer-field-label">Created</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['createdAt'])->format('d M Y, H:i') }}</span></div>
                    @if(!empty($selected['processingStartedAt']))
                        <div class="drawer-field"><span class="drawer-field-label">Started</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['processingStartedAt'])->format('d M Y, H:i') }}</span></div>
                    @endif
                    @if(!empty($selected['completedAt']))
                        <div class="drawer-field"><span class="drawer-field-label">Completed</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['completedAt'])->format('d M Y, H:i') }}</span></div>
                    @endif
                </div>

                {{-- Proof preview (read-only), spec §5.21 "Proof viewing" --}}
                @if(!empty($selected['proofRef']) && $this->hasPermission('BILL_PAYMENT_PROOF_VIEW'))
                    <div class="drawer-section">
                        <div class="drawer-section-title">Proof</div>
                        @php $proofUrl = route('bill-payments.proof', ['id' => $selected['id']]); @endphp
                        <a href="{{ $proofUrl }}" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">
                            <x-icon name="external-link" size="13" /> View proof
                        </a>
                    </div>
                @endif
            </div>

            {{-- Actions: gated by status AND permission. Buttons are HIDDEN, not disabled, when missing. --}}
            <div class="drawer-footer flex-wrap">
                @if($st === \App\Enums\Backoffice\BillPaymentStatus::QUEUED->value)
                    @if($this->hasPermission('BILL_PAYMENT_PROCESS'))
                        <button class="btn btn-primary btn-sm" wire:click="take('{{ $selected['id'] }}')" wire:loading.attr="disabled">Take</button>
                    @endif
                @elseif($st === \App\Enums\Backoffice\BillPaymentStatus::FAILED_RETRY->value)
                    {{-- Spec §5.21: FAILED_RETRY is an exceptional non-terminal row and is NOT accepted by take; no direct BO action. --}}
                    <span class="text-xs text-[var(--text-tertiary)]">Exceptional retry state — refresh or escalate. Not directly actionable.</span>
                @elseif($st === \App\Enums\Backoffice\BillPaymentStatus::IN_PROCESSING->value && $assignedToMe)
                    @if($this->hasPermission('BILL_PAYMENT_COMPLETE'))
                        <button class="btn btn-primary btn-sm" wire:click="openCompleteModal">Mark succeeded</button>
                    @endif
                    @if($this->hasPermission('BILL_PAYMENT_REFUND'))
                        <button class="btn btn-warning btn-sm" wire:click="openRefundModal">Fail + refund</button>
                    @endif
                    @if($this->hasPermission('BILL_PAYMENT_REQUEUE'))
                        <button class="btn btn-secondary btn-sm" wire:click="openReasonModal('requeue')">Retry later</button>
                    @endif
                    @if($this->hasPermission('BILL_PAYMENT_PROCESS'))
                        <button class="btn btn-secondary btn-sm" wire:click="release('{{ $selected['id'] }}')" wire:loading.attr="disabled">Release</button>
                    @endif
                @elseif($st === \App\Enums\Backoffice\BillPaymentStatus::IN_PROCESSING->value && $assignedToOther)
                    @if($this->hasPermission('BILL_PAYMENT_FORCE_RELEASE'))
                        <button class="btn btn-warning btn-sm" wire:click="openReasonModal('force-release')">Force-release</button>
                    @else
                        <span class="text-xs text-[var(--text-tertiary)]">Locked by another operator.</span>
                    @endif
                @else
                    <span class="text-xs text-[var(--text-tertiary)]">No actions available for this status.</span>
                @endif
            </div>
        </div>
    @endif

    {{-- Complete modal (multipart, mandatory proof, 4-eyes above threshold) --}}
    @if($showCompleteModal && $selected)
        <div class="modal-overlay" wire:click.self="$set('showCompleteModal', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">Complete Payment</span>
                    <button class="modal-close" wire:click="$set('showCompleteModal', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-4">
                        <x-icon name="alert-triangle" size="15" />
                        <span>This settles the held funds (<x-amount :value="$selected['heldAmount']" size="12" />) for good. Upload the provider receipt as proof.</span>
                    </div>
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="form-label">Provider Transaction Reference <span class="form-required">*</span></label>
                            <input wire:model="externalReference" type="text" class="form-input is-mono" placeholder="e.g. MWE-2026-778812" />
                            @error('externalReference') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Proof File <span class="form-required">*</span></label>
                            <input type="file" wire:model="proofFile" class="form-input" accept=".jpg,.jpeg,.png,.pdf" />
                            <div wire:loading wire:target="proofFile" class="form-hint">Uploading…</div>
                            <div class="form-hint">JPEG, PNG, or PDF · max 10 MB.</div>
                            @error('proofFile') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Internal Notes</label>
                            <textarea wire:model="internalNotes" class="form-input" rows="2" placeholder="Operator-only (optional)"></textarea>
                            @error('internalNotes') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        @if($this->requiresSecondApprover())
                            <div>
                                <label class="form-label">Second Approver Operator ID <span class="form-required">*</span></label>
                                <input wire:model="secondApproverOperatorId" type="text" class="form-input is-mono" placeholder="UUID of a different operator with completion rights" />
                                <div class="form-hint">Required: held amount ≥ {{ number_format($this->fourEyesThreshold(), 0, ',', ' ') }} KMF (4-eyes). Must be a different operator.</div>
                                @error('secondApproverOperatorId') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('showCompleteModal', false)">Cancel</button>
                    <button class="btn btn-primary btn-md" wire:click="complete" wire:loading.attr="disabled" wire:target="proofFile,complete">Complete &amp; Settle</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Refund modal (multipart, reason required, proof optional) --}}
    @if($showRefundModal && $selected)
        <div class="modal-overlay" wire:click.self="$set('showRefundModal', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">Refund Payment</span>
                    <button class="modal-close" wire:click="$set('showRefundModal', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-4">
                        <x-icon name="alert-triangle" size="15" />
                        <span>The hold is released and the customer is reimbursed atomically. This is final.</span>
                    </div>
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="form-label">Reason <span class="form-required">*</span></label>
                            <textarea wire:model="refundReason" class="form-input" rows="3" placeholder="Why is this payment being refunded?"></textarea>
                            @error('refundReason') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Proof File</label>
                            <input type="file" wire:model="refundFile" class="form-input" accept=".jpg,.jpeg,.png,.pdf" />
                            <div wire:loading wire:target="refundFile" class="form-hint">Uploading…</div>
                            <div class="form-hint">Optional · JPEG, PNG, or PDF · max 10 MB.</div>
                            @error('refundFile') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('showRefundModal', false)">Cancel</button>
                    <button class="btn btn-warning btn-md" wire:click="refund" wire:loading.attr="disabled" wire:target="refundFile,refund">Refund</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Reason-only modal (requeue / force-release) --}}
    @if($showReasonModal && $selected)
        <div class="modal-overlay" wire:click.self="$set('showReasonModal', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">{{ $reasonAction === 'requeue' ? 'Requeue Payment' : 'Force-release Assignment' }}</span>
                    <button class="modal-close" wire:click="$set('showReasonModal', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3 text-xs text-[var(--text-secondary)]">
                        @if($reasonAction === 'requeue')
                            The payment returns to the queue, the funds stay held, and the retry counter is incremented.
                        @else
                            This drops another operator's active assignment and returns the payment to the queue.
                        @endif
                    </p>
                    <div>
                        <label class="form-label">Reason <span class="form-required">*</span></label>
                        <textarea wire:model="reason" class="form-input" rows="3" placeholder="Justification (recorded)"></textarea>
                        @error('reason') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('showReasonModal', false)">Cancel</button>
                    <button class="btn btn-primary btn-md" wire:click="confirmReasonAction">Confirm</button>
                </div>
            </div>
        </div>
    @endif
</div>
