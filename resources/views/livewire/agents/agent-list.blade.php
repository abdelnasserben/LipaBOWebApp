<?php

use Livewire\Component;
use App\Services\Mock\MockDataService;

new class extends Component
{
    public string $search = '';
    public string $statusFilter = '';
    public ?array $selected = null;
    public bool $showFundModal = false;
    public string $fundType = 'in';
    public string $fundAmount = '';
    public string $fundNotes = '';
    public bool $showKycApproval = false;
    public string $kycLevel = 'KYC_BASIC';
    public string $notification = '';
    public string $notificationType = 'success';

    public function selectRow(string $id): void { $this->selected = MockDataService::agent($id); }
    public function closeDrawer(): void {
        $this->selected = null;
        $this->showFundModal = false;
        $this->showKycApproval = false;
        $this->fundAmount = '';
        $this->fundNotes = '';
    }

    public function openFundIn(): void  { $this->showFundModal = true; $this->fundType = 'in'; }
    public function openFundOut(): void { $this->showFundModal = true; $this->fundType = 'out'; }

    public function submitFund(): void
    {
        // Real: POST /api/v1/backoffice/agents/{id}/fund-in  or  fund-out
        // Returns 201 ApprovalRequestResponse — maker-checker
        $this->notify("Agent fund-{$this->fundType} submitted for approval.", 'success');
        $this->showFundModal = false;
        $this->fundAmount = '';
        $this->fundNotes = '';
    }

    public function approveKyc(): void
    {
        // Real: POST /api/v1/backoffice/agents/{id}/approve-kyc
        $this->notify('KYC approved. Agent is now active.', 'success');
        $this->showKycApproval = false;
    }

    private function notify(string $msg, string $type = 'success'): void
    {
        $this->notification = $msg;
        $this->notificationType = $type;
    }

    public function render(): \Illuminate\View\View
    {
        $all = MockDataService::agents([
            'search' => $this->search,
            'status' => $this->statusFilter ?: null,
        ]);
        return view('livewire.agents.agent-list', ['rows' => $all, 'total' => count($all)]);
    }
};
?>

<div>
    <x-page-header
        title="Agents"
        subtitle="Manage the agent network and float operations"
    />

    @if($notification)
    <div class="alert alert-{{ $notificationType }} mb-4">
        <x-icon name="{{ $notificationType === 'success' ? 'check' : 'alert-triangle' }}" size="15" />
        {{ $notification }}
    </div>
    @endif

    <div class="card">
        <div class="filter-bar">
            <div class="filter-search">
                <span class="filter-search-icon"><x-icon name="search" size="14" /></span>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search name, reference, zone…" />
            </div>
            <select wire:model.live="statusFilter" class="filter-select">
                <option value="">All statuses</option>
                <option value="ACTIVE">Active</option>
                <option value="PENDING_KYC">Pending KYC</option>
                <option value="SUSPENDED">Suspended</option>
                <option value="CLOSED">Closed</option>
            </select>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Zone</th>
                        <th>KYC</th>
                        <th>Capabilities</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr class="table-row-link" wire:click="selectRow('{{ $row['id'] }}')">
                        <td>
                            <div class="font-medium">{{ $row['fullName'] }}</div>
                            <x-mono>{{ $row['externalRef'] }}</x-mono>
                        </td>
                        <td><span class="text-[13px]">{{ $row['zone'] ?? '—' }}</span></td>
                        <td><x-badge :status="$row['kycLevel']" /></td>
                        <td>
                            <div class="flex gap-1">
                                @if($row['canDoCashIn'])  <span class="badge badge-active !text-[9px]">CI</span>  @endif
                                @if($row['canDoCashOut']) <span class="badge badge-active !text-[9px]">CO</span>  @endif
                                @if($row['canSellCards']) <span class="badge badge-kyc-basic !text-[9px]">Cards</span> @endif
                            </div>
                        </td>
                        <td><x-badge :status="$row['status']" /></td>
                        <td><x-mono>{{ \Carbon\Carbon::parse($row['createdAt'])->format('d M Y') }}</x-mono></td>
                    </tr>
                    @empty
                    <tr><td colspan="6"><div class="empty-state"><div class="empty-state-title">No agents found</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <span class="pagination-info">{{ $total }} total</span>
        </div>
    </div>

    {{-- Drawer --}}
    @if($selected)
    <div class="drawer-overlay" wire:click="closeDrawer"></div>
    <div class="drawer">
        <div class="drawer-header">
            <span class="drawer-title">{{ $selected['fullName'] }}</span>
            <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
        </div>
        <div class="drawer-body">
            <div class="mb-4 flex gap-2">
                <x-badge :status="$selected['status']" />
                <x-badge :status="$selected['kycLevel']" />
            </div>

            <div class="drawer-section">
                <div class="drawer-section-title">Identity</div>
                <div class="drawer-field"><span class="drawer-field-label">Ref</span><span class="drawer-field-value">{{ $selected['externalRef'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Phone</span><span class="drawer-field-value">{{ $selected['phoneCountryCode'] }} {{ $selected['phoneNumber'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Zone</span><span class="drawer-field-value">{{ $selected['zone'] ?? '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Contract Ref</span><span class="drawer-field-value">{{ $selected['contractRef'] ?? '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Created</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['createdAt'])->format('d M Y') }}</span></div>
            </div>

            <div class="drawer-section">
                <div class="drawer-section-title">Capabilities</div>
                <div class="drawer-field">
                    <span class="drawer-field-label">Cash-In</span>
                    <x-badge :status="$selected['canDoCashIn'] ? 'ACTIVE' : 'INACTIVE'" :label="$selected['canDoCashIn'] ? 'Enabled' : 'Disabled'" />
                </div>
                <div class="drawer-field">
                    <span class="drawer-field-label">Cash-Out</span>
                    <x-badge :status="$selected['canDoCashOut'] ? 'ACTIVE' : 'INACTIVE'" :label="$selected['canDoCashOut'] ? 'Enabled' : 'Disabled'" />
                </div>
                <div class="drawer-field">
                    <span class="drawer-field-label">Sell Cards</span>
                    <x-badge :status="$selected['canSellCards'] ? 'ACTIVE' : 'INACTIVE'" :label="$selected['canSellCards'] ? 'Enabled' : 'Disabled'" />
                </div>
            </div>

            <div class="drawer-field"><span class="drawer-field-label">Wallet ID</span><span class="drawer-field-value">{{ $selected['walletId'] }}</span></div>
            <div class="drawer-field"><span class="drawer-field-label">Limit Profile</span><span class="drawer-field-value">{{ $selected['limitProfileId'] ?? 'None' }}</span></div>

            {{-- Fund Modal --}}
            @if($showFundModal)
            <div class="drawer-section mt-4">
                <div class="drawer-section-title">Agent Fund {{ strtoupper($fundType) }} — Maker Request</div>
                <p class="mb-3 text-xs text-[var(--text-secondary)]">
                    This creates an approval request. A checker must approve before the wallet is mutated.
                </p>
                <div class="flex flex-col gap-2.5">
                    <div>
                        <label class="form-label">Amount (KMF) <span class="form-required">*</span></label>
                        <input wire:model="fundAmount" type="number" class="form-input is-mono" placeholder="e.g. 100000" min="1" />
                    </div>
                    <div>
                        <label class="form-label">Notes</label>
                        <textarea wire:model="fundNotes" class="form-textarea" rows="2" placeholder="Optional notes…"></textarea>
                    </div>
                    <div class="flex gap-2">
                        <button class="btn btn-primary btn-sm" wire:click="submitFund">Submit Request</button>
                        <button class="btn btn-secondary btn-sm" wire:click="$set('showFundModal', false)">Cancel</button>
                    </div>
                </div>
            </div>
            @endif

            {{-- KYC Approval --}}
            @if($showKycApproval && $selected['status'] === 'PENDING_KYC')
            <div class="drawer-section mt-4">
                <div class="drawer-section-title">Approve KYC</div>
                <div class="flex flex-col gap-2.5">
                    <div>
                        <label class="form-label">KYC Level to grant</label>
                        <select wire:model="kycLevel" class="form-select">
                            <option value="KYC_BASIC">KYC BASIC</option>
                            <option value="KYC_VERIFIED">KYC VERIFIED</option>
                            <option value="KYC_ENHANCED">KYC ENHANCED</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button class="btn btn-primary btn-sm" wire:click="approveKyc">Approve & Activate</button>
                        <button class="btn btn-secondary btn-sm" wire:click="$set('showKycApproval', false)">Cancel</button>
                    </div>
                </div>
            </div>
            @endif
        </div>

        @if(!$showFundModal && !$showKycApproval)
        <div class="drawer-footer">
            @if($selected['status'] === 'PENDING_KYC')
                <button class="btn btn-primary btn-sm" wire:click="$set('showKycApproval', true)">Approve KYC</button>
            @endif
            <button class="btn btn-warning btn-sm" wire:click="openFundIn">Fund In</button>
            <button class="btn btn-secondary btn-sm" wire:click="openFundOut">Fund Out</button>
            <a href="{{ route('wallets') }}" class="btn btn-ghost btn-sm">Wallet</a>
        </div>
        @endif
    </div>
    @endif
</div>
