<?php

use Livewire\Component;
use App\Services\Mock\MockDataService;

new class extends Component
{
    public string $typeFilter = '';
    public string $statusFilter = '';
    public ?array $selected = null;
    public bool $showReversalModal = false;
    public string $reversalReason = '';
    public string $notification = '';

    public function selectRow(string $id): void { $this->selected = MockDataService::transaction($id); }
    public function closeDrawer(): void { $this->selected = null; $this->showReversalModal = false; $this->reversalReason = ''; }

    public function submitReversal(): void
    {
        $this->validate(['reversalReason' => 'required|min:3']);
        // Real: POST /api/v1/backoffice/transactions/reversals  (returns 201 ApprovalRequestResponse)
        $this->notification = 'Reversal request submitted for approval.';
        $this->closeDrawer();
    }

    public function render(): \Illuminate\View\View
    {
        $all = MockDataService::transactions([
            'type'   => $this->typeFilter ?: null,
            'status' => $this->statusFilter ?: null,
        ]);
        return view('livewire.transactions.transaction-list', ['rows' => $all, 'total' => count($all)]);
    }
};
?>

<div>
    @if($notification)
    <div class="alert alert-success" style="margin-bottom:16px;"><x-icon name="check" size="15" /> {{ $notification }}</div>
    @endif

    <div class="card">
        <div class="filter-bar">
            <select wire:model.live="typeFilter" class="filter-select">
                <option value="">All types</option>
                @foreach(['CASH_IN','CASH_OUT','PAYMENT','P2P_TRANSFER','SERVICE_PAYMENT','CARD_SALE','AGENT_FUND_IN','AGENT_FUND_OUT','COMMISSION_PAYOUT','REVERSAL','MERCHANT_TO_MERCHANT','BILL_PROVIDER_SETTLEMENT','PLATFORM_REVENUE_WITHDRAWAL'] as $t)
                <option value="{{ $t }}">{{ str_replace('_', ' ', $t) }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter" class="filter-select">
                <option value="">All statuses</option>
                <option value="PENDING">Pending</option>
                <option value="AUTHORIZED">Authorized</option>
                <option value="COMPLETED">Completed</option>
                <option value="DECLINED">Declined</option>
                <option value="REVERSED">Reversed</option>
                <option value="EXPIRED">Expired</option>
            </select>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Initiator</th>
                        <th>Amount</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr class="table-row-link" wire:click="selectRow('{{ $row['id'] }}')">
                        <td><x-mono>{{ strtoupper($row['id']) }}</x-mono></td>
                        <td><span style="font-size:12px;font-weight:500;">{{ str_replace('_', ' ', $row['type']) }}</span></td>
                        <td><x-mono>{{ $row['initiatorType'] }}</x-mono></td>
                        <td><x-amount :value="$row['requestedAmount']" size="12" /></td>
                        <td><x-amount :value="$row['feeAmount']" size="12" /></td>
                        <td><x-badge :status="$row['status']" /></td>
                        <td><x-mono>{{ \Carbon\Carbon::parse($row['createdAt'])->format('d M, H:i') }}</x-mono></td>
                    </tr>
                    @empty
                    <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No transactions found</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination"><span class="pagination-info">{{ $total }} total</span></div>
    </div>

    @if($selected)
    <div class="drawer-overlay" wire:click="closeDrawer"></div>
    <div class="drawer">
        <div class="drawer-header">
            <span class="drawer-title">Transaction Detail</span>
            <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
        </div>
        <div class="drawer-body">
            <div style="display:flex;gap:8px;margin-bottom:16px;">
                <x-badge :status="$selected['status']" />
                <span style="font-size:12px;font-weight:600;color:var(--text-secondary);">{{ str_replace('_', ' ', $selected['type']) }}</span>
            </div>

            {{-- Amount hero --}}
            <div style="text-align:center;padding:16px;background:var(--bg);border-radius:8px;margin-bottom:16px;">
                <div style="font-family:'DM Mono',monospace;font-size:28px;font-weight:700;color:var(--text-primary);">
                    {{ number_format($selected['requestedAmount']) }}
                    <span style="font-size:16px;font-weight:400;color:var(--text-secondary);">{{ $selected['currency'] }}</span>
                </div>
                @if($selected['feeAmount'] > 0)
                <div style="font-size:12px;color:var(--text-secondary);margin-top:4px;">
                    Fee: {{ number_format($selected['feeAmount']) }} KMF •
                    Commission: {{ number_format($selected['commissionAmount']) }} KMF •
                    Net: {{ number_format($selected['netAmountToDestination']) }} KMF
                </div>
                @endif
            </div>

            <div class="drawer-section">
                <div class="drawer-section-title">Details</div>
                <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selected['id'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Initiator</span><span class="drawer-field-value">{{ $selected['initiatorType'] }} / {{ $selected['initiatorId'] }}</span></div>
                @if(isset($selected['sourceWalletId']))
                <div class="drawer-field"><span class="drawer-field-label">Source Wallet</span><span class="drawer-field-value">{{ $selected['sourceWalletId'] }}</span></div>
                @endif
                @if(isset($selected['destinationWalletId']))
                <div class="drawer-field"><span class="drawer-field-label">Dest Wallet</span><span class="drawer-field-value">{{ $selected['destinationWalletId'] }}</span></div>
                @endif
                @if(isset($selected['declineReason']))
                <div class="drawer-field"><span class="drawer-field-label">Decline Reason</span><span class="drawer-field-value" style="color:var(--red);">{{ $selected['declineReason'] }}</span></div>
                @endif
                @if(isset($selected['reversalOfTransactionId']))
                <div class="drawer-field"><span class="drawer-field-label">Reversal Of</span><span class="drawer-field-value">{{ $selected['reversalOfTransactionId'] }}</span></div>
                @endif
                <div class="drawer-field"><span class="drawer-field-label">Created</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['createdAt'])->format('d M Y, H:i:s') }}</span></div>
                @if(isset($selected['completedAt']))
                <div class="drawer-field"><span class="drawer-field-label">Completed</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['completedAt'])->format('d M Y, H:i:s') }}</span></div>
                @endif
            </div>

            @if($showReversalModal)
            <div class="drawer-section">
                <div class="drawer-section-title">Request Reversal</div>
                <p style="font-size:12px;color:var(--text-secondary);margin-bottom:10px;">Creates an approval request. Requires <code>TX_REVERSAL_APPROVE</code> permission to approve.</p>
                <label class="form-label">Reason <span class="form-required">*</span></label>
                <textarea wire:model="reversalReason" class="form-textarea" rows="3" placeholder="Min 3 characters…"></textarea>
                @error('reversalReason') <div class="form-error">{{ $message }}</div> @enderror
                <div style="display:flex;gap:8px;margin-top:10px;">
                    <button class="btn btn-danger btn-sm" wire:click="submitReversal">Submit Reversal Request</button>
                    <button class="btn btn-secondary btn-sm" wire:click="$set('showReversalModal', false)">Cancel</button>
                </div>
            </div>
            @endif
        </div>

        @if(!$showReversalModal)
        <div class="drawer-footer">
            @if($selected['status'] === 'COMPLETED' && !isset($selected['reversedByTransactionId']))
                <button class="btn btn-danger btn-sm" wire:click="$set('showReversalModal', true)">Request Reversal</button>
            @endif
        </div>
        @endif
    </div>
    @endif
</div>
