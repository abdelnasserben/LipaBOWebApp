<?php

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Services\Api\UsesBackofficeApi;

new class extends Component
{
    use UsesBackofficeApi;
    #[Url(as: 'tab')]
    public string $tab = 'commissions';

    public string $modeFilter = '';
    public string $statusFilter = '';
    public ?array $selectedRun = null;

    public bool $showTriggerModal = false;
    public bool $showRequestModal = false;
    public string $requestKind = '';
    public string $notification = '';
    public string $notificationType = 'success';

    public array $trigger = [
        'mode' => 'BATCH_DAILY',
        'businessDay' => '',
    ];

    public array $settlementRequest = [
        'amount' => null,
        'externalReference' => '',
        'notes' => '',
    ];

    public array $withdrawalRequest = [
        'amount' => null,
        'notes' => '',
    ];

    public array $liquidityTopUpRequest = [
        'amount' => null,
        'currency' => 'KMF',
        'externalReference' => '',
        'source' => '',
        'notes' => '',
    ];

    public array $settlementModes = ['BATCH_DAILY', 'BATCH_WEEKLY'];
    public array $runStatuses = ['COMPLETED', 'PARTIAL_FAILURE', 'NO_PAYOUTS', 'FAILED'];

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->selectedRun = null;
        $this->showTriggerModal = false;
        $this->showRequestModal = false;
        $this->modeFilter = '';
        $this->statusFilter = '';
    }

    public function selectRun(string $id): void
    {
        $this->selectedRun = $this->api()->commissionSettlementRun($id);
    }

    public function closeDrawer(): void
    {
        $this->selectedRun = null;
    }

    public function openTriggerModal(): void
    {
        $this->showTriggerModal = true;
    }

    public function triggerSettlement(): void
    {
        $this->validate([
            'trigger.mode' => 'required|in:BATCH_DAILY,BATCH_WEEKLY',
            'trigger.businessDay' => 'nullable|date',
        ]);

        $this->api()->triggerCommissionSettlement($this->trigger);
        $this->notification = 'Commission settlement run triggered.';
        $this->notificationType = 'success';
        $this->showTriggerModal = false;
    }

    public function openBillRequest(): void
    {
        $this->requestKind = 'bill';
        $this->showRequestModal = true;
    }

    public function openPlatformRequest(): void
    {
        $this->requestKind = 'platform';
        $this->showRequestModal = true;
    }

    public function openLiquidityRequest(): void
    {
        if (! $this->hasPermission('PLATFORM_LIQUIDITY_TOP_UP_REQUEST')) {
            $this->notification = 'You do not have permission to request a liquidity top-up.';
            $this->notificationType = 'danger';
            return;
        }

        $this->requestKind = 'liquidity';
        $this->showRequestModal = true;
    }

    public function submitBillSettlement(): void
    {
        $this->validate([
            'settlementRequest.amount' => 'required|numeric|min:1',
            'settlementRequest.externalReference' => 'nullable|string',
            'settlementRequest.notes' => 'nullable|string|max:500',
        ]);

        $this->api()->requestBillProviderSettlement($this->settlementRequest);
        $this->notification = 'Bill provider settlement request submitted for approval.';
        $this->notificationType = 'success';
        $this->showRequestModal = false;
    }

    public function submitPlatformWithdrawal(): void
    {
        $this->validate([
            'withdrawalRequest.amount' => 'required|numeric|min:1',
            'withdrawalRequest.notes' => 'nullable|string|max:500',
        ]);

        $this->api()->requestPlatformRevenueWithdrawal($this->withdrawalRequest);
        $this->notification = 'Platform revenue withdrawal request submitted for approval.';
        $this->notificationType = 'success';
        $this->showRequestModal = false;
    }

    public function submitLiquidityTopUp(): void
    {
        if (! $this->hasPermission('PLATFORM_LIQUIDITY_TOP_UP_REQUEST')) {
            $this->notification = 'You do not have permission to request a liquidity top-up.';
            $this->notificationType = 'danger';
            return;
        }

        $this->validate([
            'liquidityTopUpRequest.amount' => 'required|numeric|min:1',
            'liquidityTopUpRequest.currency' => 'required|in:KMF',
            'liquidityTopUpRequest.externalReference' => 'required|string|max:100',
            'liquidityTopUpRequest.source' => 'required|string|max:60',
            'liquidityTopUpRequest.notes' => 'nullable|string|max:500',
        ]);

        $this->api()->requestPlatformLiquidityTopUp($this->liquidityTopUpRequest);
        $this->notification = 'Liquidity top-up request submitted for approval.';
        $this->notificationType = 'success';
        $this->showRequestModal = false;
    }

    public function enumLabel(?string $value, string $fallback = '-'): string
    {
        return filled($value) ? str_replace('_', ' ', $value) : $fallback;
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = session('bo_user.permissions', []);

        return is_array($permissions) && in_array($permission, $permissions, true);
    }

    public function balanceValue(array $balances, array $keys): int
    {
        foreach ($keys as $key) {
            if (isset($balances[$key]) && is_numeric($balances[$key])) {
                return (int) $balances[$key];
            }
        }

        return 0;
    }

    public function balanceCurrency(array $balances): string
    {
        return (string) ($balances['currency'] ?? 'KMF');
    }

    public function requestModalTitle(): string
    {
        return match ($this->requestKind) {
            'bill' => 'Request Bill Provider Settlement',
            'liquidity' => 'Request Liquidity Top-Up',
            default => 'Request Platform Withdrawal',
        };
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.treasury.treasury-dashboard', [
            'runs' => $this->api()->commissionSettlementRuns([
                'mode' => $this->modeFilter ?: null,
                'status' => $this->statusFilter ?: null,
            ]),
            'pendingSummary' => $this->api()->commissionPendingSummary(),
            'billBalances' => $this->hasPermission('BILL_PROVIDER_SETTLEMENT_VIEW')
                ? $this->api()->billProviderSettlementBalances()
                : ['providerPayableBalance' => 0, 'settlementClearingBalance' => 0, 'currency' => 'KMF'],
            'platformBalances' => $this->hasPermission('PLATFORM_REVENUE_WITHDRAWAL_VIEW')
                ? $this->api()->platformRevenueBalances()
                : ['revenueBalance' => 0, 'withdrawalClearingBalance' => 0, 'currency' => 'KMF'],
            'liquidityBalances' => $this->hasPermission('PLATFORM_LIQUIDITY_TOP_UP_VIEW')
                ? $this->api()->platformLiquidityBalances()
                : ['liquidityBalance' => 0, 'fundingClearingBalance' => 0, 'currency' => 'KMF'],
        ]);
    }
};
?>

<div>
    <x-page-header
        title="Treasury"
        subtitle="Commission settlements, liquidity, and revenue operations"
    />

    @if($notification)
        <div class="alert alert-{{ $notificationType }} mb-4">
            <x-icon name="{{ $notificationType === 'danger' ? 'alert-triangle' : 'check' }}" size="15" /> {{ $notification }}
        </div>
    @endif

    <div class="card">
        <div class="tabs">
            <button class="tab @if($tab==='commissions') active @endif" wire:click="setTab('commissions')">Commission Settlements</button>
            <button class="tab @if($tab==='bill-providers') active @endif" wire:click="setTab('bill-providers')">Bill Provider Settlement</button>
            <button class="tab @if($tab==='platform-revenue') active @endif" wire:click="setTab('platform-revenue')">Platform Revenue</button>
            @if($this->hasPermission('PLATFORM_LIQUIDITY_TOP_UP_VIEW'))
                <button class="tab @if($tab==='platform-liquidity') active @endif" wire:click="setTab('platform-liquidity')">Liquidity Top-Up</button>
            @endif
        </div>

        <div class="filter-bar">
            @if($tab === 'commissions')
                <select wire:model.live="modeFilter" class="filter-select">
                    <option value="">All modes</option>
                    @foreach($settlementModes as $mode)
                        <option value="{{ $mode }}">{{ $this->enumLabel($mode) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="statusFilter" class="filter-select">
                    <option value="">All statuses</option>
                    @foreach($runStatuses as $status)
                        <option value="{{ $status }}">{{ $this->enumLabel($status) }}</option>
                    @endforeach
                </select>
                <div class="flex-1"></div>
                <button class="btn btn-primary btn-sm" wire:click="openTriggerModal">
                    <x-icon name="refresh" size="13" /> Trigger Settlement
                </button>
            @elseif($tab === 'bill-providers')
                <div class="text-xs text-[var(--text-secondary)]">Balances in {{ $billBalances['currency'] }}</div>
                <div class="flex-1"></div>
                @if($this->hasPermission('BILL_PROVIDER_SETTLEMENT_REQUEST'))
                    <button class="btn btn-primary btn-sm" wire:click="openBillRequest">
                        <x-icon name="plus" size="13" /> Request Settlement
                    </button>
                @endif
            @elseif($tab === 'platform-revenue')
                <div class="text-xs text-[var(--text-secondary)]">Balances in {{ $platformBalances['currency'] }}</div>
                <div class="flex-1"></div>
                @if($this->hasPermission('PLATFORM_REVENUE_WITHDRAWAL_REQUEST'))
                    <button class="btn btn-primary btn-sm" wire:click="openPlatformRequest">
                        <x-icon name="plus" size="13" /> Request Withdrawal
                    </button>
                @endif
            @else
                <div class="text-xs text-[var(--text-secondary)]">Balances in {{ $this->balanceCurrency($liquidityBalances) }}</div>
                <div class="flex-1"></div>
                @if($this->hasPermission('PLATFORM_LIQUIDITY_TOP_UP_REQUEST'))
                    <button class="btn btn-primary btn-sm" wire:click="openLiquidityRequest">
                        <x-icon name="plus" size="13" /> Request Top-Up
                    </button>
                @endif
            @endif
        </div>

        @if($tab === 'commissions')
            <div class="grid grid-cols-1 gap-3 border-b border-[var(--border-color)] p-5 md:grid-cols-4">
                <div>
                    <div class="kpi-label">Daily Pending</div>
                    <div class="kpi-value">{{ number_format($pendingSummary['pendingDailyCount']) }}</div>
                </div>
                <div>
                    <div class="kpi-label">Daily Amount</div>
                    <x-amount :value="$pendingSummary['pendingDailyAmount']" size="20" />
                </div>
                <div>
                    <div class="kpi-label">Weekly Pending</div>
                    <div class="kpi-value">{{ number_format($pendingSummary['pendingWeeklyCount']) }}</div>
                </div>
                <div>
                    <div class="kpi-label">Weekly Amount</div>
                    <x-amount :value="$pendingSummary['pendingWeeklyAmount']" size="20" />
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Run</th>
                            <th>Mode</th>
                            <th>Business Day</th>
                            <th>Agents</th>
                            <th>Payouts</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($runs as $run)
                            <tr class="table-row-link" wire:click="selectRun('{{ $run['id'] }}')">
                                <td><x-mono>{{ strtoupper($run['id']) }}</x-mono></td>
                                <td><span class="text-xs font-medium">{{ $this->enumLabel($run['mode']) }}</span></td>
                                <td><x-mono>{{ \Carbon\Carbon::parse($run['businessDay'])->format('d M Y') }}</x-mono></td>
                                <td><span class="text-xs">{{ $run['agentsSettled'] }} / {{ $run['agentsTotal'] }}</span></td>
                                <td><x-mono>{{ number_format($run['payoutsSettled']) }}</x-mono></td>
                                <td><x-amount :value="$run['amountSettled']" size="12" /></td>
                                <td><x-badge :status="$run['status']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No settlement runs found</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif($tab === 'bill-providers')
            <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
                <div class="rounded-md border border-[var(--border-color)] p-4">
                    <div class="kpi-label">Provider Payable</div>
                    <x-amount :value="$billBalances['providerPayableBalance']" size="24" />
                </div>
                <div class="rounded-md border border-[var(--border-color)] p-4">
                    <div class="kpi-label">Settlement Clearing</div>
                    <x-amount :value="$billBalances['settlementClearingBalance']" size="24" />
                </div>
            </div>
        @elseif($tab === 'platform-revenue')
            <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
                <div class="rounded-md border border-[var(--border-color)] p-4">
                    <div class="kpi-label">Revenue Balance</div>
                    <x-amount :value="$platformBalances['revenueBalance']" size="24" />
                </div>
                <div class="rounded-md border border-[var(--border-color)] p-4">
                    <div class="kpi-label">Withdrawal Clearing</div>
                    <x-amount :value="$platformBalances['withdrawalClearingBalance']" size="24" />
                </div>
            </div>
        @else
            @if($this->hasPermission('PLATFORM_LIQUIDITY_TOP_UP_VIEW'))
                <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
                    <div class="rounded-md border border-[var(--border-color)] p-4">
                        <div class="kpi-label">SYSTEM_LIQUIDITY</div>
                        <x-amount :value="$this->balanceValue($liquidityBalances, ['liquidityBalance', 'systemLiquidityBalance', 'systemLiquidityBalanceKmf'])" size="24" />
                    </div>
                    <div class="rounded-md border border-[var(--border-color)] p-4">
                        <div class="kpi-label">SYSTEM_LIQUIDITY_FUNDING_CLEARING</div>
                        <x-amount :value="$this->balanceValue($liquidityBalances, ['fundingClearingBalance', 'fundingClearingMagnitude', 'fundingClearingBalanceKmf'])" size="24" />
                    </div>
                </div>
            @else
                <div class="p-5">
                    <div class="alert alert-warning">
                        <x-icon name="lock" size="15" /> You do not have permission to view liquidity balances.
                    </div>
                </div>
            @endif
        @endif
    </div>

    @if($selectedRun)
        <div class="drawer-overlay" wire:click="closeDrawer"></div>
        <div class="drawer">
            <div class="drawer-header">
                <span class="drawer-title">Settlement Run {{ strtoupper($selectedRun['id']) }}</span>
                <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
            </div>
            <div class="drawer-body">
                <div class="mb-4 flex gap-2">
                    <x-badge :status="$selectedRun['status']" />
                    <span class="text-xs font-semibold text-[var(--text-secondary)]">{{ $this->enumLabel($selectedRun['mode']) }}</span>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Run</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selectedRun['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Business Day</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedRun['businessDay'])->format('d M Y') }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Triggered By</span><span class="drawer-field-value">{{ $this->enumLabel($selectedRun['triggeredByType']) }} / {{ $selectedRun['triggeredById'] ?? 'system' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Started</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedRun['startedAt'])->format('d M Y, H:i:s') }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Completed</span><span class="drawer-field-value">{{ $selectedRun['completedAt'] ? \Carbon\Carbon::parse($selectedRun['completedAt'])->format('d M Y, H:i:s') : '-' }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Settlement</div>
                    <div class="drawer-field"><span class="drawer-field-label">Agents Total</span><span class="drawer-field-value">{{ number_format($selectedRun['agentsTotal']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Agents Settled</span><span class="drawer-field-value">{{ number_format($selectedRun['agentsSettled']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Agents Failed</span><span class="drawer-field-value">{{ number_format($selectedRun['agentsFailed']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Payouts Settled</span><span class="drawer-field-value">{{ number_format($selectedRun['payoutsSettled']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Amount Settled</span><span class="drawer-field-value"><x-amount :value="$selectedRun['amountSettled']" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Error</span><span class="drawer-field-value">{{ $selectedRun['errorSummary'] ?? '-' }}</span></div>
                </div>
            </div>
        </div>
    @endif

    @if($showTriggerModal)
        <div class="modal-overlay" wire:click.self="$set('showTriggerModal', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">Trigger Commission Settlement</span>
                    <button class="modal-close" wire:click="$set('showTriggerModal', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="form-label">Mode <span class="form-required">*</span></label>
                            <select wire:model="trigger.mode" class="form-select">
                                @foreach($settlementModes as $mode)
                                    <option value="{{ $mode }}">{{ $this->enumLabel($mode) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Business Day</label>
                            <input wire:model="trigger.businessDay" type="date" class="form-input" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('showTriggerModal', false)">Cancel</button>
                    <button class="btn btn-primary btn-md" wire:click="triggerSettlement">Trigger</button>
                </div>
            </div>
        </div>
    @endif

    @if($showRequestModal)
        <div class="modal-overlay" wire:click.self="$set('showRequestModal', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">{{ $this->requestModalTitle() }}</span>
                    <button class="modal-close" wire:click="$set('showRequestModal', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    @if($requestKind === 'bill')
                        <div class="flex flex-col gap-3">
                            <div>
                                <label class="form-label">Amount (KMF) <span class="form-required">*</span></label>
                                <input wire:model="settlementRequest.amount" type="number" min="1" class="form-input is-mono" placeholder="e.g. 250000" />
                                @error('settlementRequest.amount') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="form-label">External Reference</label>
                                <input wire:model="settlementRequest.externalReference" type="text" class="form-input is-mono" placeholder="e.g. WIRE-2026-05-001" />
                                @error('settlementRequest.externalReference') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="form-label">Notes</label>
                                <textarea wire:model="settlementRequest.notes" rows="3" class="form-textarea"></textarea>
                                @error('settlementRequest.notes') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    @elseif($requestKind === 'liquidity')
                        <div class="flex flex-col gap-3">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="form-label">Amount <span class="form-required">*</span></label>
                                    <input wire:model="liquidityTopUpRequest.amount" type="number" min="1" class="form-input is-mono" placeholder="e.g. 3500000" />
                                    @error('liquidityTopUpRequest.amount') <div class="form-error">{{ $message }}</div> @enderror
                                </div>
                                <div>
                                    <label class="form-label">Currency <span class="form-required">*</span></label>
                                    <select wire:model="liquidityTopUpRequest.currency" class="form-select is-mono">
                                        <option value="KMF">KMF</option>
                                    </select>
                                    @error('liquidityTopUpRequest.currency') <div class="form-error">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Funding Reference <span class="form-required">*</span></label>
                                <input wire:model="liquidityTopUpRequest.externalReference" type="text" maxlength="100" class="form-input is-mono" placeholder="e.g. WIRE-2026-05-009" />
                                @error('liquidityTopUpRequest.externalReference') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="form-label">Funding Source <span class="form-required">*</span></label>
                                <input wire:model="liquidityTopUpRequest.source" type="text" maxlength="60" class="form-input is-mono" placeholder="e.g. BANK_WIRE" />
                                @error('liquidityTopUpRequest.source') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="form-label">Notes</label>
                                <textarea wire:model="liquidityTopUpRequest.notes" rows="3" maxlength="500" class="form-textarea"></textarea>
                                @error('liquidityTopUpRequest.notes') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    @else
                        <div class="flex flex-col gap-3">
                            <div>
                                <label class="form-label">Amount (KMF) <span class="form-required">*</span></label>
                                <input wire:model="withdrawalRequest.amount" type="number" min="1" class="form-input is-mono" placeholder="e.g. 1000000" />
                                @error('withdrawalRequest.amount') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="form-label">Notes</label>
                                <textarea wire:model="withdrawalRequest.notes" rows="3" class="form-textarea"></textarea>
                                @error('withdrawalRequest.notes') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('showRequestModal', false)">Cancel</button>
                    @if($requestKind === 'bill')
                        <button class="btn btn-primary btn-md" wire:click="submitBillSettlement">Submit for Approval</button>
                    @elseif($requestKind === 'liquidity')
                        <button class="btn btn-primary btn-md" wire:click="submitLiquidityTopUp">Submit for Approval</button>
                    @else
                        <button class="btn btn-primary btn-md" wire:click="submitPlatformWithdrawal">Submit for Approval</button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
