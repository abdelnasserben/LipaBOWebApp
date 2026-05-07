<?php

use Livewire\Component;
use App\Services\Mock\MockDataService;

new class extends Component
{
    public string $search = '';
    public string $statusFilter = '';
    public ?array $selected = null;
    public bool $showKycApproval = false;
    public string $kycLevel = 'KYC_BASIC';
    public string $notification = '';

    public function selectRow(string $id): void { $this->selected = MockDataService::merchant($id); }
    public function closeDrawer(): void { $this->selected = null; $this->showKycApproval = false; }

    public function toggleM2m(string $enable): void
    {
        // Real: POST /api/v1/backoffice/merchants/{id}/m2m/enable  or /disable
        $this->notification = 'M2M ' . ($enable === '1' ? 'enabled' : 'disabled') . ' successfully.';
    }

    public function approveKyc(): void
    {
        // Real: POST /api/v1/backoffice/merchants/{id}/approve-kyc
        $this->notification = 'KYC approved. Merchant is now active.';
        $this->showKycApproval = false;
    }

    public function render(): \Illuminate\View\View
    {
        $all = MockDataService::merchants([
            'search' => $this->search,
            'status' => $this->statusFilter ?: null,
        ]);
        return view('livewire.merchants.merchant-list', ['rows' => $all, 'total' => count($all)]);
    }
};
?>

<div>
    <x-page-header
        title="Merchants"
        subtitle="Manage merchant accounts and payment features"
    />

    @if($notification)
    <div class="alert alert-success mb-4">
        <x-icon name="check" size="15" /> {{ $notification }}
    </div>
    @endif

    <div class="card">
        <div class="filter-bar">
            <div class="filter-search">
                <span class="filter-search-icon"><x-icon name="search" size="14" /></span>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search business name, ref…" />
            </div>
            <select wire:model.live="statusFilter" class="filter-select">
                <option value="">All statuses</option>
                <option value="ACTIVE">Active</option>
                <option value="PENDING_KYC">Pending KYC</option>
                <option value="SUSPENDED">Suspended</option>
                <option value="FROZEN">Frozen</option>
                <option value="CLOSED">Closed</option>
            </select>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Merchant</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>KYC</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr class="table-row-link" wire:click="selectRow('{{ $row['id'] }}')">
                        <td>
                            <div class="font-medium">{{ $row['businessName'] }}</div>
                            <x-mono>{{ $row['externalRef'] }}</x-mono>
                        </td>
                        <td><span class="text-xs">{{ $row['category'] }}</span></td>
                        <td><span class="text-xs">{{ str_replace('_', ' ', $row['businessType']) }}</span></td>
                        <td><x-badge :status="$row['kycLevel']" /></td>
                        <td><x-badge :status="$row['status']" /></td>
                        <td><x-mono>{{ \Carbon\Carbon::parse($row['createdAt'])->format('d M Y') }}</x-mono></td>
                    </tr>
                    @empty
                    <tr><td colspan="6"><div class="empty-state"><div class="empty-state-title">No merchants found</div></div></td></tr>
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
            <span class="drawer-title">{{ $selected['businessName'] }}</span>
            <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
        </div>
        <div class="drawer-body">
            <div class="mb-4 flex gap-2">
                <x-badge :status="$selected['status']" />
                <x-badge :status="$selected['kycLevel']" />
            </div>

            <div class="drawer-section">
                <div class="drawer-section-title">Business</div>
                <div class="drawer-field"><span class="drawer-field-label">Ref</span><span class="drawer-field-value">{{ $selected['externalRef'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Legal Name</span><span class="drawer-field-value">{{ $selected['legalName'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Type</span><span class="drawer-field-value">{{ str_replace('_', ' ', $selected['businessType']) }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Category</span><span class="drawer-field-value">{{ $selected['category'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Tax ID</span><span class="drawer-field-value">{{ $selected['taxId'] ?? '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Phone</span><span class="drawer-field-value">{{ $selected['phoneCountryCode'] }} {{ $selected['phoneNumber'] }}</span></div>
            </div>

            <div class="drawer-section">
                <div class="drawer-section-title">Payment Features</div>
                <div class="drawer-field">
                    <span class="drawer-field-label">Cash Out</span>
                    <x-badge :status="$selected['canCashOut'] ? 'ACTIVE' : 'INACTIVE'" :label="$selected['canCashOut'] ? 'Enabled' : 'Disabled'" />
                </div>
                <div class="drawer-field">
                    <span class="drawer-field-label">M2M Receive</span>
                    <div class="flex items-center gap-2">
                        <x-badge :status="$selected['canReceiveFromMerchant'] ? 'ACTIVE' : 'INACTIVE'" :label="$selected['canReceiveFromMerchant'] ? 'Enabled' : 'Disabled'" />
                        @if($selected['status'] === 'ACTIVE')
                            @if($selected['canReceiveFromMerchant'])
                                <button class="btn btn-secondary btn-sm" wire:click="toggleM2m('0')">Disable M2M</button>
                            @else
                                <button class="btn btn-primary btn-sm" wire:click="toggleM2m('1')">Enable M2M</button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            <div class="drawer-field"><span class="drawer-field-label">Wallet ID</span><span class="drawer-field-value">{{ $selected['walletId'] }}</span></div>
            <div class="drawer-field"><span class="drawer-field-label">Created</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['createdAt'])->format('d M Y') }}</span></div>

            @if($showKycApproval)
            <div class="drawer-section mt-4">
                <div class="drawer-section-title">Approve KYC</div>
                <select wire:model="kycLevel" class="form-select mb-2.5">
                    <option value="KYC_BASIC">KYC BASIC</option>
                    <option value="KYC_VERIFIED">KYC VERIFIED</option>
                    <option value="KYC_ENHANCED">KYC ENHANCED</option>
                </select>
                <div class="flex gap-2">
                    <button class="btn btn-primary btn-sm" wire:click="approveKyc">Approve & Activate</button>
                    <button class="btn btn-secondary btn-sm" wire:click="$set('showKycApproval', false)">Cancel</button>
                </div>
            </div>
            @endif
        </div>

        @if(!$showKycApproval)
        <div class="drawer-footer">
            @if($selected['status'] === 'PENDING_KYC')
                <button class="btn btn-primary btn-sm" wire:click="$set('showKycApproval', true)">Approve KYC</button>
            @elseif($selected['status'] === 'ACTIVE')
                <button class="btn btn-warning btn-sm">Suspend</button>
            @elseif($selected['status'] === 'SUSPENDED')
                <button class="btn btn-primary btn-sm">Reactivate</button>
            @endif
            <button class="btn btn-danger btn-sm">Request Closure</button>
        </div>
        @endif
    </div>
    @endif
</div>
