<?php

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Enums\Backoffice\PaymentRequestStatus;
use App\Enums\Backoffice\TransactionStatus;
use App\Enums\Backoffice\TransactionType;
use App\Livewire\Concerns\WithApiCursorPagination;
use App\Livewire\Concerns\UsesBackofficeEnums;
use App\Services\Api\UsesBackofficeApi;
use App\Support\BackofficeEnums;

new class extends Component
{
    use WithApiCursorPagination;
    use UsesBackofficeApi;
    use UsesBackofficeEnums;

    #[Url(as: 'tab')]
    public string $tab = 'transactions';

    public string $typeFilter = '';
    public string $statusFilter = '';
    public string $paymentRequestStatusFilter = '';
    #[Url(as: 'merchant')]
    public string $paymentRequestMerchantFilter = '';
    public string $paymentRequestFrom = '';
    public string $paymentRequestTo = '';
    public ?array $selected = null;
    public ?array $selectedPaymentRequest = null;
    public bool $showReversalModal = false;
    public string $reversalReason = '';
    public string $notification = '';

    public function updatingTypeFilter(): void { $this->resetCursorPage('transactions'); }
    public function updatingStatusFilter(): void { $this->resetCursorPage('transactions'); }
    public function updatingPaymentRequestStatusFilter(): void { $this->resetCursorPage('payment-requests'); }
    public function updatingPaymentRequestMerchantFilter(): void { $this->resetCursorPage('payment-requests'); }
    public function updatingPaymentRequestFrom(): void { $this->resetCursorPage('payment-requests'); }
    public function updatingPaymentRequestTo(): void { $this->resetCursorPage('payment-requests'); }

    public function selectRow(string $id): void
    {
        $this->selected = $this->api()->transaction($id);
        $this->selectedPaymentRequest = null;
    }

    public function selectPaymentRequest(string $id): void
    {
        $this->selectedPaymentRequest = $this->api()->paymentRequest($id);
        $this->selected = null;
    }

    public function closeDrawer(): void
    {
        $this->selected = null;
        $this->selectedPaymentRequest = null;
        $this->showReversalModal = false;
        $this->reversalReason = '';
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['transactions', 'payment-requests'], true)) {
            return;
        }

        $this->tab = $tab;
        $this->closeDrawer();
    }

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
        $transactionRows = [];
        $paymentRequestRows = [];
        $typeOptions = [];
        $statusOptions = [];
        $paymentRequestStatusOptions = BackofficeEnums::options(PaymentRequestStatus::class);
        $transactionPaginator = null;
        $paymentRequestPaginator = null;

        if ($this->tab === 'payment-requests') {
            $page = $this->api()->paymentRequestsPage($this->cursorPageQuery('payment-requests') + [
                'status' => $this->paymentRequestStatusFilter ?: null,
                'merchantId' => trim($this->paymentRequestMerchantFilter) ?: null,
                'from' => $this->paymentRequestFrom ?: null,
                'to' => $this->paymentRequestTo ?: null,
            ]);
            $paymentRequestRows = $page['data'];
            $paymentRequestPaginator = $this->cursorPaginator('payment-requests', $page, count($paymentRequestRows), 'payment requests');
        } else {
            $page = $this->api()->transactionsPage($this->cursorPageQuery('transactions') + [
                'type'   => $this->typeFilter ?: null,
                'status' => $this->statusFilter ?: null,
            ]);
            $transactionRows = $page['data'];
            $typeRows = $this->api()->transactions([
                'status' => $this->statusFilter ?: null,
                'limit' => 100,
            ]);
            $statusRows = $this->api()->transactions([
                'type' => $this->typeFilter ?: null,
                'limit' => 100,
            ]);
            $transactionPaginator = $this->cursorPaginator('transactions', $page, count($transactionRows), 'transactions');
            $typeOptions = BackofficeEnums::optionsFromRows($typeRows, 'type', TransactionType::class, $this->typeFilter);
            $statusOptions = BackofficeEnums::optionsFromRows($statusRows, 'status', TransactionStatus::class, $this->statusFilter);
        }

        return view('livewire.transactions.transaction-list', [
            'rows' => $transactionRows,
            'paymentRequestRows' => $paymentRequestRows,
            'paginator' => $transactionPaginator,
            'paymentRequestPaginator' => $paymentRequestPaginator,
            'typeOptions' => $typeOptions,
            'statusOptions' => $statusOptions,
            'paymentRequestStatusOptions' => $paymentRequestStatusOptions,
        ]);
    }
};
?>

<div>
    <x-page-header
        title="Transactions"
        subtitle="Financial movements and merchant payment requests"
    />

    @if($notification)
    <div class="alert alert-success mb-4"><x-icon name="check" size="15" /> {{ $notification }}</div>
    @endif

    <div class="card">
        <div class="tabs">
            <button class="tab @if($tab==='transactions') active @endif" wire:click="setTab('transactions')">Ledger</button>
            <button class="tab @if($tab==='payment-requests') active @endif" wire:click="setTab('payment-requests')">Payment Requests</button>
        </div>

        @if($tab === 'payment-requests')
            <div class="filter-bar">
                <select wire:model.live="paymentRequestStatusFilter" class="filter-select">
                    <option value="">All statuses</option>
                    @foreach($paymentRequestStatusOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <input wire:model.live.debounce.300ms="paymentRequestMerchantFilter" type="text" class="filter-select !cursor-text" placeholder="Merchant ID" />
                <input wire:model.live="paymentRequestFrom" type="date" class="filter-select" />
                <input wire:model.live="paymentRequestTo" type="date" class="filter-select" />
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Request</th>
                            <th>Merchant</th>
                            <th>Amount</th>
                            <th>Mode</th>
                            <th>Payer</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paymentRequestRows as $row)
                        <tr class="table-row-link" wire:click="selectPaymentRequest('{{ $row['id'] }}')">
                            <td>
                                <div class="font-medium">{{ $row['shortCode'] }}</div>
                                <x-mono>{{ $row['id'] }}</x-mono>
                            </td>
                            <td><x-mono>{{ $row['beneficiaryMerchantId'] }}</x-mono></td>
                            <td><x-amount :value="$row['amount']" :currency="$row['currency'] ?? 'KMF'" size="12" /></td>
                            <td><x-badge :status="$row['mode']" /></td>
                            <td>
                                @if(!empty($row['targetPayerType']) && !empty($row['targetPayerId']))
                                    <span class="text-xs font-medium">{{ $this->enumLabel($row['targetPayerType']) }}</span><br>
                                    <x-mono>{{ $row['targetPayerId'] }}</x-mono>
                                @else
                                    <span class="text-xs text-[var(--text-secondary)]">Open</span>
                                @endif
                            </td>
                            <td><x-badge :status="$row['status']" /></td>
                            <td><x-mono>{{ \Carbon\Carbon::parse($row['createdAt'])->format('d M, H:i') }}</x-mono></td>
                        </tr>
                        @empty
                        <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No payment requests found</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-cursor-pagination :paginator="$paymentRequestPaginator" />
        @else
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
                        <tr><td colspan="6"><div class="empty-state"><div class="empty-state-title">No transactions found</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-cursor-pagination :paginator="$paginator" />
        @endif
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

    @if($selectedPaymentRequest)
    <div class="drawer-overlay" wire:click="closeDrawer"></div>
    <div class="drawer">
        <div class="drawer-header">
            <div>
                <span class="drawer-title">Payment Request</span><br>
                <span class="drawer-field-value">{{ $selectedPaymentRequest['id'] }}</span>
            </div>
            <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
        </div>
        <div class="drawer-body">
            <div class="mb-4 flex gap-2">
                <x-badge :status="$selectedPaymentRequest['status']" />
                <x-badge :status="$selectedPaymentRequest['mode']" />
            </div>

            <div class="mb-4 rounded-lg bg-[var(--bg)] p-4 text-center">
                <x-amount :value="$selectedPaymentRequest['amount']" :currency="$selectedPaymentRequest['currency'] ?? 'KMF'" size="28" />
                @if(!empty($selectedPaymentRequest['label']))
                    <div class="mt-1 text-xs text-[var(--text-secondary)]">{{ $selectedPaymentRequest['label'] }}</div>
                @endif
            </div>

            <div class="drawer-section">
                <div class="drawer-section-title">Request</div>
                <div class="drawer-field"><span class="drawer-field-label">Short Code</span><span class="drawer-field-value">{{ $selectedPaymentRequest['shortCode'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Merchant</span><span class="drawer-field-value">{{ $selectedPaymentRequest['beneficiaryMerchantId'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Expires</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedPaymentRequest['expiresAt'])->format('d M Y, H:i:s') }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Created</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedPaymentRequest['createdAt'])->format('d M Y, H:i:s') }}</span></div>
            </div>

            @if(!empty($selectedPaymentRequest['targetPayerType']) || !empty($selectedPaymentRequest['paidByActorType']))
            <div class="drawer-section">
                <div class="drawer-section-title">Actors</div>
                @if(!empty($selectedPaymentRequest['targetPayerType']))
                    <div class="drawer-field"><span class="drawer-field-label">Target Payer</span><span class="drawer-field-value">{{ $this->enumLabel($selectedPaymentRequest['targetPayerType']) }} / {{ $selectedPaymentRequest['targetPayerId'] }}</span></div>
                @endif
                @if(!empty($selectedPaymentRequest['paidByActorType']))
                    <div class="drawer-field"><span class="drawer-field-label">Paid By</span><span class="drawer-field-value">{{ $this->enumLabel($selectedPaymentRequest['paidByActorType']) }} / {{ $selectedPaymentRequest['paidByActorId'] }}</span></div>
                @endif
            </div>
            @endif

            @if(!empty($selectedPaymentRequest['settledTransactionId']) || !empty($selectedPaymentRequest['paidAt']) || !empty($selectedPaymentRequest['cancelledReason']))
            <div class="drawer-section">
                <div class="drawer-section-title">Outcome</div>
                @if(!empty($selectedPaymentRequest['settledTransactionId']))
                    <div class="drawer-field"><span class="drawer-field-label">Settled Transaction</span><span class="drawer-field-value">{{ $selectedPaymentRequest['settledTransactionId'] }}</span></div>
                @endif
                @if(!empty($selectedPaymentRequest['paidAt']))
                    <div class="drawer-field"><span class="drawer-field-label">Paid At</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedPaymentRequest['paidAt'])->format('d M Y, H:i:s') }}</span></div>
                @endif
                @if(!empty($selectedPaymentRequest['cancelledReason']))
                    <div class="drawer-field"><span class="drawer-field-label">Cancel Reason</span><span class="drawer-field-value">{{ $selectedPaymentRequest['cancelledReason'] }}</span></div>
                @endif
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
