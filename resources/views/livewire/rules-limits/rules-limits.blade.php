<?php

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Services\Api\UsesBackofficeApi;

new class extends Component
{
    use UsesBackofficeApi;
    #[Url(as: 'tab')]
    public string $tab = 'fees';

    public string $txTypeFilter = '';
    public ?array $selected = null;
    public string $selectedKind = ''; // fee | commission | limit | threshold

    public bool $showCreateModal = false;
    public string $notification = '';

    public array $rulesTransactionTypes = ['CASH_IN', 'CASH_OUT', 'PAYMENT', 'P2P_TRANSFER', 'SERVICE_PAYMENT', 'CARD_SALE'];
    public array $commissionTransactionTypes = ['CASH_IN', 'CASH_OUT', 'PAYMENT', 'CARD_SALE', 'SERVICE_PAYMENT'];
    public array $thresholdTransactionTypes = ['CASH_IN', 'CASH_OUT', 'PAYMENT', 'P2P_TRANSFER', 'SERVICE_PAYMENT'];
    public array $feeCalculationTypes = ['FLAT', 'PERCENTAGE', 'TIERED', 'MAX_OF', 'MIN_OF', 'ZERO'];
    public array $commissionCalculationTypes = ['FLAT', 'ON_TRANSACTION_AMOUNT', 'ON_FEE_AMOUNT'];
    public array $feeBearers = ['SENDER', 'RECIPIENT', 'SHARED'];
    public array $settlementModes = ['BATCH_DAILY', 'BATCH_WEEKLY'];
    public array $actorTypes = ['CUSTOMER', 'AGENT', 'MERCHANT'];
    public array $kycLevels = ['KYC_NONE', 'KYC_BASIC', 'KYC_VERIFIED', 'KYC_ENHANCED'];
    public array $scopeTypes = ['GLOBAL', 'ACTOR'];
    public array $approvalTypes = ['LARGE_CASH_OUT', 'AGENT_FUND_IN', 'AGENT_FUND_OUT', 'REVERSAL'];

    // Create payloads (one per tab; spec-exact field names)
    public array $newFee = [
        'name' => '', 'description' => '', 'transactionType' => 'CASH_IN',
        'calculationType' => 'PERCENTAGE', 'flatAmount' => null, 'percentage' => null,
        'minFeeAmount' => null, 'maxFeeAmount' => null, 'feeBearer' => 'SENDER',
        'priority' => 10, 'validFrom' => '', 'activeOnApproval' => true,
    ];

    public array $newCommission = [
        'name' => '', 'transactionType' => 'CASH_IN', 'agentId' => '',
        'calculationType' => 'ON_FEE_AMOUNT', 'flatAmount' => null, 'percentage' => null,
        'settlementMode' => 'BATCH_DAILY', 'priority' => 10, 'validFrom' => '',
        'activeOnApproval' => true,
    ];

    public array $newLimit = [
        'name' => '', 'applicableActorTypes' => ['CUSTOMER'],
        'requiredKycLevel' => 'KYC_BASIC',
        'maxTransactionAmount' => null, 'minTransactionAmount' => null,
        'maxDailyAmount' => null, 'maxWeeklyAmount' => null, 'maxMonthlyAmount' => null,
        'maxDailyTransactionCount' => null, 'maxMonthlyTransactionCount' => null,
    ];

    public array $newThreshold = [
        'transactionType' => 'CASH_OUT', 'actorType' => 'CUSTOMER',
        'scopeType' => 'GLOBAL', 'scopeId' => '', 'currency' => 'KMF',
        'pinRequiredAboveAmount' => null, 'confirmationRequiredAboveAmount' => null,
        'approvalRequiredAboveAmount' => null, 'approvalType' => '',
    ];

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->closeDrawer();
        $this->txTypeFilter = '';
    }

    public function selectFee(string $id): void        { $this->selected = $this->api()->feeRule($id); $this->selectedKind = 'fee'; }
    public function selectCommission(string $id): void { $this->selected = $this->api()->commissionRule($id); $this->selectedKind = 'commission'; }
    public function selectLimit(string $id): void      { $this->selected = $this->api()->limitProfile($id); $this->selectedKind = 'limit'; }
    public function selectThreshold(string $id): void  { $this->selected = $this->api()->controlThreshold($id); $this->selectedKind = 'threshold'; }

    public function closeDrawer(): void { $this->selected = null; $this->selectedKind = ''; $this->showCreateModal = false; }

    public function openCreate(): void { $this->showCreateModal = true; }

    public function submitCreate(): void
    {
        // Real endpoints (all return 202 ApprovalRequestResponse — maker-checker):
        //   fee:        POST /api/v1/backoffice/fee-rules
        //   commission: POST /api/v1/backoffice/commission-rules
        //   limit:      POST /api/v1/backoffice/limit-profiles
        //   threshold:  POST /api/v1/backoffice/control-thresholds
        $label = match ($this->tab) {
            'fees'        => 'Fee rule',
            'commissions' => 'Commission rule',
            'limits'      => 'Limit profile',
            'thresholds'  => 'Control threshold',
        };
        $this->notify("{$label} submitted for approval.");
        $this->showCreateModal = false;
    }

    public function activate(): void
    {
        if ($this->selected && $this->selectedKind) {
            $this->api()->activateRule($this->selectedKind, $this->selected['id']);
        }
        $this->notify('Activation submitted for approval.');
        $this->closeDrawer();
    }

    public function deactivate(): void
    {
        if ($this->selected && $this->selectedKind) {
            $this->api()->deactivateRule($this->selectedKind, $this->selected['id']);
        }
        $this->notify('Deactivation submitted for approval.');
        $this->closeDrawer();
    }

    private function notify(string $msg): void { $this->notification = $msg; }

    public function enumLabel(?string $value, string $fallback = '—'): string
    {
        return filled($value) ? str_replace('_', ' ', $value) : $fallback;
    }

    public function enumListLabel(array $values): string
    {
        return implode(', ', array_map(fn (string $value): string => $this->enumLabel($value), $values));
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.rules-limits.rules-limits', [
            'feeRules'         => $this->api()->feeRules($this->txTypeFilter ? ['transactionType' => $this->txTypeFilter] : []),
            'commissionRules'  => $this->api()->commissionRules($this->txTypeFilter ? ['transactionType' => $this->txTypeFilter] : []),
            'limitProfiles'    => $this->api()->limitProfiles(),
            'controlThresholds'=> $this->api()->controlThresholds($this->txTypeFilter ? ['transactionType' => $this->txTypeFilter] : []),
        ]);
    }
};
?>

<div>
    <x-page-header
        title="Rules & Limits"
        subtitle="Fees, commissions, limits and control thresholds"
    />

    @if($notification)
    <div class="alert alert-success mb-4"><x-icon name="check" size="15" /> {{ $notification }}</div>
    @endif

    <div class="card">
        <div class="tabs">
            <button class="tab @if($tab==='fees') active @endif" wire:click="setTab('fees')">Fee Profiles</button>
            <button class="tab @if($tab==='commissions') active @endif" wire:click="setTab('commissions')">Commission Profiles</button>
            <button class="tab @if($tab==='limits') active @endif" wire:click="setTab('limits')">Limit Profiles</button>
            <button class="tab @if($tab==='thresholds') active @endif" wire:click="setTab('thresholds')">Control Thresholds</button>
        </div>

        <div class="filter-bar">
            @if(in_array($tab, ['fees','commissions','thresholds']))
            <select wire:model.live="txTypeFilter" class="filter-select">
                <option value="">All transaction types</option>
                @foreach($rulesTransactionTypes as $type)
                    <option value="{{ $type }}">{{ $this->enumLabel($type) }}</option>
                @endforeach
            </select>
            @endif
            <div class="flex-1"></div>
            <button class="btn btn-primary btn-sm" wire:click="openCreate">
                <x-icon name="plus" size="13" />
                @switch($tab)
                    @case('fees') New Fee Rule @break
                    @case('commissions') New Commission Rule @break
                    @case('limits') New Limit Profile @break
                    @case('thresholds') New Threshold @break
                @endswitch
            </button>
        </div>

        {{-- ─── Tab: Fee Profiles ─── --}}
        @if($tab === 'fees')
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Tx Type</th>
                        <th>Calculation</th>
                        <th>Amount</th>
                        <th>Bearer</th>
                        <th>Priority</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($feeRules as $r)
                    <tr class="table-row-link" wire:click="selectFee('{{ $r['id'] }}')">
                        <td>
                            <div class="font-medium">{{ $r['name'] }}</div>
                            <div class="text-[11px] text-[var(--text-secondary)]">v{{ $r['version'] }}</div>
                        </td>
                        <td><span class="text-xs font-medium">{{ $this->enumLabel($r['transactionType']) }}</span></td>
                        <td><span class="text-[12px]">{{ $this->enumLabel($r['calculationType']) }}</span></td>
                        <td>
                            @if($r['calculationType'] === 'PERCENTAGE')
                                <x-mono>{{ rtrim(rtrim(number_format($r['percentage']*100, 4), '0'), '.') }}%</x-mono>
                            @elseif($r['calculationType'] === 'FLAT')
                                <x-amount :value="$r['flatAmount']" />
                            @elseif($r['calculationType'] === 'ZERO')
                                <span class="text-xs text-[var(--text-secondary)]">No fee</span>
                            @else
                                <span class="text-xs text-[var(--text-secondary)]">{{ $this->enumLabel($r['calculationType']) }}</span>
                            @endif
                        </td>
                        <td><span class="text-[12px]">{{ $this->enumLabel($r['feeBearer']) }}</span></td>
                        <td><x-mono>{{ $r['priority'] }}</x-mono></td>
                        <td><x-badge :status="$r['active'] ? 'ACTIVE' : 'INACTIVE'" /></td>
                    </tr>
                    @empty
                    <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No fee rules</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif

        {{-- ─── Tab: Commission Profiles ─── --}}
        @if($tab === 'commissions')
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Tx Type</th>
                        <th>Agent</th>
                        <th>Calculation</th>
                        <th>Amount</th>
                        <th>Settlement</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($commissionRules as $r)
                    <tr class="table-row-link" wire:click="selectCommission('{{ $r['id'] }}')">
                        <td>
                            <div class="font-medium">{{ $r['name'] }}</div>
                            <div class="text-[11px] text-[var(--text-secondary)]">v{{ $r['version'] }}</div>
                        </td>
                        <td><span class="text-xs font-medium">{{ $this->enumLabel($r['transactionType']) }}</span></td>
                        <td>
                            @if($r['agentId']) <x-mono>{{ $r['agentId'] }}</x-mono>
                            @else <span class="text-xs text-[var(--text-secondary)]">All agents</span>
                            @endif
                        </td>
                        <td><span class="text-[12px]">{{ $this->enumLabel($r['calculationType']) }}</span></td>
                        <td>
                            @if($r['calculationType'] === 'FLAT')
                                <x-amount :value="$r['flatAmount']" />
                            @else
                                <x-mono>{{ rtrim(rtrim(number_format($r['percentage']*100, 4), '0'), '.') }}%</x-mono>
                            @endif
                        </td>
                        <td><span class="text-[12px]">{{ $this->enumLabel($r['settlementMode']) }}</span></td>
                        <td><x-badge :status="$r['active'] ? 'ACTIVE' : 'INACTIVE'" /></td>
                    </tr>
                    @empty
                    <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No commission rules</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif

        {{-- ─── Tab: Limit Profiles ─── --}}
        @if($tab === 'limits')
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Profile</th>
                        <th>Applicable Actors</th>
                        <th>Required KYC</th>
                        <th>Max / Tx</th>
                        <th>Max / Day</th>
                        <th>Max / Month</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($limitProfiles as $r)
                    <tr class="table-row-link" wire:click="selectLimit('{{ $r['id'] }}')">
                        <td>
                            <div class="font-medium">{{ $r['name'] }}</div>
                            <x-mono>{{ $r['id'] }}</x-mono>
                        </td>
                        <td>
                            <div class="flex gap-1 flex-wrap">
                                @foreach($r['applicableActorTypes'] as $a)
                                    <span class="badge badge-active !text-[9px]">{{ $this->enumLabel($a) }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td><x-badge :status="$r['requiredKycLevel']" /></td>
                        <td>@if($r['maxTransactionAmount']) <x-amount :value="$r['maxTransactionAmount']" /> @else — @endif</td>
                        <td>@if($r['maxDailyAmount']) <x-amount :value="$r['maxDailyAmount']" /> @else — @endif</td>
                        <td>@if($r['maxMonthlyAmount']) <x-amount :value="$r['maxMonthlyAmount']" /> @else — @endif</td>
                        <td><x-badge :status="$r['active'] ? 'ACTIVE' : 'INACTIVE'" /></td>
                    </tr>
                    @empty
                    <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No limit profiles</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif

        {{-- ─── Tab: Control Thresholds ─── --}}
        @if($tab === 'thresholds')
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Tx Type</th>
                        <th>Actor</th>
                        <th>Scope</th>
                        <th>PIN Above</th>
                        <th>Confirm Above</th>
                        <th>Approval Above</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($controlThresholds as $r)
                    <tr class="table-row-link" wire:click="selectThreshold('{{ $r['id'] }}')">
                        <td><span class="text-xs font-medium">{{ $this->enumLabel($r['transactionType']) }}</span></td>
                        <td><span class="text-[12px]">{{ $this->enumLabel($r['actorType']) }}</span></td>
                        <td>
                            <div class="text-[12px]">{{ $this->enumLabel($r['scopeType']) }}</div>
                            @if($r['scopeId']) <x-mono>{{ $r['scopeId'] }}</x-mono> @endif
                        </td>
                        <td>@if($r['pinRequiredAboveAmount']) <x-amount :value="$r['pinRequiredAboveAmount']" /> @else — @endif</td>
                        <td>@if($r['confirmationRequiredAboveAmount']) <x-amount :value="$r['confirmationRequiredAboveAmount']" /> @else — @endif</td>
                        <td>
                            @if($r['approvalRequiredAboveAmount'])
                                <x-amount :value="$r['approvalRequiredAboveAmount']" />
                                @if($r['approvalType']) <div class="text-[10px] text-[var(--text-secondary)]">{{ $this->enumLabel($r['approvalType']) }}</div> @endif
                            @else — @endif
                        </td>
                        <td><x-badge :status="$r['active'] ? 'ACTIVE' : 'INACTIVE'" /></td>
                    </tr>
                    @empty
                    <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No control thresholds</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ─────────────────────── Detail Drawer ─────────────────────── --}}
    @if($selected)
    <div class="drawer-overlay" wire:click="closeDrawer"></div>
    <div class="drawer">
        <div class="drawer-header">
            <span class="drawer-title">
                @switch($selectedKind)
                    @case('fee') {{ $selected['name'] }} @break
                    @case('commission') {{ $selected['name'] }} @break
                    @case('limit') {{ $selected['name'] }} @break
                    @case('threshold') {{ $this->enumLabel($selected['transactionType']) }} / {{ $this->enumLabel($selected['actorType']) }} @break
                @endswitch
            </span>
            <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
        </div>
        <div class="drawer-body">
            <div class="mb-4 flex gap-2">
                <x-badge :status="$selected['active'] ? 'ACTIVE' : 'INACTIVE'" />
                <span class="text-[11px] text-[var(--text-secondary)]">v{{ $selected['version'] }}</span>
            </div>

            @if($selectedKind === 'fee')
                <div class="drawer-section">
                    <div class="drawer-section-title">Fee Rule</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selected['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Description</span><span class="drawer-field-value">{{ $selected['description'] ?? '—' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Tx Type</span><span class="drawer-field-value">{{ $this->enumLabel($selected['transactionType'] ?? null) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Actor Type</span><span class="drawer-field-value">{{ $selected['actorType'] ? $this->enumLabel($selected['actorType']) : 'Any' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Merchant Cat.</span><span class="drawer-field-value">{{ $selected['merchantCategory'] ? $this->enumLabel($selected['merchantCategory']) : 'Any' }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Calculation</div>
                    <div class="drawer-field"><span class="drawer-field-label">Type</span><span class="drawer-field-value">{{ $this->enumLabel($selected['calculationType']) }}</span></div>
                    @if($selected['flatAmount'])
                        <div class="drawer-field"><span class="drawer-field-label">Flat</span><span class="drawer-field-value"><x-amount :value="$selected['flatAmount']" /></span></div>
                    @endif
                    @if($selected['percentage'])
                        <div class="drawer-field"><span class="drawer-field-label">Percentage</span><span class="drawer-field-value">{{ rtrim(rtrim(number_format($selected['percentage']*100, 4), '0'), '.') }}%</span></div>
                    @endif
                    <div class="drawer-field"><span class="drawer-field-label">Min Fee</span><span class="drawer-field-value">@if($selected['minFeeAmount']) <x-amount :value="$selected['minFeeAmount']" /> @else — @endif</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Max Fee</span><span class="drawer-field-value">@if($selected['maxFeeAmount']) <x-amount :value="$selected['maxFeeAmount']" /> @else — @endif</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Bearer</span><span class="drawer-field-value">{{ $this->enumLabel($selected['feeBearer']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Priority</span><span class="drawer-field-value">{{ $selected['priority'] }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Validity</div>
                    <div class="drawer-field"><span class="drawer-field-label">From</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['validFrom'])->format('d M Y') }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">To</span><span class="drawer-field-value">{{ $selected['validTo'] ? \Carbon\Carbon::parse($selected['validTo'])->format('d M Y') : 'Open-ended' }}</span></div>
                </div>
            @elseif($selectedKind === 'commission')
                <div class="drawer-section">
                    <div class="drawer-section-title">Commission Rule</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selected['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Tx Type</span><span class="drawer-field-value">{{ $this->enumLabel($selected['transactionType']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Agent</span><span class="drawer-field-value">{{ $selected['agentId'] ?? 'All agents' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Calculation</span><span class="drawer-field-value">{{ $this->enumLabel($selected['calculationType']) }}</span></div>
                    @if($selected['flatAmount'])
                        <div class="drawer-field"><span class="drawer-field-label">Flat</span><span class="drawer-field-value"><x-amount :value="$selected['flatAmount']" /></span></div>
                    @endif
                    @if($selected['percentage'])
                        <div class="drawer-field"><span class="drawer-field-label">Percentage</span><span class="drawer-field-value">{{ rtrim(rtrim(number_format($selected['percentage']*100, 4), '0'), '.') }}%</span></div>
                    @endif
                    <div class="drawer-field"><span class="drawer-field-label">Settlement</span><span class="drawer-field-value">{{ $this->enumLabel($selected['settlementMode']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Currency</span><span class="drawer-field-value">{{ $selected['currency'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Priority</span><span class="drawer-field-value">{{ $selected['priority'] }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Validity</div>
                    <div class="drawer-field"><span class="drawer-field-label">From</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['validFrom'])->format('d M Y') }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">To</span><span class="drawer-field-value">{{ $selected['validTo'] ? \Carbon\Carbon::parse($selected['validTo'])->format('d M Y') : 'Open-ended' }}</span></div>
                </div>
            @elseif($selectedKind === 'limit')
                <div class="drawer-section">
                    <div class="drawer-section-title">Limit Profile</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selected['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Required KYC</span><span class="drawer-field-value">{{ $this->enumLabel($selected['requiredKycLevel']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Applies to</span><span class="drawer-field-value">{{ $this->enumListLabel($selected['applicableActorTypes']) }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Amount Limits</div>
                    <div class="drawer-field"><span class="drawer-field-label">Min / Tx</span><span class="drawer-field-value">@if($selected['minTransactionAmount']) <x-amount :value="$selected['minTransactionAmount']" /> @else — @endif</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Max / Tx</span><span class="drawer-field-value">@if($selected['maxTransactionAmount']) <x-amount :value="$selected['maxTransactionAmount']" /> @else — @endif</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Max / Day</span><span class="drawer-field-value">@if($selected['maxDailyAmount']) <x-amount :value="$selected['maxDailyAmount']" /> @else — @endif</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Max / Week</span><span class="drawer-field-value">@if($selected['maxWeeklyAmount']) <x-amount :value="$selected['maxWeeklyAmount']" /> @else — @endif</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Max / Month</span><span class="drawer-field-value">@if($selected['maxMonthlyAmount']) <x-amount :value="$selected['maxMonthlyAmount']" /> @else — @endif</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Count Limits</div>
                    <div class="drawer-field"><span class="drawer-field-label">Daily</span><span class="drawer-field-value">{{ $selected['maxDailyTransactionCount'] ?? '—' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Monthly</span><span class="drawer-field-value">{{ $selected['maxMonthlyTransactionCount'] ?? '—' }}</span></div>
                </div>
            @elseif($selectedKind === 'threshold')
                <div class="drawer-section">
                    <div class="drawer-section-title">Control Threshold</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selected['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Tx Type</span><span class="drawer-field-value">{{ $this->enumLabel($selected['transactionType']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Actor Type</span><span class="drawer-field-value">{{ $this->enumLabel($selected['actorType']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Scope</span><span class="drawer-field-value">{{ $this->enumLabel($selected['scopeType']) }}@if($selected['scopeId']) ({{ $selected['scopeId'] }})@endif</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Currency</span><span class="drawer-field-value">{{ $selected['currency'] }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Thresholds</div>
                    <div class="drawer-field"><span class="drawer-field-label">PIN above</span><span class="drawer-field-value">@if($selected['pinRequiredAboveAmount']) <x-amount :value="$selected['pinRequiredAboveAmount']" /> @else — @endif</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Confirm above</span><span class="drawer-field-value">@if($selected['confirmationRequiredAboveAmount']) <x-amount :value="$selected['confirmationRequiredAboveAmount']" /> @else — @endif</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Approval above</span><span class="drawer-field-value">@if($selected['approvalRequiredAboveAmount']) <x-amount :value="$selected['approvalRequiredAboveAmount']" /> @else — @endif</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Approval Type</span><span class="drawer-field-value">{{ $this->enumLabel($selected['approvalType'] ?? null) }}</span></div>
                </div>
            @endif
        </div>

        <div class="drawer-footer">
            @if($selected['active'])
                <button class="btn btn-warning btn-sm" wire:click="deactivate">Deactivate</button>
            @else
                <button class="btn btn-primary btn-sm" wire:click="activate">Activate</button>
            @endif
        </div>
    </div>
    @endif

    {{-- ─────────────────────── Create Modal ─────────────────────── --}}
    @if($showCreateModal)
    <div class="modal-overlay" wire:click.self="$set('showCreateModal', false)">
        <div class="modal">
            <div class="modal-header">
                <span class="modal-title">
                    @switch($tab)
                        @case('fees') New Fee Rule @break
                        @case('commissions') New Commission Rule @break
                        @case('limits') New Limit Profile @break
                        @case('thresholds') New Control Threshold @break
                    @endswitch
                </span>
                <button class="modal-close" wire:click="$set('showCreateModal', false)"><x-icon name="x" size="18" /></button>
            </div>
            <div class="modal-body">
                <p class="mb-4 text-xs text-[var(--text-secondary)]">
                    Submitting creates an approval request. A second BO user must approve before this configuration takes effect.
                </p>

                {{-- ── Fee Rule form ── --}}
                @if($tab === 'fees')
                <div class="flex flex-col gap-3">
                    <div>
                        <label class="form-label">Name <span class="form-required">*</span></label>
                        <input wire:model="newFee.name" type="text" class="form-input" placeholder="e.g. Standard Cash-In" />
                    </div>
                    <div>
                        <label class="form-label">Description</label>
                        <input wire:model="newFee.description" type="text" class="form-input" placeholder="Short description shown in approval" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Transaction Type <span class="form-required">*</span></label>
                            <select wire:model="newFee.transactionType" class="form-select">
                                @foreach($rulesTransactionTypes as $type)
                                    <option value="{{ $type }}">{{ $this->enumLabel($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Calculation <span class="form-required">*</span></label>
                            <select wire:model.live="newFee.calculationType" class="form-select">
                                @foreach($feeCalculationTypes as $type)
                                    <option value="{{ $type }}">{{ $this->enumLabel($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        @if(in_array($newFee['calculationType'], ['FLAT','MAX_OF','MIN_OF']))
                        <div>
                            <label class="form-label">Flat Amount (KMF)</label>
                            <input wire:model="newFee.flatAmount" type="number" class="form-input is-mono" placeholder="e.g. 100" />
                        </div>
                        @endif
                        @if(in_array($newFee['calculationType'], ['PERCENTAGE','MAX_OF','MIN_OF']))
                        <div>
                            <label class="form-label">Percentage (0–1)</label>
                            <input wire:model="newFee.percentage" type="number" step="0.0001" class="form-input is-mono" placeholder="e.g. 0.01" />
                        </div>
                        @endif
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="form-label">Min Fee</label>
                            <input wire:model="newFee.minFeeAmount" type="number" class="form-input is-mono" placeholder="e.g. 50" />
                        </div>
                        <div>
                            <label class="form-label">Max Fee</label>
                            <input wire:model="newFee.maxFeeAmount" type="number" class="form-input is-mono" placeholder="e.g. 5000" />
                        </div>
                        <div>
                            <label class="form-label">Priority <span class="form-required">*</span></label>
                            <input wire:model="newFee.priority" type="number" min="1" class="form-input is-mono" placeholder="Lower = applied first" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Bearer <span class="form-required">*</span></label>
                            <select wire:model="newFee.feeBearer" class="form-select">
                                @foreach($feeBearers as $bearer)
                                    <option value="{{ $bearer }}">{{ $this->enumLabel($bearer) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Valid From <span class="form-required">*</span></label>
                            <input wire:model="newFee.validFrom" type="datetime-local" class="form-input" />
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-[12px]">
                        <input wire:model="newFee.activeOnApproval" type="checkbox" /> Activate immediately on approval
                    </label>
                </div>
                @endif

                {{-- ── Commission Rule form ── --}}
                @if($tab === 'commissions')
                <div class="flex flex-col gap-3">
                    <div>
                        <label class="form-label">Name <span class="form-required">*</span></label>
                        <input wire:model="newCommission.name" type="text" class="form-input" placeholder="e.g. Agent Cash-In Commission" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Transaction Type <span class="form-required">*</span></label>
                            <select wire:model="newCommission.transactionType" class="form-select">
                                @foreach($commissionTransactionTypes as $type)
                                    <option value="{{ $type }}">{{ $this->enumLabel($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Agent (optional)</label>
                            <input wire:model="newCommission.agentId" type="text" class="form-input is-mono" placeholder="Empty = all agents" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Calculation <span class="form-required">*</span></label>
                            <select wire:model.live="newCommission.calculationType" class="form-select">
                                @foreach($commissionCalculationTypes as $type)
                                    <option value="{{ $type }}">{{ $this->enumLabel($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Settlement Mode <span class="form-required">*</span></label>
                            <select wire:model="newCommission.settlementMode" class="form-select">
                                @foreach($settlementModes as $mode)
                                    <option value="{{ $mode }}">{{ $this->enumLabel($mode) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        @if($newCommission['calculationType'] === 'FLAT')
                        <div>
                            <label class="form-label">Flat Amount (KMF)</label>
                            <input wire:model="newCommission.flatAmount" type="number" class="form-input is-mono" placeholder="e.g. 200" />
                        </div>
                        @else
                        <div>
                            <label class="form-label">Percentage (0–1)</label>
                            <input wire:model="newCommission.percentage" type="number" step="0.0001" class="form-input is-mono" placeholder="e.g. 0.005" />
                        </div>
                        @endif
                        <div>
                            <label class="form-label">Priority <span class="form-required">*</span></label>
                            <input wire:model="newCommission.priority" type="number" min="1" class="form-input is-mono" placeholder="Lower = applied first" />
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Valid From <span class="form-required">*</span></label>
                        <input wire:model="newCommission.validFrom" type="datetime-local" class="form-input" />
                    </div>
                    <label class="flex items-center gap-2 text-[12px]">
                        <input wire:model="newCommission.activeOnApproval" type="checkbox" /> Activate immediately on approval
                    </label>
                </div>
                @endif

                {{-- ── Limit Profile form ── --}}
                @if($tab === 'limits')
                <div class="flex flex-col gap-3">
                    <div>
                        <label class="form-label">Profile Name <span class="form-required">*</span></label>
                        <input wire:model="newLimit.name" type="text" class="form-input" placeholder="e.g. KYC Verified — Standard" />
                    </div>
                    <div>
                        <label class="form-label">Required KYC Level <span class="form-required">*</span></label>
                        <select wire:model="newLimit.requiredKycLevel" class="form-select">
                            @foreach($kycLevels as $level)
                                <option value="{{ $level }}">{{ $this->enumLabel($level) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Applies to <span class="form-required">*</span></label>
                        <div class="flex gap-3 text-[12px]">
                            @foreach($actorTypes as $a)
                            <label class="flex items-center gap-1.5">
                                <input type="checkbox" wire:model="newLimit.applicableActorTypes" value="{{ $a }}" /> {{ $this->enumLabel($a) }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Min / Tx</label>
                            <input wire:model="newLimit.minTransactionAmount" type="number" class="form-input is-mono" placeholder="e.g. 500" />
                        </div>
                        <div>
                            <label class="form-label">Max / Tx</label>
                            <input wire:model="newLimit.maxTransactionAmount" type="number" class="form-input is-mono" placeholder="e.g. 500000" />
                        </div>
                        <div>
                            <label class="form-label">Max / Day</label>
                            <input wire:model="newLimit.maxDailyAmount" type="number" class="form-input is-mono" placeholder="e.g. 1000000" />
                        </div>
                        <div>
                            <label class="form-label">Max / Week</label>
                            <input wire:model="newLimit.maxWeeklyAmount" type="number" class="form-input is-mono" placeholder="e.g. 5000000" />
                        </div>
                        <div>
                            <label class="form-label">Max / Month</label>
                            <input wire:model="newLimit.maxMonthlyAmount" type="number" class="form-input is-mono" placeholder="e.g. 20000000" />
                        </div>
                        <div>
                            <label class="form-label">Daily Tx Count</label>
                            <input wire:model="newLimit.maxDailyTransactionCount" type="number" class="form-input is-mono" placeholder="e.g. 20" />
                        </div>
                        <div>
                            <label class="form-label">Monthly Tx Count</label>
                            <input wire:model="newLimit.maxMonthlyTransactionCount" type="number" class="form-input is-mono" placeholder="e.g. 200" />
                        </div>
                    </div>
                </div>
                @endif

                {{-- ── Control Threshold form ── --}}
                @if($tab === 'thresholds')
                <div class="flex flex-col gap-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Transaction Type <span class="form-required">*</span></label>
                            <select wire:model="newThreshold.transactionType" class="form-select">
                                @foreach($thresholdTransactionTypes as $type)
                                    <option value="{{ $type }}">{{ $this->enumLabel($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Actor Type <span class="form-required">*</span></label>
                            <select wire:model="newThreshold.actorType" class="form-select">
                                @foreach($actorTypes as $type)
                                    <option value="{{ $type }}">{{ $this->enumLabel($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Scope <span class="form-required">*</span></label>
                            <select wire:model.live="newThreshold.scopeType" class="form-select">
                                @foreach($scopeTypes as $type)
                                    <option value="{{ $type }}">{{ $this->enumLabel($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($newThreshold['scopeType'] === 'ACTOR')
                        <div>
                            <label class="form-label">Scope ID</label>
                            <input wire:model="newThreshold.scopeId" type="text" class="form-input is-mono" placeholder="actor uuid" />
                        </div>
                        @endif
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="form-label">PIN Above</label>
                            <input wire:model="newThreshold.pinRequiredAboveAmount" type="number" class="form-input is-mono" placeholder="e.g. 50000" />
                        </div>
                        <div>
                            <label class="form-label">Confirm Above</label>
                            <input wire:model="newThreshold.confirmationRequiredAboveAmount" type="number" class="form-input is-mono" placeholder="e.g. 100000" />
                        </div>
                        <div>
                            <label class="form-label">Approval Above</label>
                            <input wire:model="newThreshold.approvalRequiredAboveAmount" type="number" class="form-input is-mono" placeholder="e.g. 1000000" />
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Approval Type</label>
                        <select wire:model="newThreshold.approvalType" class="form-select">
                            <option value="">— None —</option>
                            @foreach($approvalTypes as $type)
                                <option value="{{ $type }}">{{ $this->enumLabel($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-md" wire:click="$set('showCreateModal', false)">Cancel</button>
                <button class="btn btn-primary btn-md" wire:click="submitCreate">Submit for Approval</button>
            </div>
        </div>
    </div>
    @endif
</div>
