<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Services\Api\UsesBackofficeApi;

new class extends Component
{
    use WithPagination;
    use UsesBackofficeApi;

    public string $search = '';
    public string $statusFilter = '';
    public ?array $selected = null;
    public bool $showSuspendConfirm = false;
    public bool $showReactivateConfirm = false;
    public bool $showCloseModal = false;
    public string $actionReason = '';
    public string $notification = '';
    public string $notificationType = 'success';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    public function selectRow(string $id): void
    {
        $this->selected = $this->api()->customer($id);
    }

    public function closeDrawer(): void
    {
        $this->selected = null;
        $this->showSuspendConfirm = false;
        $this->showReactivateConfirm = false;
        $this->showCloseModal = false;
        $this->actionReason = '';
    }

    public function confirmSuspend(): void   { $this->showSuspendConfirm = true; }
    public function confirmReactivate(): void { $this->showReactivateConfirm = true; }
    public function openCloseModal(): void    { $this->showCloseModal = true; }

    public function suspendCustomer(): void
    {
        $this->api()->suspendCustomer($this->selected['id'], $this->actionReason);
        $this->notify('Customer suspended successfully.', 'success');
        $this->closeDrawer();
    }

    public function reactivateCustomer(): void
    {
        $this->api()->reactivateCustomer($this->selected['id']);
        $this->notify('Customer reactivated successfully.', 'success');
        $this->closeDrawer();
    }

    public function requestClosure(): void
    {
        $this->api()->requestCustomerClosure($this->selected['id'], $this->actionReason);
        $this->notify('Account closure request submitted for approval.', 'success');
        $this->closeDrawer();
    }

    private function notify(string $msg, string $type = 'success'): void
    {
        $this->notification = $msg;
        $this->notificationType = $type;
    }

    public function render(): \Illuminate\View\View
    {
        $all = $this->api()->customers([
            'search' => $this->search,
            'status' => $this->statusFilter ?: null,
        ]);
        $perPage = 10;
        $page = $this->getPage();
        $total = count($all);
        $rows = array_slice($all, ($page - 1) * $perPage, $perPage);
        return view('livewire.customers.customer-list', compact('rows', 'total', 'perPage', 'page'));
    }
};
?>

<div>
    <x-page-header
        title="Customers"
        subtitle="Browse and manage customer accounts"
    />

    {{-- Notification --}}
    @if($notification)
    <div class="alert alert-{{ $notificationType }} mb-4">
        <x-icon name="{{ $notificationType === 'success' ? 'check' : 'alert-triangle' }}" size="15" />
        {{ $notification }}
    </div>
    @endif

    <div class="card">
        {{-- Filter Bar --}}
        <div class="filter-bar">
            <div class="filter-search">
                <span class="filter-search-icon"><x-icon name="search" size="14" /></span>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search name, reference, phone…" />
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

        {{-- Table --}}
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>KYC Level</th>
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
                        <td><x-mono>{{ $row['phoneCountryCode'] }} {{ $row['phoneNumber'] }}</x-mono></td>
                        <td><x-badge :status="$row['kycLevel']" /></td>
                        <td><x-badge :status="$row['status']" /></td>
                        <td><x-mono>{{ \Carbon\Carbon::parse($row['createdAt'])->format('d M Y') }}</x-mono></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <div class="empty-state-icon">👤</div>
                                <div class="empty-state-title">No customers found</div>
                                <div class="empty-state-text">Try adjusting your filters.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="pagination">
            <span class="pagination-info">{{ $total }} total • showing {{ count($rows) }}</span>
            <div class="pagination-controls">
                <button class="pagination-btn" wire:click="previousPage" @disabled($page <= 1)>‹</button>
                <button class="pagination-btn active">{{ $page }}</button>
                <button class="pagination-btn" wire:click="nextPage" @disabled(($page * $perPage) >= $total)>›</button>
            </div>
        </div>
    </div>

    {{-- Detail Drawer --}}
    @if($selected)
    <div class="drawer-overlay" wire:click="closeDrawer"></div>
    <div class="drawer">
        <div class="drawer-header">
            <span class="drawer-title">{{ $selected['fullName'] }}</span>
            <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
        </div>
        <div class="drawer-body">

            {{-- Status badges --}}
            <div class="mb-4 flex gap-2">
                <x-badge :status="$selected['status']" />
                <x-badge :status="$selected['kycLevel']" />
            </div>

            {{-- Identity --}}
            <div class="drawer-section">
                <div class="drawer-section-title">Identity</div>
                <div class="drawer-field">
                    <span class="drawer-field-label">External Ref</span>
                    <span class="drawer-field-value">{{ $selected['externalRef'] }}</span>
                </div>
                <div class="drawer-field">
                    <span class="drawer-field-label">Full Name</span>
                    <span class="drawer-field-value">{{ $selected['fullName'] }}</span>
                </div>
                <div class="drawer-field">
                    <span class="drawer-field-label">Date of Birth</span>
                    <span class="drawer-field-value">{{ $selected['dateOfBirth'] ?? '—' }}</span>
                </div>
                <div class="drawer-field">
                    <span class="drawer-field-label">National ID</span>
                    <span class="drawer-field-value">{{ $selected['nationalIdNumber'] ?? '—' }}</span>
                </div>
                <div class="drawer-field">
                    <span class="drawer-field-label">Phone</span>
                    <span class="drawer-field-value">{{ $selected['phoneCountryCode'] }} {{ $selected['phoneNumber'] }}</span>
                </div>
            </div>

            {{-- Account --}}
            <div class="drawer-section">
                <div class="drawer-section-title">Account</div>
                <div class="drawer-field">
                    <span class="drawer-field-label">Wallet ID</span>
                    <span class="drawer-field-value">{{ $selected['walletId'] }}</span>
                </div>
                <div class="drawer-field">
                    <span class="drawer-field-label">Limit Profile</span>
                    <span class="drawer-field-value">{{ $selected['limitProfileId'] ?? 'None assigned' }}</span>
                </div>
                <div class="drawer-field">
                    <span class="drawer-field-label">KYC Verified</span>
                    <span class="drawer-field-value">{{ isset($selected['kycVerifiedAt']) ? \Carbon\Carbon::parse($selected['kycVerifiedAt'])->format('d M Y') : '—' }}</span>
                </div>
                <div class="drawer-field">
                    <span class="drawer-field-label">Created</span>
                    <span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['createdAt'])->format('d M Y, H:i') }}</span>
                </div>
            </div>

            {{-- Confirm suspend / reactivate inline --}}
            @if($showSuspendConfirm)
            <div class="alert alert-warning">
                <div>
                    <strong>Confirm suspension?</strong>
                    <br />This will prevent the customer from transacting.
                    <div class="mt-2.5 flex gap-2">
                        <button class="btn btn-danger btn-sm" wire:click="suspendCustomer">Yes, suspend</button>
                        <button class="btn btn-secondary btn-sm" wire:click="$set('showSuspendConfirm', false)">Cancel</button>
                    </div>
                </div>
            </div>
            @endif

            @if($showReactivateConfirm)
            <div class="alert alert-info">
                <div>
                    <strong>Confirm reactivation?</strong>
                    <div class="mt-2.5 flex gap-2">
                        <button class="btn btn-primary btn-sm" wire:click="reactivateCustomer">Yes, reactivate</button>
                        <button class="btn btn-secondary btn-sm" wire:click="$set('showReactivateConfirm', false)">Cancel</button>
                    </div>
                </div>
            </div>
            @endif

            @if($showCloseModal)
            <div class="drawer-section">
                <div class="drawer-section-title">Request Account Closure</div>
                <p class="mb-2.5 text-xs text-[var(--text-secondary)]">This will create an approval request for 4-eyes review before closure.</p>
                <label class="form-label">Reason (optional)</label>
                <textarea wire:model="actionReason" class="form-textarea" rows="3" placeholder="Reason for closure…"></textarea>
                <div class="mt-2.5 flex gap-2">
                    <button class="btn btn-danger btn-sm" wire:click="requestClosure">Submit Closure Request</button>
                    <button class="btn btn-secondary btn-sm" wire:click="$set('showCloseModal', false)">Cancel</button>
                </div>
            </div>
            @endif
        </div>

        {{-- Actions footer --}}
        @if(!$showSuspendConfirm && !$showReactivateConfirm && !$showCloseModal)
        <div class="drawer-footer">
            @if($selected['status'] === 'ACTIVE')
                <button class="btn btn-warning btn-sm" wire:click="confirmSuspend">Suspend</button>
            @elseif(in_array($selected['status'], ['SUSPENDED', 'FROZEN']))
                <button class="btn btn-primary btn-sm" wire:click="confirmReactivate">Reactivate</button>
            @endif
            @if(!in_array($selected['status'], ['CLOSED']))
                <button class="btn btn-danger btn-sm" wire:click="openCloseModal">Request Closure</button>
            @endif
            <a href="{{ route('wallets') }}" class="btn btn-secondary btn-sm">View Wallet</a>
        </div>
        @endif
    </div>
    @endif
</div>
