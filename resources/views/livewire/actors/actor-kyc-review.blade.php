<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Enums\Backoffice\KycLevel;
use App\Enums\Backoffice\KycDocumentType;
use App\Exceptions\BackofficeApiException;
use App\Livewire\Concerns\UsesBackofficeEnums;
use App\Services\Api\UsesBackofficeApi;
use App\Support\BackofficeEnums;

new class extends Component {
    use UsesBackofficeApi;
    use UsesBackofficeEnums;
    use WithFileUploads;

    /** 'agents' or 'merchants'. */
    public string $ownerType = 'agents';
    public string $actorId = '';

    public ?array $actor = null;
    /** @var array<int, array<string, mixed>> */
    public array $documents = [];
    public ?array $selectedDocument = null;

    public bool $showUploadForm = false;
    public string $uploadDocumentType = '';
    public $uploadFile = null;

    public bool $showRejectModal = false;
    public string $rejectReason = '';

    public bool $showKycLevelForm = false;
    public string $newKycLevel = '';

    public bool $showActivateConfirm = false;

    public string $notification = '';
    public string $notificationType = 'success';

    public function mount(string $ownerType, string $actorId): void
    {
        $this->ownerType = in_array($ownerType, ['agents', 'merchants'], true) ? $ownerType : 'agents';
        $this->actorId = $actorId;
        $this->loadActor();
        $this->loadDocuments();
    }

    public function isMerchant(): bool
    {
        return $this->ownerType === 'merchants';
    }

    private function loadActor(): void
    {
        $this->actor = $this->isMerchant()
            ? $this->api()->merchant($this->actorId)
            : $this->api()->agent($this->actorId);
    }

    private function loadDocuments(): void
    {
        $this->documents = $this->api()->actorKycDocuments($this->ownerType, $this->actorId);
    }

    public function selectDocument(string $documentId): void
    {
        $summary = $this->findDocumentSummary($documentId);
        $details = $this->api()->actorKycDocument($this->ownerType, $documentId) ?? [];
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

    // ── Permissions (spec §5.3b) ───────────────────────────────────────────
    private function permPrefix(): string
    {
        return $this->isMerchant() ? 'MERCHANT' : 'AGENT';
    }

    public function canView(): bool
    {
        return $this->hasPermission($this->permPrefix() . '_KYC_DOCUMENT_VIEW');
    }

    public function canUpload(): bool
    {
        return $this->hasPermission($this->permPrefix() . '_KYC_DOCUMENT_UPLOAD');
    }

    public function canReview(): bool
    {
        return $this->hasPermission($this->permPrefix() . '_KYC_DOCUMENT_REVIEW');
    }

    public function canUpdateKyc(): bool
    {
        return $this->hasPermission('ACTOR_KYC_UPDATE');
    }

    public function canActivate(): bool
    {
        return $this->hasPermission('ACTOR_ACTIVATE');
    }

    private function hasPermission(string $permission): bool
    {
        $permissions = session('bo_user.permissions', []);

        return is_array($permissions) && in_array($permission, $permissions, true);
    }

    // ── File preview ───────────────────────────────────────────────────────
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

    // ── Upload (spec §5.3b "Upload and file rules") ────────────────────────
    public function openUploadForm(): void
    {
        if (!$this->canUpload()) {
            $this->notify('You do not have permission to upload ' . $this->actorNoun() . ' KYC documents.', 'danger');
            return;
        }

        $this->uploadDocumentType = '';
        $this->uploadFile = null;
        $this->resetValidation(['uploadDocumentType', 'uploadFile']);
        $this->showUploadForm = true;
    }

    public function submitUpload(): void
    {
        if (!$this->canUpload()) {
            $this->showUploadForm = false;
            $this->notify('You do not have permission to upload ' . $this->actorNoun() . ' KYC documents.', 'danger');
            return;
        }

        $this->validate(
            [
                'uploadDocumentType' => ['required', BackofficeEnums::validationRule(KycDocumentType::class)],
                // Spec: required, non-empty, max 10 MB, JPEG/PNG/PDF by byte sniff (server enforces sniffing).
                'uploadFile' => ['required', 'file', 'max:10240', 'mimes:jpeg,jpg,png,pdf'],
            ],
            [],
            ['uploadDocumentType' => 'document type', 'uploadFile' => 'file'],
        );

        try {
            $created = $this->api()->uploadActorKycDocument(
                $this->ownerType,
                $this->actorId,
                $this->uploadDocumentType,
                $this->uploadFile,
            );
        } catch (BackofficeApiException $e) {
            $this->notify($e->userMessage(), 'danger');
            return;
        }

        $this->loadDocuments();
        $this->showUploadForm = false;
        $this->uploadDocumentType = '';
        $this->uploadFile = null;

        if (!empty($created['id'])) {
            $this->selectDocument((string) $created['id']);
        }

        $this->notify('Document uploaded. It is now PENDING_REVIEW — this did not change ' . $this->actorNoun() . ' kycLevel or status.', 'success');
    }

    // ── Document review (spec §5.3b "Review, level, and activation") ───────
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

        $updated = $this->api()->approveActorKycDocument($this->ownerType, (string) $this->selectedDocument['id']);
        $this->selectedDocument = $this->mergeDocument($updated);
        $this->loadDocuments();
        $this->notify('Document approved. The ' . $this->actorNoun() . " kycLevel and status are unchanged.", 'success');
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

        $updated = $this->api()->rejectActorKycDocument($this->ownerType, (string) $this->selectedDocument['id'], $this->rejectReason);
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

    // ── KYC level (spec §5.3b: monotonic, refuses downgrades, no nextReviewDate) ──
    public function openKycLevelForm(): void
    {
        if (!$this->canUpdateKyc()) {
            $this->notify('You do not have permission to change KYC level.', 'danger');
            return;
        }

        $this->newKycLevel = (string) ($this->actor['kycLevel'] ?? '');
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
        ]);

        // Server enforces monotonic increase and treats same-level as a no-op (spec §5.3b).
        $updated = $this->api()->changeActorKycLevel($this->ownerType, $this->actorId, $this->newKycLevel);

        $this->actor = $updated === [] ? $this->actor : array_replace($this->actor ?? [], $updated);
        $this->showKycLevelForm = false;
        $this->notify('KYC level updated. This did not activate the ' . $this->actorNoun() . ' or change its limit profile.', 'success');
    }

    // ── Activation (spec §5.3b) ────────────────────────────────────────────
    public function openActivateConfirm(): void
    {
        if (!$this->canActivate()) {
            $this->notify('You do not have permission to activate ' . $this->actorNoun() . 's.', 'danger');
            return;
        }

        $this->showActivateConfirm = true;
    }

    public function activateActor(): void
    {
        if (!$this->canActivate()) {
            $this->showActivateConfirm = false;
            $this->notify('You do not have permission to activate ' . $this->actorNoun() . 's.', 'danger');
            return;
        }

        try {
            $updated = $this->api()->activateActor($this->ownerType, $this->actorId);
            $this->actor = $updated === [] ? $this->actor : array_replace($this->actor ?? [], $updated);
            $this->showActivateConfirm = false;
            $this->notify(ucfirst($this->actorNoun()) . ' activated. The wallet has been created.', 'success');
        } catch (BackofficeApiException $e) {
            $this->showActivateConfirm = false;

            // Spec §5.3b: a 400 here means kycLevel < KYC_ENHANCED or a missing/incompatible
            // LimitProfile — surface the message verbatim so the operator knows the next step.
            $this->notify($e->userMessage(), 'danger');
        }
    }

    /** @return array<int, string> */
    public function getActivationBlockersProperty(): array
    {
        $blockers = [];
        $actor = $this->actor ?? [];

        if (($actor['status'] ?? null) !== 'PENDING_KYC') {
            $blockers[] = ucfirst($this->actorNoun()) . ' is not in PENDING_KYC status.';
        }

        // Spec §5.3b: activation requires kycLevel >= KYC_ENHANCED.
        if (!in_array($actor['kycLevel'] ?? null, ['KYC_ENHANCED', 'KYC_VERIFIED'], true)) {
            $blockers[] = 'kycLevel must be at least KYC_ENHANCED. Raise it with "Change KYC Level".';
        }

        if (empty($actor['limitProfileId'])) {
            $blockers[] = 'No limit profile is assigned. Assign a compatible one from the '
                . ($this->isMerchant() ? 'Merchants' : 'Agents') . ' page (4-eyes approval required).';
        }

        return $blockers;
    }

    private function actorNoun(): string
    {
        return $this->isMerchant() ? 'merchant' : 'agent';
    }

    private function notify(string $msg, string $type = 'success'): void
    {
        $this->notification = $msg;
        $this->notificationType = $type;
    }

    public function render(): \Illuminate\View\View
    {
        $statusCounts = ['PENDING_REVIEW' => 0, 'ACCEPTED' => 0, 'REJECTED' => 0];
        foreach ($this->documents as $doc) {
            $status = (string) ($doc['status'] ?? '');
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }

        return view('livewire.actors.actor-kyc-review', [
            'kycLevelOptions' => BackofficeEnums::options(KycLevel::class),
            'documentTypeOptions' => BackofficeEnums::options(KycDocumentType::class),
            'statusCounts' => $statusCounts,
        ]);
    }
};
?>

<div>
    @php
        $isMerchant = $this->isMerchant();
        $listRoute = $isMerchant ? 'merchants' : 'agents';
        $actorName = $isMerchant
            ? ($actor['businessName'] ?? $actor['legalName'] ?? '')
            : ($actor['fullName'] ?? '');
        $actorNoun = $isMerchant ? 'merchant' : 'agent';
    @endphp

    <x-page-header title="KYC/KYB Review"
        subtitle="Upload and review {{ $actorNoun }} KYC/KYB documents, update KYC level and activation status" />

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
                <div class="empty-state-text">You need the {{ $isMerchant ? 'MERCHANT' : 'AGENT' }}_KYC_DOCUMENT_VIEW
                    permission to review {{ $actorNoun }} KYC documents.</div>
            </div>
        </div>
    @elseif(!$actor)
        <div class="card">
            <div class="empty-state">
                <div class="empty-state-icon">❓</div>
                <div class="empty-state-title">{{ ucfirst($actorNoun) }} not found</div>
                <div class="empty-state-text">No {{ $actorNoun }} matches ID <x-mono>{{ $actorId }}</x-mono>.</div>
                <a href="{{ route($listRoute) }}" class="btn btn-secondary btn-sm mt-2.5">Back to {{ $listRoute }}</a>
            </div>
        </div>
    @else
        {{-- Actor summary --}}
        <div class="card mb-4">
            <div class="drawer-header">
                <div>
                    <span class="drawer-title">{{ $actorName }}</span><br>
                    <x-mono>{{ $actor['id'] }}</x-mono>
                    <x-mono> · {{ $actor['externalRef'] ?? '—' }}</x-mono>
                </div>
                <a href="{{ route($listRoute) }}" class="btn btn-secondary btn-sm">← {{ ucfirst($listRoute) }}</a>
            </div>
            <div class="drawer-body">
                <div class="mb-4 flex gap-2">
                    <x-badge :status="$actor['status']" />
                    <x-badge :status="$actor['kycLevel']" />
                </div>

                <div class="drawer-section">
                    <div class="drawer-section-title">{{ ucfirst($actorNoun) }}</div>
                    @if ($isMerchant)
                        <div class="drawer-field">
                            <span class="drawer-field-label">Legal Name</span>
                            <span class="drawer-field-value">{{ $actor['legalName'] ?? '—' }}</span>
                        </div>
                        <div class="drawer-field">
                            <span class="drawer-field-label">Business Type</span>
                            <span class="drawer-field-value">{{ \App\Support\BackofficeEnums::label($actor['businessType'] ?? null) }}</span>
                        </div>
                        <div class="drawer-field">
                            <span class="drawer-field-label">Tax ID</span>
                            <span class="drawer-field-value">{{ $actor['taxId'] ?? '—' }}</span>
                        </div>
                    @else
                        <div class="drawer-field">
                            <span class="drawer-field-label">Zone</span>
                            <span class="drawer-field-value">{{ $actor['zone'] ?? '—' }}</span>
                        </div>
                        <div class="drawer-field">
                            <span class="drawer-field-label">Contract Ref</span>
                            <span class="drawer-field-value">{{ $actor['contractRef'] ?? '—' }}</span>
                        </div>
                    @endif
                    <div class="drawer-field">
                        <span class="drawer-field-label">Wallet</span>
                        <span class="drawer-field-value">
                            @if (!empty($actor['walletId']))
                                <x-mono>{{ $actor['walletId'] }}</x-mono>
                            @else
                                <span class="text-[var(--text-secondary)]">Not created yet</span>
                            @endif
                        </span>
                    </div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Limit Profile</span>
                        <span class="drawer-field-value">
                            {{ $actor['limitProfileId'] ?? 'None assigned' }}
                        </span>
                    </div>
                </div>

                {{-- KYC level + activation actions footer --}}
                <div class="drawer-footer">
                    @if ($this->canUpdateKyc())
                        <button class="btn btn-primary btn-sm" wire:click="openKycLevelForm">Change KYC Level</button>
                    @endif
                    @if ($this->canActivate() && ($actor['status'] ?? null) === 'PENDING_KYC')
                        <button class="btn btn-primary btn-sm" wire:click="openActivateConfirm">Activate
                            {{ ucfirst($actorNoun) }}</button>
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
                            The level is monotonic — downgrades are rejected server-side.
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
            <x-confirmation-modal title="Activate {{ $actorNoun }}?"
                message="This transitions the {{ $actorNoun }} from PENDING_KYC to ACTIVE and creates its wallet."
                confirmLabel="Activate" cancelLabel="Cancel" confirmAction="activateActor"
                cancelAction="$set('showActivateConfirm', false)" actionStyle="primary"
                :entityLabel="ucfirst($actorNoun)" :entityName="$actorName" :entityId="$actor['id']" />
        @endif

        {{-- Documents card --}}
        <div class="card">
            <div class="drawer-header">
                <div>
                    <span class="drawer-title">KYC/KYB Documents</span><br>
                    <span class="text-xs text-[var(--text-secondary)]">
                        {{ count($documents) }} total
                        · {{ $statusCounts['PENDING_REVIEW'] }} pending
                        · {{ $statusCounts['ACCEPTED'] }} accepted
                        · {{ $statusCounts['REJECTED'] }} rejected
                    </span>
                </div>
                @if ($this->canUpload())
                    <button class="btn btn-secondary btn-sm" wire:click="openUploadForm">
                        <x-icon name="upload" size="14" /> Upload Document
                    </button>
                @endif
            </div>

            {{-- Upload form --}}
            @if ($showUploadForm)
                <div class="drawer-body">
                    <div class="drawer-section">
                        <div class="drawer-section-title">Upload {{ ucfirst($actorNoun) }} KYC/KYB Document</div>
                        <p class="text-xs text-[var(--text-secondary)] mb-2.5">
                            The file must be JPEG, PNG, or PDF and at most 10 MB.
                        </p>
                        <div class="grid gap-2.5 md:grid-cols-2">
                            <div>
                                <label class="form-label">Document Type <span class="form-required">*</span></label>
                                <select wire:model="uploadDocumentType" class="form-select">
                                    <option value="">Select type</option>
                                    @foreach ($documentTypeOptions as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('uploadDocumentType')
                                    <div class="form-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">File <span class="form-required">*</span></label>
                                <input type="file" wire:model="uploadFile" class="form-input"
                                    accept=".jpg,.jpeg,.png,.pdf" />
                                <div wire:loading wire:target="uploadFile"
                                    class="text-xs text-[var(--text-secondary)] mt-1">Uploading…</div>
                                @error('uploadFile')
                                    <div class="form-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="flex gap-2">
                                <button class="btn btn-primary btn-sm" wire:click="submitUpload"
                                    wire:loading.attr="disabled" wire:target="uploadFile,submitUpload">Upload</button>
                                <button class="btn btn-secondary btn-sm"
                                    wire:click="$set('showUploadForm', false)">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

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
                                        <div class="empty-state-title">No KYC/KYB documents</div>
                                        <div class="empty-state-text">
                                            @if ($this->canUpload())
                                                Upload the {{ $actorNoun }}'s first KYC/KYB document to start the
                                                review.
                                            @else
                                                No documents have been uploaded for this {{ $actorNoun }} yet.
                                            @endif
                                        </div>
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
                            $fileUrl = route('actor-kyc-documents.file', [
                                'ownerType' => $ownerType,
                                'documentId' => $selectedDocument['id'],
                            ]);
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
                            <span class="drawer-field-label">Owner</span>
                            <span class="drawer-field-value">
                                {{ \App\Support\BackofficeEnums::label($selectedDocument['ownerActorType'] ?? null) }}
                                · <x-mono>{{ $selectedDocument['ownerActorId'] ?? '—' }}</x-mono>
                            </span>
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
                                The encrypted file is preserved on storage for KYC retention. Only the row's status and
                                reviewer are updated — the {{ $actorNoun }} kycLevel and status are unchanged.
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
