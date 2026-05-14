<?php

use Livewire\Component;
use App\Enums\Backoffice\ActorType;
use App\Enums\Backoffice\TransactionStatus;
use App\Enums\Backoffice\TransactionType;
use App\Livewire\Concerns\UsesBackofficeEnums;
use App\Services\Api\UsesBackofficeApi;
use App\Support\BackofficeEnums;

new class extends Component
{
    use UsesBackofficeApi;
    use UsesBackofficeEnums;

    public string $typeFilter = '';
    public string $statusFilter = '';
    public ?array $selected = null;
    public bool $showReversalModal = false;
    public string $reversalReason = '';
    public string $notification = '';

    public function selectRow(string $id): void { $this->selected = $this->api()->transaction($id); }
    public function closeDrawer(): void { $this->selected = null; $this->showReversalModal = false; $this->reversalReason = ''; }

    public function submitReversal(): void
    {
        $this->validate(['reversalReason' => 'required|min:3']);
        $this->api()->reverseTransaction([
            'transactionId' => $this->selected['id'],
            'reason' => $this->reversalReason,
        ]);
        $this->notification = 'Reversal request submitted for approval.';
        $this->closeDrawer();
    }

    public function render(): \Illuminate\View\View
    {
        $all = $this->api()->transactions([
            'type'   => $this->typeFilter ?: null,
            'status' => $this->statusFilter ?: null,
        ]);
        $typeRows = $this->api()->transactions([
            'status' => $this->statusFilter ?: null,
        ]);
        $statusRows = $this->api()->transactions([
            'type' => $this->typeFilter ?: null,
        ]);

        return view('livewire.transactions.transaction-list', [
            'rows' => $all,
            'total' => count($all),
            'typeOptions' => BackofficeEnums::optionsFromRows($typeRows, 'type', TransactionType::class, $this->typeFilter),
            'statusOptions' => BackofficeEnums::optionsFromRows($statusRows, 'status', TransactionStatus::class, $this->statusFilter),
        ]);
    }
};
?>

<div>
    <x-page-header
        title="Transactions"
        subtitle="All financial movements across wallets"
    />

    @if($notification)
    <div class="alert alert-success mb-4"><x-icon name="check" size="15" /> {{ $notification }}</div>
    @endif

    <div class="card">
        <div class="filter-bar">
            <select wire:model.live="typeFilter" class="filter-select">
                <option value="">All types</option>
                @foreach($typeOptions as $option)
                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter" class="filter-select">
                <option value="">All statuses</option>
                @foreach($statusOptions as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
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
                        <td><span class="text-xs font-medium">{{ $this->enumLabel($row['type']) }}</span></td>
                        <td><span class="text-xs font-medium">{{ $this->enumLabel($row['initiatorType']) }}</span></td>
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
            <div>
                <span class="drawer-title">Transaction Detail</span><br>
                <span class="drawer-field-value">{{ $selected['id'] }}</span>
            </div>
            <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
        </div>
        <div class="drawer-body">
            <div class="mb-4 flex gap-2">
                <x-badge :status="$selected['status']" />
                <span class="text-xs font-semibold text-[var(--text-secondary)]">{{ $this->enumLabel($selected['type']) }}</span>
            </div>

            {{-- Amount hero --}}
            <div class="mb-4 rounded-lg bg-[var(--bg)] p-4 text-center">
                <div class="text-mono text-[28px] font-bold text-[var(--text-primary)]">
                    {{ number_format($selected['requestedAmount']) }}
                    <span class="text-base font-normal text-[var(--text-secondary)]">{{ $selected['currency'] }}</span>
                </div>
                @if($selected['feeAmount'] > 0)
                <div class="mt-1 text-xs text-[var(--text-secondary)]">
                    Fee: {{ number_format($selected['feeAmount']) }} KMF •
                    Commission: {{ number_format($selected['commissionAmount']) }} KMF •
                    Net: {{ number_format($selected['netAmountToDestination']) }} KMF
                </div>
                @endif
            </div>

            <div class="drawer-section">
                <div class="drawer-section-title">Details</div>
                <div class="drawer-field"><span class="drawer-field-label">Initiator</span><span class="drawer-field-value">{{ $this->enumLabel($selected['initiatorType']) }} / {{ $selected['initiatorId'] }}</span></div>
                @if(isset($selected['sourceWalletId']))
                <div class="drawer-field"><span class="drawer-field-label">Source Wallet</span><span class="drawer-field-value">{{ $selected['sourceWalletId'] }}</span></div>
                @endif
                @if(isset($selected['destinationWalletId']))
                <div class="drawer-field"><span class="drawer-field-label">Dest Wallet</span><span class="drawer-field-value">{{ $selected['destinationWalletId'] }}</span></div>
                @endif
                @if(isset($selected['declineReason']))
                <div class="drawer-field"><span class="drawer-field-label">Decline Reason</span><span class="drawer-field-value !text-[var(--red)]">{{ $this->enumLabel($selected['declineReason']) }}</span></div>
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
                <p class="mb-2.5 text-xs text-[var(--text-secondary)]">Creates an approval request. Requires <code>TX_REVERSAL_APPROVE</code> permission to approve.</p>
                <label class="form-label">Reason <span class="form-required">*</span></label>
                <textarea wire:model="reversalReason" class="form-textarea" rows="3" placeholder="Min 3 characters…"></textarea>
                @error('reversalReason') <div class="form-error">{{ $message }}</div> @enderror
                <div class="mt-2.5 flex gap-2">
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
