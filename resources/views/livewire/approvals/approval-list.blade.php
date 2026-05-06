<?php

use Livewire\Component;
use App\Services\Mock\MockDataService;

new class extends Component
{
    public bool $pendingOnly = true;
    public string $typeFilter = '';
    public ?array $selected = null;
    public bool $showApproveConfirm = false;
    public bool $showRejectModal = false;
    public string $decisionReason = '';
    public string $notification = '';
    public string $notificationType = 'success';

    public function selectRow(string $id): void { $this->selected = MockDataService::approval($id); }
    public function closeDrawer(): void {
        $this->selected = null;
        $this->showApproveConfirm = false;
        $this->showRejectModal = false;
        $this->decisionReason = '';
    }

    public function approve(): void
    {
        // Real: POST /api/v1/backoffice/approvals/{id}/approve  (optional ApprovalDecisionRequest)
        $this->notify('Approval granted successfully.', 'success');
        $this->closeDrawer();
    }

    public function reject(): void
    {
        $this->validate(['decisionReason' => 'required|min:3|max:500']);
        // Real: POST /api/v1/backoffice/approvals/{id}/reject  (ApprovalDecisionRequest with reason)
        $this->notify('Request rejected.', 'success');
        $this->closeDrawer();
    }

    private function notify(string $msg, string $type = 'success'): void
    {
        $this->notification = $msg;
        $this->notificationType = $type;
    }

    private function approvalTypeClass(string $type): string
    {
        return match($type) {
            'REVERSAL'                         => 'text-[var(--red)] bg-[var(--red-bg)]',
            'AGENT_FUND_IN', 'AGENT_FUND_OUT'  => 'text-[var(--teal)] bg-[var(--teal-bg)]',
            'ACCOUNT_CLOSURE'                  => 'text-[var(--amber)] bg-[var(--amber-bg)]',
            'LARGE_CASH_OUT'                   => 'text-[var(--amber)] bg-[var(--amber-bg)]',
            'FEE_RULE_CHANGE'                  => 'text-[var(--purple)] bg-[var(--purple-bg)]',
            'COMMISSION_RULE_CHANGE'           => 'text-[var(--indigo)] bg-[var(--indigo-bg)]',
            'BILL_PROVIDER_SETTLEMENT'         => 'text-[var(--blue)] bg-[var(--blue-bg)]',
            'PLATFORM_REVENUE_WITHDRAWAL'      => 'text-[var(--green)] bg-[var(--green-bg)]',
            default                            => 'text-[var(--text-secondary)] bg-[var(--border-color)]',
        };
    }

    public function render(): \Illuminate\View\View
    {
        $all = MockDataService::approvals([
            'pendingOnly' => $this->pendingOnly,
            'type'        => $this->typeFilter ?: null,
        ]);
        return view('livewire.approvals.approval-list', [
            'rows'   => $all,
            'total'  => count($all),
            'pending' => count(array_filter($all, fn($r) => $r['status'] === 'PENDING_APPROVAL')),
        ]);
    }
};
?>

<div>
    @if($notification)
    <div class="alert alert-{{ $notificationType }} mb-4">
        <x-icon name="check" size="15" /> {{ $notification }}
    </div>
    @endif

    {{-- Stats strip --}}
    <div class="mb-4 flex gap-3">
        <div class="kpi-card flex-1">
            <div class="kpi-label">Pending Review</div>
            <div class="kpi-value {{ $pending > 0 ? '!text-[var(--amber)]' : '' }}">{{ $pending }}</div>
        </div>
        <div class="kpi-card flex-1">
            <div class="kpi-label">Total Shown</div>
            <div class="kpi-value">{{ $total }}</div>
        </div>
    </div>

    <div class="card">
        <div class="filter-bar">
            <label class="flex cursor-pointer items-center gap-1.5 text-[13px]">
                <input type="checkbox" wire:model.live="pendingOnly" /> Pending only
            </label>
            <select wire:model.live="typeFilter" class="filter-select">
                <option value="">All types</option>
                @foreach(['REVERSAL','ACCOUNT_CLOSURE','LARGE_CASH_OUT','AGENT_FUND_IN','AGENT_FUND_OUT','FEE_RULE_CHANGE','COMMISSION_RULE_CHANGE','CONTROL_THRESHOLD_CHANGE','LIMIT_PROFILE_CHANGE','SERVICE_PROVIDER_CHANGE','BILL_PROVIDER_SETTLEMENT','PLATFORM_REVENUE_WITHDRAWAL','RECONCILIATION_ADJUSTMENT','BACKOFFICE_USER_PRIVILEGE_ELEVATION'] as $t)
                <option value="{{ $t }}">{{ str_replace('_', ' ', $t) }}</option>
                @endforeach
            </select>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Target</th>
                        <th>Requested By</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr class="table-row-link" wire:click="selectRow('{{ $row['id'] }}')">
                        <td>
                            <span class="approval-type-pill {{ $this->approvalTypeClass($row['type']) }}">
                                {{ str_replace('_', ' ', $row['type']) }}
                            </span>
                        </td>
                        <td>
                            <span class="text-xs">{{ str_replace('_', ' ', $row['targetEntityType']) }}</span><br/>
                            <x-mono>{{ Str::limit($row['targetEntityId'] ?? '—', 12) }}</x-mono>
                        </td>
                        <td><x-mono>{{ Str::limit($row['requestedBy'], 12) }}</x-mono></td>
                        <td><x-badge :status="$row['status']" /></td>
                        <td><x-mono>{{ \Carbon\Carbon::parse($row['expiresAt'])->format('d M, H:i') }}</x-mono></td>
                        <td><x-mono>{{ \Carbon\Carbon::parse($row['createdAt'])->format('d M, H:i') }}</x-mono></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-state-icon">✓</div>
                                <div class="empty-state-title">No approval requests</div>
                                <div class="empty-state-text">All clear — nothing awaiting review.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination"><span class="pagination-info">{{ $total }} items</span></div>
    </div>

    @if($selected)
    <div class="drawer-overlay" wire:click="closeDrawer"></div>
    <div class="drawer">
        <div class="drawer-header">
            <div>
                <div class="drawer-title">Approval Request</div>
                <span class="approval-type-pill mt-1 {{ $this->approvalTypeClass($selected['type']) }}">
                    {{ str_replace('_', ' ', $selected['type']) }}
                </span>
            </div>
            <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
        </div>
        <div class="drawer-body">
            <div class="mb-4"><x-badge :status="$selected['status']" /></div>

            <div class="drawer-section">
                <div class="drawer-section-title">Request Details</div>
                <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selected['id'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Requested By</span><span class="drawer-field-value">{{ $selected['requestedBy'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Target</span><span class="drawer-field-value">{{ str_replace('_', ' ', $selected['targetEntityType']) }} / {{ $selected['targetEntityId'] ?? '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Expires</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['expiresAt'])->format('d M Y, H:i') }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Created</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['createdAt'])->format('d M Y, H:i') }}</span></div>
                @if(isset($selected['approvedBy']))
                <div class="drawer-field"><span class="drawer-field-label">Approved By</span><span class="drawer-field-value">{{ $selected['approvedBy'] }}</span></div>
                @endif
                @if(isset($selected['rejectedBy']))
                <div class="drawer-field"><span class="drawer-field-label">Rejected By</span><span class="drawer-field-value">{{ $selected['rejectedBy'] }}</span></div>
                @endif
                @if(isset($selected['decisionReason']))
                <div class="drawer-field"><span class="drawer-field-label">Decision Reason</span><span class="drawer-field-value">{{ $selected['decisionReason'] }}</span></div>
                @endif
            </div>

            {{-- Payload --}}
            <div class="drawer-section">
                <div class="drawer-section-title">Payload</div>
                <pre class="text-mono overflow-x-auto whitespace-pre-wrap break-all rounded-md bg-[var(--bg)] p-3 text-[11px]">{{ json_encode(json_decode($selected['payload']), JSON_PRETTY_PRINT) }}</pre>
            </div>

            @if($showApproveConfirm)
            <div class="alert alert-success">
                <div>
                    <strong>Confirm approval?</strong>
                    <br/>This action cannot be undone.
                    <div class="mt-2.5 flex gap-2">
                        <button class="btn btn-primary btn-sm" wire:click="approve">Yes, approve</button>
                        <button class="btn btn-secondary btn-sm" wire:click="$set('showApproveConfirm', false)">Cancel</button>
                    </div>
                </div>
            </div>
            @endif

            @if($showRejectModal)
            <div class="drawer-section">
                <div class="drawer-section-title">Reject Request</div>
                <label class="form-label">Reason <span class="form-required">*</span></label>
                <textarea wire:model="decisionReason" class="form-textarea" rows="3" placeholder="Reason for rejection (required)…"></textarea>
                @error('decisionReason') <div class="form-error">{{ $message }}</div> @enderror
                <div class="mt-2.5 flex gap-2">
                    <button class="btn btn-danger btn-sm" wire:click="reject">Confirm Rejection</button>
                    <button class="btn btn-secondary btn-sm" wire:click="$set('showRejectModal', false)">Cancel</button>
                </div>
            </div>
            @endif
        </div>

        @if($selected['status'] === 'PENDING_APPROVAL' && !$showApproveConfirm && !$showRejectModal)
        <div class="drawer-footer">
            <button class="btn btn-primary btn-md" wire:click="$set('showApproveConfirm', true)">
                <x-icon name="check" size="14" /> Approve
            </button>
            <button class="btn btn-danger btn-md" wire:click="$set('showRejectModal', true)">
                <x-icon name="x" size="14" /> Reject
            </button>
        </div>
        @endif
    </div>
    @endif
</div>
