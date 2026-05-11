<?php

use Livewire\Component;
use App\Enums\Backoffice\BusinessType;
use App\Enums\Backoffice\KycLevel;
use App\Enums\Backoffice\MerchantCategory;
use App\Enums\Backoffice\MerchantStatus;
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
    public bool $showKycApproval = false;
    public string $kycLevel = 'KYC_BASIC';

    public bool $showSuspendConfirm = false;
    public bool $showReactivateConfirm = false;
    public bool $showCloseModal = false;
    public bool $showLimitProfileForm = false;
    public string $actionReason = '';
    public string $limitProfileId = '';

    // Create-merchant form
    public bool $showCreateModal = false;
    public string $newBusinessName = '';
    public string $newLegalName = '';
    public string $newBusinessType = 'COMPANY';
    public string $newCategory = 'RETAIL';
    public string $newTaxId = '';
    public string $newPhoneCountryCode = '+269';
    public string $newPhoneNumber = '';
    public string $newAddressIsland = '';
    public string $newAddressCity = '';
    public string $newAddressDistrict = '';

    public string $notification = '';
    public string $notificationType = 'success';

    public function selectRow(string $id): void { $this->selected = $this->api()->merchant($id); }

    public function closeDrawer(): void
    {
        $this->selected = null;
        $this->showKycApproval = false;
        $this->showSuspendConfirm = false;
        $this->showReactivateConfirm = false;
        $this->showCloseModal = false;
        $this->showLimitProfileForm = false;
        $this->actionReason = '';
        $this->limitProfileId = '';
    }

    public function confirmSuspend(): void    { $this->showSuspendConfirm = true; }
    public function confirmReactivate(): void { $this->showReactivateConfirm = true; }
    public function openCloseModal(): void    { $this->showCloseModal = true; }
    public function openLimitProfileForm(): void
    {
        $this->showLimitProfileForm = true;
        $this->limitProfileId = (string) ($this->selected['limitProfileId'] ?? '');
    }

    public function toggleM2m(string $enable): void
    {
        $this->api()->setMerchantM2M($this->selected['id'], $enable === '1');
        $this->notify('M2M ' . ($enable === '1' ? 'enabled' : 'disabled') . ' successfully.', 'success');
        $this->selected = $this->api()->merchant($this->selected['id']);
    }

    public function approveKyc(): void
    {
        $this->validate([
            'kycLevel' => 'required|' . BackofficeEnums::validationRule(KycLevel::class, BackofficeEnumSets::grantableKycLevels()),
        ]);

        $this->api()->approveMerchantKyc($this->selected['id'], ['kycLevel' => $this->kycLevel]);
        $this->notify('KYC approved. Merchant is now active.', 'success');
        $this->selected = $this->api()->merchant($this->selected['id']);
        $this->showKycApproval = false;
    }

    public function suspendMerchant(): void
    {
        $this->api()->suspendMerchant($this->selected['id'], $this->actionReason);
        $this->notify('Merchant suspended successfully.', 'success');
        $this->closeDrawer();
    }

    public function reactivateMerchant(): void
    {
        $this->api()->reactivateMerchant($this->selected['id']);
        $this->notify('Merchant reactivated successfully.', 'success');
        $this->closeDrawer();
    }

    public function requestClosure(): void
    {
        $this->api()->requestMerchantClosure($this->selected['id'], $this->actionReason);
        $this->notify('Account closure request submitted for approval.', 'success');
        $this->closeDrawer();
    }

    public function assignLimitProfile(): void
    {
        if (! $this->selected || ! $this->canWriteLimitProfiles()) {
            $this->notify('You do not have permission to assign limit profiles.', 'danger');
            return;
        }

        $this->validate([
            'limitProfileId' => 'required|string',
        ]);

        $this->api()->assignMerchantLimitProfile($this->selected['id'], $this->limitProfileId);
        $this->notify('Limit profile assignment submitted for approval.', 'success');
        $this->showLimitProfileForm = false;
        $this->limitProfileId = '';
    }

    public function openCreateModal(): void
    {
        $this->showCreateModal = true;
        $this->notification = '';
        $this->newBusinessName = '';
        $this->newLegalName = '';
        $this->newBusinessType = 'COMPANY';
        $this->newCategory = 'RETAIL';
        $this->newTaxId = '';
        $this->newPhoneCountryCode = '+269';
        $this->newPhoneNumber = '';
        $this->newAddressIsland = '';
        $this->newAddressCity = '';
        $this->newAddressDistrict = '';
    }

    public function createMerchant(): void
    {
        $this->validate([
            'newBusinessName' => 'required|string|max:255',
            'newLegalName' => 'required|string|max:255',
            'newBusinessType' => 'required|' . BackofficeEnums::validationRule(BusinessType::class),
            'newCategory' => 'required|' . BackofficeEnums::validationRule(MerchantCategory::class),
            'newTaxId' => 'nullable|string|max:100',
            'newPhoneCountryCode' => 'required|string|max:10',
            'newPhoneNumber' => 'required|string|max:20',
            'newAddressIsland' => 'nullable|string|max:100',
            'newAddressCity' => 'nullable|string|max:100',
            'newAddressDistrict' => 'nullable|string|max:100',
        ]);

        $this->api()->createMerchant([
            'businessName' => trim($this->newBusinessName),
            'legalName' => trim($this->newLegalName),
            'businessType' => $this->newBusinessType,
            'category' => $this->newCategory,
            'taxId' => trim($this->newTaxId),
            'phoneCountryCode' => trim($this->newPhoneCountryCode),
            'phoneNumber' => trim($this->newPhoneNumber),
            'addressIsland' => trim($this->newAddressIsland),
            'addressCity' => trim($this->newAddressCity),
            'addressDistrict' => trim($this->newAddressDistrict),
        ]);
        $this->notify("Merchant '{$this->newBusinessName}' created. Pending KYC approval.", 'success');
        $this->showCreateModal = false;
    }

    private function notify(string $msg, string $type = 'success'): void
    {
        $this->notification = $msg;
        $this->notificationType = $type;
    }

    public function canWriteLimitProfiles(): bool
    {
        $permissions = session('bo_user.permissions', []);

        return is_array($permissions) && in_array('LIMIT_PROFILE_WRITE', $permissions, true);
    }

    private function limitProfileOptions(string $actorType): array
    {
        if (! $this->showLimitProfileForm || ! $this->canWriteLimitProfiles()) {
            return [];
        }

        return array_values(array_filter(
            $this->api()->limitProfiles(),
            fn (array $profile): bool => in_array($actorType, $profile['applicableActorTypes'] ?? [], true),
        ));
    }

    public function render(): \Illuminate\View\View
    {
        $baseFilters = [
            'search' => $this->search,
        ];
        $all = $this->api()->merchants($baseFilters + [
            'status' => $this->statusFilter ?: null,
        ]);
        $statusRows = $this->api()->merchants($baseFilters);

        return view('livewire.merchants.merchant-list', [
            'rows' => $all,
            'total' => count($all),
            'statusOptions' => BackofficeEnums::optionsFromRows($statusRows, 'status', MerchantStatus::class, $this->statusFilter),
            'businessTypeOptions' => BackofficeEnums::options(BusinessType::class),
            'categoryOptions' => BackofficeEnums::options(MerchantCategory::class),
            'kycLevelOptions' => BackofficeEnums::options(KycLevel::class, BackofficeEnumSets::grantableKycLevels()),
            'limitProfileOptions' => $this->limitProfileOptions('MERCHANT'),
        ]);
    }
};
?>

<div>
    <x-page-header
        title="Merchants"
        subtitle="Manage merchant accounts and payment features"
    >
        <x-slot:actions>
            <button class="btn btn-primary btn-md" wire:click="openCreateModal">
                <x-icon name="plus" size="14" /> New Merchant
            </button>
        </x-slot:actions>
    </x-page-header>

    @if($notification)
    <div class="alert alert-{{ $notificationType }} mb-4">
        <x-icon name="{{ $notificationType === 'success' ? 'check' : 'alert-triangle' }}" size="15" /> {{ $notification }}
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
                @foreach($statusOptions as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
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
                        <td><span class="text-xs">{{ $this->enumLabel($row['category']) }}</span></td>
                        <td><span class="text-xs">{{ $this->enumLabel($row['businessType']) }}</span></td>
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

    {{-- Create Merchant Modal --}}
    @if($showCreateModal)
    <div class="drawer-overlay" wire:click="$set('showCreateModal', false)"></div>
    <div class="drawer">
        <div class="drawer-header">
            <span class="drawer-title">New Merchant</span>
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
                Creates a new merchant in <strong>PENDING_KYC</strong> status. KYC must be approved before the merchant can transact.
            </p>
            <div class="flex flex-col gap-3">
                <div>
                    <label class="form-label">Business Name <span class="form-required">*</span></label>
                    <input wire:model="newBusinessName" type="text" class="form-input" placeholder="e.g. Comoros Fresh Market" maxlength="255" />
                    @error('newBusinessName') <div class="form-error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="form-label">Legal Name <span class="form-required">*</span></label>
                    <input wire:model="newLegalName" type="text" class="form-input" placeholder="e.g. SARL Comoros Fresh" maxlength="255" />
                    @error('newLegalName') <div class="form-error">{{ $message }}</div> @enderror
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="form-label">Business Type <span class="form-required">*</span></label>
                        <select wire:model="newBusinessType" class="form-select">
                            @foreach($businessTypeOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                        @error('newBusinessType') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="form-label">Category <span class="form-required">*</span></label>
                        <select wire:model="newCategory" class="form-select">
                            @foreach($categoryOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                        @error('newCategory') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div>
                    <label class="form-label">Tax ID</label>
                    <input wire:model="newTaxId" type="text" class="form-input is-mono" placeholder="e.g. KM12345678" maxlength="100" />
                    @error('newTaxId') <div class="form-error">{{ $message }}</div> @enderror
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div class="col-span-1">
                        <label class="form-label">Country Code <span class="form-required">*</span></label>
                        <input wire:model="newPhoneCountryCode" type="text" class="form-input is-mono" placeholder="+269" maxlength="10" />
                        @error('newPhoneCountryCode') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Phone Number <span class="form-required">*</span></label>
                        <input wire:model="newPhoneNumber" type="text" class="form-input is-mono" placeholder="7701010" maxlength="20" />
                        @error('newPhoneNumber') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="form-label">Island</label>
                        <input wire:model="newAddressIsland" type="text" class="form-input" placeholder="e.g. Grande Comore" maxlength="100" />
                        @error('newAddressIsland') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="form-label">City</label>
                        <input wire:model="newAddressCity" type="text" class="form-input" placeholder="e.g. Moroni" maxlength="100" />
                        @error('newAddressCity') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="form-label">District</label>
                        <input wire:model="newAddressDistrict" type="text" class="form-input" placeholder="e.g. Centre" maxlength="100" />
                        @error('newAddressDistrict') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>
        <div class="drawer-footer">
            <button class="btn btn-primary btn-sm" wire:click="createMerchant">
                Create Merchant
            </button>
            <button class="btn btn-secondary btn-sm" wire:click="$set('showCreateModal', false)">Cancel</button>
        </div>
    </div>
    @endif

    {{-- Detail Drawer --}}
    @if($selected)
    <div class="drawer-overlay" wire:click="closeDrawer"></div>
    <div class="drawer">
        <div class="drawer-header">
            <div>
                <span class="drawer-title">{{ $selected['businessName'] }}</span><br>
                <span class="drawer-field-value">{{ $selected['id'] }}</span>
            </div>
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
                <div class="drawer-field"><span class="drawer-field-label">Type</span><span class="drawer-field-value">{{ $this->enumLabel($selected['businessType']) }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Category</span><span class="drawer-field-value">{{ $this->enumLabel($selected['category']) }}</span></div>
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
            <div class="drawer-field"><span class="drawer-field-label">Limit Profile</span><span class="drawer-field-value">{{ $selected['limitProfileId'] ?? 'None' }}</span></div>
            <div class="drawer-field"><span class="drawer-field-label">Created</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['createdAt'])->format('d M Y') }}</span></div>

            @if($showLimitProfileForm)
            <div class="drawer-section mt-4">
                <div class="drawer-section-title">Assign Limit Profile</div>
                <div class="flex flex-col gap-2.5">
                    <div>
                        <label class="form-label">Limit Profile <span class="form-required">*</span></label>
                        <select wire:model="limitProfileId" class="form-select">
                            <option value="">{{ empty($limitProfileOptions) ? 'No compatible profiles' : 'Select limit profile' }}</option>
                            @foreach($limitProfileOptions as $profile)
                                <option value="{{ $profile['id'] }}">
                                    {{ $profile['name'] ?? $profile['id'] }} ({{ $profile['id'] }})@if(!($profile['active'] ?? true)) - inactive @endif
                                </option>
                            @endforeach
                        </select>
                        @error('limitProfileId') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="flex gap-2">
                        <button class="btn btn-primary btn-sm" wire:click="assignLimitProfile" @disabled(empty($limitProfileOptions))>Submit Request</button>
                        <button class="btn btn-secondary btn-sm" wire:click="$set('showLimitProfileForm', false)">Cancel</button>
                    </div>
                </div>
            </div>
            @endif

            {{-- KYC Approval --}}
            @if($showKycApproval)
            <div class="drawer-section mt-4">
                <div class="drawer-section-title">Approve KYC</div>
                <select wire:model="kycLevel" class="form-select mb-2.5">
                    @foreach($kycLevelOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button class="btn btn-primary btn-sm" wire:click="approveKyc">Approve & Activate</button>
                    <button class="btn btn-secondary btn-sm" wire:click="$set('showKycApproval', false)">Cancel</button>
                </div>
            </div>
            @endif

            {{-- Suspend confirm --}}
            @if($showSuspendConfirm)
            <div class="alert alert-warning">
                <div>
                    <strong>Confirm suspension?</strong>
                    <br />This will prevent the merchant from receiving payments.
                    <div class="mt-2.5 flex gap-2">
                        <button class="btn btn-danger btn-sm" wire:click="suspendMerchant">Yes, suspend</button>
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
                        <button class="btn btn-primary btn-sm" wire:click="reactivateMerchant">Yes, reactivate</button>
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

        @if(!$showKycApproval && !$showSuspendConfirm && !$showReactivateConfirm && !$showCloseModal && !$showLimitProfileForm)
        <div class="drawer-footer">
            @if($this->canWriteLimitProfiles())
                <button class="btn btn-primary btn-sm" wire:click="openLimitProfileForm">Change Limit Profile</button>
            @endif
            @if($selected['status'] === 'PENDING_KYC')
                <button class="btn btn-primary btn-sm" wire:click="$set('showKycApproval', true)">Approve KYC</button>
            @elseif($selected['status'] === 'ACTIVE')
                <button class="btn btn-warning btn-sm" wire:click="confirmSuspend">Suspend</button>
            @elseif(in_array($selected['status'], ['SUSPENDED', 'FROZEN']))
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
