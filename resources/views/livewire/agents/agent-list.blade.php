<?php

use Livewire\Component;
use App\Enums\Backoffice\AgentStatus;
use App\Enums\Backoffice\KycLevel;
use App\Livewire\Concerns\UsesBackofficeEnums;
use App\Services\Api\UsesBackofficeApi;
use App\Support\BackofficeEnums;
use App\Support\BackofficeEnumSets;

new class extends Component
{
    use UsesBackofficeApi;
    use UsesBackofficeEnums;
    public string $search = '';
    public string $statusFilter = '';
    public ?array $selected = null;

    // Action modals
    public bool $showFundModal = false;
    public string $fundType = 'in';
    public string $fundAmount = '';
    public string $fundNotes = '';

    public bool $showKycApproval = false;
    public string $kycLevel = 'KYC_BASIC';

    public bool $showSuspendConfirm = false;
    public bool $showReactivateConfirm = false;
    public bool $showCloseModal = false;
    public string $actionReason = '';

    // Create-agent form
    public bool $showCreateModal = false;
    public string $newFullName = '';
    public string $newPhoneCountryCode = '+269';
    public string $newPhoneNumber = '';
    public string $newZone = '';
    public string $newContractRef = '';

    public string $notification = '';
    public string $notificationType = 'success';

    public function selectRow(string $id): void { $this->selected = $this->api()->agent($id); }

    public function closeDrawer(): void
    {
        $this->selected = null;
        $this->showFundModal = false;
        $this->showKycApproval = false;
        $this->showSuspendConfirm = false;
        $this->showReactivateConfirm = false;
        $this->showCloseModal = false;
        $this->fundAmount = '';
        $this->fundNotes = '';
        $this->actionReason = '';
    }

    public function openFundIn(): void  { $this->showFundModal = true; $this->fundType = 'in'; }
    public function openFundOut(): void { $this->showFundModal = true; $this->fundType = 'out'; }
    public function confirmSuspend(): void    { $this->showSuspendConfirm = true; }
    public function confirmReactivate(): void { $this->showReactivateConfirm = true; }
    public function openCloseModal(): void    { $this->showCloseModal = true; }

    public function submitFund(): void
    {
        $this->validate([
            'fundAmount' => 'required|integer|min:1',
            'fundNotes' => 'nullable|string|max:500',
        ]);

        $this->api()->fundAgent($this->selected['id'], 'fund-' . $this->fundType, [
            'amount' => (int) $this->fundAmount,
            'notes' => trim($this->fundNotes),
        ]);
        $this->notify("Agent fund-{$this->fundType} submitted for approval.", 'success');
        $this->showFundModal = false;
        $this->fundAmount = '';
        $this->fundNotes = '';
    }

    public function approveKyc(): void
    {
        $this->validate([
            'kycLevel' => 'required|' . BackofficeEnums::validationRule(KycLevel::class, BackofficeEnumSets::grantableKycLevels()),
        ]);

        $this->api()->approveAgentKyc($this->selected['id'], ['kycLevel' => $this->kycLevel]);
        $this->notify('KYC approved. Agent is now active.', 'success');
        $this->selected = $this->api()->agent($this->selected['id']);
        $this->showKycApproval = false;
    }

    public function suspendAgent(): void
    {
        $this->api()->suspendAgent($this->selected['id'], $this->actionReason);
        $this->notify('Agent suspended successfully.', 'success');
        $this->closeDrawer();
    }

    public function reactivateAgent(): void
    {
        $this->api()->reactivateAgent($this->selected['id']);
        $this->notify('Agent reactivated successfully.', 'success');
        $this->closeDrawer();
    }

    public function requestClosure(): void
    {
        $this->api()->requestAgentClosure($this->selected['id'], $this->actionReason);
        $this->notify('Account closure request submitted for approval.', 'success');
        $this->closeDrawer();
    }

    public function openCreateModal(): void
    {
        $this->showCreateModal = true;
        $this->notification = '';
        $this->newFullName = '';
        $this->newPhoneCountryCode = '+269';
        $this->newPhoneNumber = '';
        $this->newZone = '';
        $this->newContractRef = '';
    }

    public function createAgent(): void
    {
        $this->validate([
            'newFullName' => 'required|string|max:255',
            'newPhoneCountryCode' => 'required|string|max:10',
            'newPhoneNumber' => 'required|string|max:20',
            'newZone' => 'nullable|string|max:100',
            'newContractRef' => 'nullable|string|max:255',
        ]);

        $this->api()->createAgent([
            'fullName' => trim($this->newFullName),
            'phoneCountryCode' => trim($this->newPhoneCountryCode),
            'phoneNumber' => trim($this->newPhoneNumber),
            'zone' => trim($this->newZone),
            'contractRef' => trim($this->newContractRef),
        ]);
        $this->notify("Agent '{$this->newFullName}' created. Pending KYC approval.", 'success');
        $this->showCreateModal = false;
    }

    private function notify(string $msg, string $type = 'success'): void
    {
        $this->notification = $msg;
        $this->notificationType = $type;
    }

    public function render(): \Illuminate\View\View
    {
        $baseFilters = [
            'search' => $this->search,
        ];
        $all = $this->api()->agents($baseFilters + [
            'status' => $this->statusFilter ?: null,
        ]);
        $statusRows = $this->api()->agents($baseFilters);

        return view('livewire.agents.agent-list', [
            'rows' => $all,
            'total' => count($all),
            'statusOptions' => BackofficeEnums::optionsFromRows($statusRows, 'status', AgentStatus::class, $this->statusFilter),
            'kycLevelOptions' => BackofficeEnums::options(KycLevel::class, BackofficeEnumSets::grantableKycLevels()),
        ]);
    }
};
?>

<div>
    <x-page-header
        title="Agents"
        subtitle="Manage the agent network and float operations"
    >
        <x-slot:actions>
            <button class="btn btn-primary btn-md" wire:click="openCreateModal">
                <x-icon name="plus" size="14" /> New Agent
            </button>
        </x-slot:actions>
    </x-page-header>

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
                @foreach($statusOptions as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
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

    {{-- Create Agent Modal --}}
    @if($showCreateModal)
    <div class="drawer-overlay" wire:click="$set('showCreateModal', false)"></div>
    <div class="drawer">
        <div class="drawer-header">
            <span class="drawer-title">New Agent</span>
            <button class="modal-close" wire:click="$set('showCreateModal', false)"><x-icon name="x" size="18" /></button>
        </div>
        <div class="drawer-body">
            @if($notification && $notificationType === 'danger')
            <div class="alert alert-danger mb-4">
                <x-icon name="alert-triangle" size="15" />
                {{ $notification }}
            </div>
            @endif
            <p class="mb-4 text-xs text-[var(--text-secondary)]">
                Creates a new agent in <strong>PENDING_KYC</strong> status. KYC must be approved before the agent can transact.
            </p>
            <div class="flex flex-col gap-3">
                <div>
                    <label class="form-label">Full Name <span class="form-required">*</span></label>
                    <input wire:model="newFullName" type="text" class="form-input" placeholder="e.g. Rachid Oumouri" />
                    @error('newFullName') <div class="form-error">{{ $message }}</div> @enderror
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div class="col-span-1">
                        <label class="form-label">Country Code <span class="form-required">*</span></label>
                        <input wire:model="newPhoneCountryCode" type="text" class="form-input is-mono" placeholder="+269" maxlength="10" />
                        @error('newPhoneCountryCode') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Phone Number <span class="form-required">*</span></label>
                        <input wire:model="newPhoneNumber" type="text" class="form-input is-mono" placeholder="3101010" maxlength="20" />
                        @error('newPhoneNumber') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div>
                    <label class="form-label">Zone</label>
                    <input wire:model="newZone" type="text" class="form-input" placeholder="e.g. Moroni Centre" maxlength="100" />
                    @error('newZone') <div class="form-error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="form-label">Contract Ref</label>
                    <input wire:model="newContractRef" type="text" class="form-input is-mono" placeholder="e.g. CTR-2025-001" maxlength="255" />
                    @error('newContractRef') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
        <div class="drawer-footer">
            <button class="btn btn-primary btn-sm" wire:click="createAgent">Create Agent</button>
            <button class="btn btn-secondary btn-sm" wire:click="$set('showCreateModal', false)">Cancel</button>
        </div>
    </div>
    @endif

    {{-- Detail Drawer --}}
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
                        @error('fundAmount') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="form-label">Notes</label>
                        <textarea wire:model="fundNotes" class="form-textarea" rows="2" placeholder="Optional notes…"></textarea>
                        @error('fundNotes') <div class="form-error">{{ $message }}</div> @enderror
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
                            @foreach($kycLevelOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button class="btn btn-primary btn-sm" wire:click="approveKyc">Approve & Activate</button>
                        <button class="btn btn-secondary btn-sm" wire:click="$set('showKycApproval', false)">Cancel</button>
                    </div>
                </div>
            </div>
            @endif

            {{-- Suspend confirm --}}
            @if($showSuspendConfirm)
            <div class="alert alert-warning">
                <div>
                    <strong>Confirm suspension?</strong>
                    <br />This will prevent the agent from operating cash-in / cash-out.
                    <div class="mt-2.5 flex gap-2">
                        <button class="btn btn-danger btn-sm" wire:click="suspendAgent">Yes, suspend</button>
                        <button class="btn btn-secondary btn-sm" wire:click="$set('showSuspendConfirm', false)">Cancel</button>
                    </div>
                </div>
            </div>
            @endif

            {{-- Reactivate confirm --}}
            @if($showReactivateConfirm)
            <div class="alert alert-info">
                <div>
                    <strong>Confirm reactivation?</strong>
                    <div class="mt-2.5 flex gap-2">
                        <button class="btn btn-primary btn-sm" wire:click="reactivateAgent">Yes, reactivate</button>
                        <button class="btn btn-secondary btn-sm" wire:click="$set('showReactivateConfirm', false)">Cancel</button>
                    </div>
                </div>
            </div>
            @endif

            {{-- Close-request --}}
            @if($showCloseModal)
            <div class="drawer-section">
                <div class="drawer-section-title">Request Account Closure</div>
                <p class="mb-2.5 text-xs text-[var(--text-secondary)]">This will create an approval request for 4-eyes review before closure.</p>
                <label class="form-label">Reason (optional)</label>
                <textarea wire:model="actionReason" class="form-textarea" rows="3" placeholder="Reason for closure…" maxlength="500"></textarea>
                <div class="mt-2.5 flex gap-2">
                    <button class="btn btn-danger btn-sm" wire:click="requestClosure">Submit Closure Request</button>
                    <button class="btn btn-secondary btn-sm" wire:click="$set('showCloseModal', false)">Cancel</button>
                </div>
            </div>
            @endif
        </div>

        @if(!$showFundModal && !$showKycApproval && !$showSuspendConfirm && !$showReactivateConfirm && !$showCloseModal)
        <div class="drawer-footer">
            @if($selected['status'] === 'PENDING_KYC')
                <button class="btn btn-primary btn-sm" wire:click="$set('showKycApproval', true)">Approve KYC</button>
            @endif
            @if($selected['status'] === 'ACTIVE')
                <button class="btn btn-warning btn-sm" wire:click="openFundIn">Fund In</button>
                <button class="btn btn-secondary btn-sm" wire:click="openFundOut">Fund Out</button>
                <button class="btn btn-warning btn-sm" wire:click="confirmSuspend">Suspend</button>
            @elseif($selected['status'] === 'SUSPENDED')
                <button class="btn btn-primary btn-sm" wire:click="confirmReactivate">Reactivate</button>
            @endif
            @if(!in_array($selected['status'], ['CLOSED']))
                <button class="btn btn-danger btn-sm" wire:click="openCloseModal">Request Closure</button>
            @endif
            <a href="{{ route('wallets') }}" class="btn btn-ghost btn-sm">Wallet</a>
        </div>
        @endif
    </div>
    @endif
</div>
