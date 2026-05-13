<?php

use Livewire\Component;
use App\Enums\Backoffice\KycLevel;
use App\Exceptions\BackofficeApiException;
use App\Livewire\Concerns\UsesBackofficeEnums;
use App\Services\Api\UsesBackofficeApi;
use App\Support\BackofficeEnums;

new class extends Component {
    use UsesBackofficeApi;
    use UsesBackofficeEnums;

    public string $customerId = '';

    public ?array $customer = null;
    /** @var array<int, array<string, mixed>> */
    public array $documents = [];
    public ?array $selectedDocument = null;

    public bool $showRejectModal = false;
    public string $rejectReason = '';

    public bool $showKycLevelForm = false;
    public string $newKycLevel = '';
    public string $kycNextReviewDate = '';

    public bool $showActivateConfirm = false;

    public string $notification = '';
    public string $notificationType = 'success';

    public function mount(string $customerId): void
    {
        $this->customerId = $customerId;
        $this->loadCustomer();
        $this->loadDocuments();
    }

    private function loadCustomer(): void
    {
        $this->customer = $this->api()->customer($this->customerId);
    }

    private function loadDocuments(): void
    {
        // GET /customers/{id}/kyc-documents — emits KYC_DOCUMENT_VIEWED on the server.
        $this->documents = $this->api()->customerKycDocuments($this->customerId);
    }

    public function selectDocument(string $documentId): void
    {
        // GET /kyc-documents/{documentId} — refreshes details and emits KYC_DOCUMENT_VIEWED.
        $summary = $this->findDocumentSummary($documentId);
        $details = $this->api()->kycDocument($documentId) ?? [];
        $this->selectedDocument = array_replace($summary, $details);
        $this->showRejectModal = false;
        $this->rejectReason = '';
    }

    private function findDocumentSummary(string $documentId): array
    {
        foreach ($this->documents as $document) {
            if ((string) ($document['id'] ?? '') === $documentId) {
                return $document;
            }
        }

        return [];
    }

    public function closeDocumentDrawer(): void
    {
        $this->selectedDocument = null;
        $this->showRejectModal = false;
        $this->rejectReason = '';
    }

    public function canView(): bool
    {
        return $this->hasPermission('CUSTOMER_KYC_DOCUMENT_VIEW');
    }

    public function canReview(): bool
    {
        return $this->hasPermission('CUSTOMER_KYC_DOCUMENT_REVIEW');
    }

    public function canUpdateKyc(): bool
    {
        return $this->hasPermission('ACTOR_KYC_UPDATE');
    }

    public function canActivate(): bool
    {
        return $this->hasPermission('ACTOR_ACTIVATE');
    }

    public function canWriteLimitProfiles(): bool
    {
        return $this->hasPermission('LIMIT_PROFILE_WRITE');
    }

    public function getSelectedDocumentPreviewModeProperty(): string
    {
        $contentType = $this->selectedDocumentContentType();

        if (in_array($contentType, ['image/jpeg', 'image/png'], true)) {
            return 'image';
        }

        if ($contentType === 'application/pdf') {
            return 'pdf';
        }

        return 'download';
    }

    public function selectedDocumentContentType(): string
    {
        $contentType = strtolower(trim((string) ($this->selectedDocument['contentType'] ?? '')));

        if ($contentType === '') {
            return 'application/octet-stream';
        }

        return trim(explode(';', $contentType, 2)[0]);
    }

    private function hasPermission(string $permission): bool
    {
        $permissions = session('bo_user.permissions', []);

        return is_array($permissions) && in_array($permission, $permissions, true);
    }

    public function approveDocument(): void
    {
        if (!$this->selectedDocument || !$this->canReview()) {
            $this->notify('You do not have permission to review KYC documents.', 'danger');
            return;
        }

        if (($this->selectedDocument['status'] ?? null) !== 'PENDING_REVIEW') {
            $this->notify('Only PENDING_REVIEW documents can be approved.', 'warning');
            return;
        }

        $updated = $this->api()->approveKycDocument((string) $this->selectedDocument['id']);
        $this->selectedDocument = $this->mergeDocument($updated);
        $this->loadDocuments();
        $this->notify('Document approved. Customer KYC level and status are unchanged.', 'success');
    }

    public function openRejectModal(): void
    {
        if (!$this->canReview()) {
            $this->notify('You do not have permission to review KYC documents.', 'danger');
            return;
        }

        $this->rejectReason = '';
        $this->resetValidation('rejectReason');
        $this->showRejectModal = true;
    }

    public function rejectDocument(): void
    {
        if (!$this->selectedDocument || !$this->canReview()) {
            $this->showRejectModal = false;
            $this->notify('You do not have permission to review KYC documents.', 'danger');
            return;
        }

        $this->validate(
            [
                // Spec §6.3a: reason required, non-blank, max 1000.
                'rejectReason' => ['required', 'string', 'min:1', 'max:1000'],
            ],
            [],
            ['rejectReason' => 'reason'],
        );

        if (($this->selectedDocument['status'] ?? null) !== 'PENDING_REVIEW') {
            $this->showRejectModal = false;
            $this->notify('Only PENDING_REVIEW documents can be rejected.', 'warning');
            return;
        }

        $updated = $this->api()->rejectKycDocument((string) $this->selectedDocument['id'], $this->rejectReason);
        $this->selectedDocument = $this->mergeDocument($updated);
        $this->loadDocuments();
        $this->showRejectModal = false;
        $this->rejectReason = '';
        $this->notify('Document rejected. The encrypted file is preserved for KYC retention.', 'success');
    }

    private function mergeDocument(array $updated): ?array
    {
        if ($this->selectedDocument === null) {
            return $updated === [] ? null : $updated;
        }

        return $updated === [] ? $this->selectedDocument : array_replace($this->selectedDocument, $updated);
    }

    public function openKycLevelForm(): void
    {
        if (!$this->canUpdateKyc()) {
            $this->notify('You do not have permission to change KYC level.', 'danger');
            return;
        }

        $this->newKycLevel = (string) ($this->customer['kycLevel'] ?? '');
        $this->kycNextReviewDate = '';
        $this->showKycLevelForm = true;
    }

    public function submitKycLevel(): void
    {
        if (!$this->canUpdateKyc()) {
            $this->notify('You do not have permission to change KYC level.', 'danger');
            return;
        }

        $this->validate([
            'newKycLevel' => ['required', BackofficeEnums::validationRule(KycLevel::class)],
            'kycNextReviewDate' => ['nullable', 'date'],
        ]);

        // Server enforces monotonic increase and treats same-level as a no-op (spec §5.3a "Change KYC level").
        $updated = $this->api()->changeCustomerKycLevel($this->customerId, $this->newKycLevel, $this->kycNextReviewDate !== '' ? $this->kycNextReviewDate : null);

        $this->customer = $updated === [] ? $this->customer : array_replace($this->customer ?? [], $updated);
        $this->showKycLevelForm = false;
        $this->notify('KYC level updated. This did not change customer status or limit profile.', 'success');
    }

    public function openActivateConfirm(): void
    {
        if (!$this->canActivate()) {
            $this->notify('You do not have permission to activate customers.', 'danger');
            return;
        }

        $this->showActivateConfirm = true;
    }

    public function activateCustomer(): void
    {
        if (!$this->canActivate()) {
            $this->showActivateConfirm = false;
            $this->notify('You do not have permission to activate customers.', 'danger');
            return;
        }

        try {
            $updated = $this->api()->activateCustomer($this->customerId);
            $this->customer = $updated === [] ? $this->customer : array_replace($this->customer ?? [], $updated);
            $this->showActivateConfirm = false;
            $this->notify('Customer activated.', 'success');
        } catch (BackofficeApiException $e) {
            $this->showActivateConfirm = false;

            // Spec §5.3a: a 400 here usually means missing/incompatible limit profile —
            // surface the message verbatim so the operator knows the next step.
            $this->notify($e->userMessage(), 'danger');
        }
    }

    public function getActivationBlockersProperty(): array
    {
        $blockers = [];
        $customer = $this->customer ?? [];

        if (($customer['status'] ?? null) !== 'PENDING_KYC') {
            $blockers[] = 'Customer is not in PENDING_KYC status.';
        }

        if (empty($customer['limitProfileId'])) {
            $blockers[] = 'No limit profile is assigned. Assign one from the Customers page (4-eyes approval required).';
        }

        return $blockers;
    }

    private function notify(string $msg, string $type = 'success'): void
    {
        $this->notification = $msg;
        $this->notificationType = $type;
    }

    public function getRequiredKycDocsAcceptedProperty(): bool
    {
        if ($this->documents === []) {
            return false;
        }

        foreach ($this->documents as $doc) {
            if (($doc['status'] ?? null) !== 'ACCEPTED') {
                return false;
            }
        }

        return true;
    }

    public function render(): \Illuminate\View\View
    {
        $kycLevelOptions = BackofficeEnums::options(KycLevel::class);

        $statusCounts = [
            'PENDING_REVIEW' => 0,
            'ACCEPTED' => 0,
            'REJECTED' => 0,
        ];
        foreach ($this->documents as $doc) {
            $status = (string) ($doc['status'] ?? '');
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }

        return view('livewire.customers.customer-kyc-review', [
            'kycLevelOptions' => $kycLevelOptions,
            'statusCounts' => $statusCounts,
        ]);
    }
};
?>

<div>
    <x-page-header title="KYC Review" subtitle="Review customer KYC documents and update KYC level / activation status" />

    @if ($notification)
        <div class="alert alert-{{ $notificationType }} mb-4">
            <x-icon name="{{ $notificationType === 'success' ? 'check' : 'alert-triangle' }}" size="15" />
            <span>{{ $notification }}</span>
        </div>
    @endif

    @if (!$this->canView())
        <div class="card">
            <div class="empty-state">
                <div class="empty-state-icon">🔒</div>
                <div class="empty-state-title">Access denied</div>
                <div class="empty-state-text">You need the CUSTOMER_KYC_DOCUMENT_VIEW permission to review KYC documents.
                </div>
            </div>
        </div>
    @elseif(!$customer)
        <div class="card">
            <div class="empty-state">
                <div class="empty-state-icon">❓</div>
                <div class="empty-state-title">Customer not found</div>
                <div class="empty-state-text">No customer matches ID <x-mono>{{ $customerId }}</x-mono>.</div>
                <a href="{{ route('customers') }}" class="btn btn-secondary btn-sm mt-2.5">Back to customers</a>
            </div>
        </div>
    @else
        {{-- Customer summary --}}
        <div class="card mb-4">
            <div class="drawer-header">
                <div>
                    <span class="drawer-title">{{ $customer['fullName'] }}</span><br>
                    <x-mono>{{ $customer['id'] }}</x-mono>
                    <x-mono> · {{ $customer['externalRef'] }}</x-mono>
                </div>
                <a href="{{ route('customers') }}" class="btn btn-secondary btn-sm">← Customers</a>
            </div>
            <div class="drawer-body">
                <div class="mb-4 flex gap-2">
                    <x-badge :status="$customer['status']" />
                    <x-badge :status="$customer['kycLevel']" />
                </div>

                <div class="drawer-section">
                    <div class="drawer-section-title">Customer</div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Phone</span>
                        <span class="drawer-field-value"><x-mono>+{{ preg_replace('/\D+/', '', (string) ($customer['phoneCountryCode'] ?? '')) }}
                                {{ $customer['phoneNumber'] ?? '' }}</x-mono></span>
                    </div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">National ID</span>
                        <span class="drawer-field-value">{{ $customer['nationalIdNumber'] ?? '—' }}</span>
                    </div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Date of birth</span>
                        <span class="drawer-field-value">{{ $customer['dateOfBirth'] ?? '—' }}</span>
                    </div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Limit Profile</span>
                        <span class="drawer-field-value">
                            {{ $customer['limitProfileId'] ?? 'None assigned' }}
                            @if (empty($customer['limitProfileId']))
                                <em class="text-xs text-[var(--text-secondary)]"> — assign from Customers page
                                    (LIMIT_PROFILE_CHANGE approval)</em>
                            @endif
                        </span>
                    </div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">KYC Verified</span>
                        <span
                            class="drawer-field-value">{{ isset($customer['kycVerifiedAt']) ? \Carbon\Carbon::parse($customer['kycVerifiedAt'])->format('d M Y') : '—' }}</span>
                    </div>
                </div>

                {{-- KYC level + activation actions footer --}}
                <div class="drawer-footer">
                    @if ($this->canUpdateKyc())
                        <button class="btn btn-primary btn-sm" wire:click="openKycLevelForm">Change KYC Level</button>
                    @endif
                    @if ($this->canActivate() && ($customer['status'] ?? null) === 'PENDING_KYC')
                        <button class="btn btn-primary btn-sm" wire:click="openActivateConfirm">Activate
                            Customer</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- KYC level form --}}
        @if ($showKycLevelForm)
            <div class="card mb-4">
                <div class="drawer-body">
                    <div class="drawer-section">
                        <div class="drawer-section-title">Change KYC Level</div>
                        <p class="text-xs text-[var(--text-secondary)] mb-2.5">
                            The level is monotonic — downgrades are rejected server-side. Submitting the current level
                            is a no-op.
                            This does <strong>not</strong> change the customer's status or limit profile.
                        </p>
                        <div class="flex flex-col gap-2.5">
                            <div>
                                <label class="form-label">KYC Level <span class="form-required">*</span></label>
                                <select wire:model="newKycLevel" class="form-select">
                                    <option value="">Select level</option>
                                    @foreach ($kycLevelOptions as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('newKycLevel')
                                    <div class="form-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Next Review Date <span
                                        class="text-[var(--text-secondary)] text-xs">(optional)</span></label>
                                <input type="date" wire:model="kycNextReviewDate" class="form-input" />
                                @error('kycNextReviewDate')
                                    <div class="form-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="flex gap-2">
                                <button class="btn btn-primary btn-sm" wire:click="submitKycLevel">Submit</button>
                                <button class="btn btn-secondary btn-sm"
                                    wire:click="$set('showKycLevelForm', false)">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Activation confirm --}}
        @if ($showActivateConfirm)
            <x-confirmation-modal title="Activate customer?"
                message="This transitions the customer from PENDING_KYC to ACTIVE. It is refused if no compatible LimitProfile is assigned (active, applicable to CUSTOMER, requiredKycLevel <= customer's kycLevel)."
                confirmLabel="Activate" cancelLabel="Cancel" confirmAction="activateCustomer"
                cancelAction="$set('showActivateConfirm', false)" actionStyle="primary" :entityLabel="'Customer'"
                :entityName="$customer['fullName']" :entityId="$customer['id']" />
        @endif

        {{-- Documents card --}}
        <div class="card">
            <div class="drawer-header">
                <div>
                    <span class="drawer-title">KYC Documents</span><br>
                    <span class="text-xs text-[var(--text-secondary)]">
                        {{ count($documents) }} total
                        · {{ $statusCounts['PENDING_REVIEW'] }} pending
                        · {{ $statusCounts['ACCEPTED'] }} accepted
                        · {{ $statusCounts['REJECTED'] }} rejected
                    </span>
                </div>
                @if ($this->requiredKycDocsAccepted)
                    <span class="text-xs text-[var(--text-secondary)]">All documents accepted</span>
                @endif
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Uploaded</th>
                            <th>Status</th>
                            <th>Reviewed</th>
                            <th>Content Hash</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $doc)
                            <tr class="table-row-link" wire:click="selectDocument('{{ $doc['id'] }}')">
                                <td>
                                    <div class="font-medium">
                                        {{ \App\Support\BackofficeEnums::label($doc['documentType'] ?? null) }}</div>
                                    <x-mono>{{ $doc['id'] }}</x-mono>
                                </td>
                                <td><x-mono>{{ isset($doc['uploadedAt']) ? \Carbon\Carbon::parse($doc['uploadedAt'])->format('d M Y, H:i') : '—' }}</x-mono>
                                </td>
                                <td><x-badge :status="$doc['status']" /></td>
                                <td>
                                    @if (isset($doc['reviewedAt']))
                                        <x-mono>{{ \Carbon\Carbon::parse($doc['reviewedAt'])->format('d M Y, H:i') }}</x-mono>
                                    @else
                                        <span class="text-[var(--text-secondary)]">—</span>
                                    @endif
                                </td>
                                <td><x-mono>{{ \Illuminate\Support\Str::limit((string) ($doc['contentHash'] ?? '—'), 16, '…') }}</x-mono>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">📄</div>
                                        <div class="empty-state-title">No KYC documents</div>
                                        <div class="empty-state-text">The customer has not uploaded any KYC documents
                                            yet.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Document detail drawer --}}
        @if ($selectedDocument)
            <div class="drawer-overlay" wire:click="closeDocumentDrawer"></div>
            <div class="drawer">
                <div class="drawer-header">
                    <div>
                        <span
                            class="drawer-title">{{ \App\Support\BackofficeEnums::label($selectedDocument['documentType'] ?? null) }}</span><br>
                        <x-mono>{{ $selectedDocument['id'] }}</x-mono>
                    </div>
                    <button class="modal-close" wire:click="closeDocumentDrawer"><x-icon name="x"
                            size="18" /></button>
                </div>
                <div class="drawer-body" x-data="{ showPreview: false }"
                    x-init="$watch('$wire.selectedDocument.id', () => showPreview = false)">
                    <div class="mb-4 flex gap-2">
                        <x-badge :status="$selectedDocument['status']" />
                    </div>

                    @if ($this->canView())
                        @php
                            $fileUrl = route('kyc-documents.file', ['documentId' => $selectedDocument['id']]);
                            $contentType = $this->selectedDocumentContentType();
                            $previewMode = $this->selectedDocumentPreviewMode;
                        @endphp
                        <div class="drawer-section">
                            <div class="drawer-section-title">File Preview</div>
                            <div class="flex gap-2 mb-2.5">
                                @if ($previewMode !== 'download')
                                    <button type="button" class="btn btn-primary btn-sm"
                                        x-show="!showPreview" @click="showPreview = true">
                                        <x-icon name="eye" size="14" /> Preview
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm"
                                        x-show="showPreview" @click="showPreview = false" x-cloak>
                                        Hide
                                    </button>
                                @endif
                                <a href="{{ $fileUrl }}" class="btn btn-secondary btn-sm" target="_blank"
                                    rel="noopener">
                                    <x-icon name="external-link" size="14" /> Open
                                </a>
                                <a href="{{ $fileUrl }}" class="btn btn-secondary btn-sm" download>
                                    <x-icon name="download" size="14" /> Download
                                </a>
                            </div>
                            @if ($previewMode === 'download')
                                <div class="alert alert-warning mb-2.5">
                                    <x-icon name="alert-triangle" size="15" />
                                    <span>Inline preview is unavailable for <x-mono>{{ $contentType }}</x-mono>.</span>
                                </div>
                            @else
                                <div x-show="showPreview" x-cloak class="kyc-preview">
                                    @if ($previewMode === 'pdf')
                                        <iframe src="{{ $fileUrl }}" class="w-full"
                                            style="height: 600px; border: 1px solid var(--border-color); border-radius: 6px;"
                                            title="KYC document preview"></iframe>
                                    @else
                                        <img src="{{ $fileUrl }}" alt="KYC document"
                                            style="max-width: 100%; height: auto; border: 1px solid var(--border-color); border-radius: 6px;" />
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="drawer-section">
                        <div class="drawer-section-title">Document</div>
                        <div class="drawer-field">
                            <span class="drawer-field-label">Type</span>
                            <span
                                class="drawer-field-value">{{ \App\Support\BackofficeEnums::label($selectedDocument['documentType'] ?? null) }}</span>
                        </div>
                        <div class="drawer-field">
                            <span class="drawer-field-label">Content Type</span>
                            <span class="drawer-field-value"><x-mono>{{ $this->selectedDocumentContentType() }}</x-mono></span>
                        </div>
                        <div class="drawer-field">
                            <span class="drawer-field-label">Uploaded By</span>
                            <span class="drawer-field-value">
                                {{ \App\Support\BackofficeEnums::label($selectedDocument['uploadedByActorType'] ?? null) }}
                                · <x-mono>{{ $selectedDocument['uploadedByActorId'] ?? '—' }}</x-mono>
                            </span>
                        </div>
                        <div class="drawer-field">
                            <span class="drawer-field-label">Uploaded At</span>
                            <span
                                class="drawer-field-value">{{ isset($selectedDocument['uploadedAt']) ? \Carbon\Carbon::parse($selectedDocument['uploadedAt'])->format('d M Y, H:i') : '—' }}</span>
                        </div>
                    </div>

                    @if (in_array($selectedDocument['status'] ?? null, ['ACCEPTED', 'REJECTED'], true))
                        <div class="drawer-section">
                            <div class="drawer-section-title">Review</div>
                            <div class="drawer-field">
                                <span class="drawer-field-label">Decided At</span>
                                <span
                                    class="drawer-field-value">{{ isset($selectedDocument['reviewedAt']) ? \Carbon\Carbon::parse($selectedDocument['reviewedAt'])->format('d M Y, H:i') : '—' }}</span>
                            </div>
                            <div class="drawer-field">
                                <span class="drawer-field-label">Decided By</span>
                                <span
                                    class="drawer-field-value"><x-mono>{{ $selectedDocument['reviewedByUserId'] ?? '—' }}</x-mono></span>
                            </div>
                            @if (($selectedDocument['status'] ?? null) === 'REJECTED')
                                <div class="drawer-field">
                                    <span class="drawer-field-label">Rejection Reason</span>
                                    <span
                                        class="drawer-field-value">{{ $selectedDocument['rejectionReason'] ?? '—' }}</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Reject form --}}
                    @if ($showRejectModal)
                        <div class="drawer-section">
                            <div class="drawer-section-title">Reject Document</div>
                            <p class="text-xs text-[var(--text-secondary)] mb-2.5">
                                The encrypted file is preserved on storage for the 10-year KYC retention. Only the row's
                                status and reviewer are updated.
                            </p>
                            <label class="form-label">Reason <span class="form-required">*</span></label>
                            <textarea wire:model="rejectReason" class="form-textarea" rows="4" maxlength="1000"
                                placeholder="Explain why this document is rejected (1–1000 chars)…"></textarea>
                            @error('rejectReason')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                            <div class="mt-2.5 flex gap-2">
                                <button class="btn btn-danger btn-sm" wire:click="rejectDocument">Reject
                                    Document</button>
                                <button class="btn btn-secondary btn-sm"
                                    wire:click="$set('showRejectModal', false)">Cancel</button>
                            </div>
                        </div>
                    @endif
                </div>

                @if (!$showRejectModal)
                    @if ($this->canReview() && ($selectedDocument['status'] ?? null) === 'PENDING_REVIEW')
                        <div class="drawer-footer">
                            <button class="btn btn-primary btn-sm" wire:click="approveDocument">Approve</button>
                            <button class="btn btn-danger btn-sm" wire:click="openRejectModal">Reject</button>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    @endif
</div>
