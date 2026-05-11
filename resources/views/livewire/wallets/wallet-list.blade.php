<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Enums\Backoffice\ActorType;
use App\Enums\Backoffice\WalletStatus;
use App\Livewire\Concerns\UsesBackofficeEnums;
use App\Services\Api\UsesBackofficeApi;
use App\Support\BackofficeEnums;

new class extends Component {
    use WithPagination;
    use UsesBackofficeApi;
    use UsesBackofficeEnums;

    public string $search = '';
    public string $ownerTypeFilter = '';
    public string $statusFilter = '';

    public ?array $selected = null;

    public bool $showFreezeConfirm = false;
    public bool $showUnfreezeConfirm = false;
    public string $notification = '';
    public string $notificationType = 'success';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
    public function updatingOwnerTypeFilter(): void
    {
        $this->resetPage();
    }
    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function selectRow(string $id): void
    {
        $this->selected = $this->api()->walletById($id);
        $this->showFreezeConfirm = false;
        $this->showUnfreezeConfirm = false;
    }

    public function closeDrawer(): void
    {
        $this->selected = null;
        $this->showFreezeConfirm = false;
        $this->showUnfreezeConfirm = false;
    }

    public function confirmFreeze(): void
    {
        $this->showFreezeConfirm = true;
    }
    public function confirmUnfreeze(): void
    {
        $this->showUnfreezeConfirm = true;
    }

    public function freezeWallet(): void
    {
        $this->api()->freezeWallet($this->selected['id']);
        $this->notify('Wallet frozen successfully.', 'success');
        $this->closeDrawer();
    }

    public function unfreezeWallet(): void
    {
        $this->api()->unfreezeWallet($this->selected['id']);
        $this->notify('Wallet unfrozen successfully.', 'success');
        $this->closeDrawer();
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
        $all = $this->api()->wallets(
            $baseFilters + [
                'ownerType' => $this->ownerTypeFilter ?: null,
                'status' => $this->statusFilter ?: null,
            ],
        );
        $ownerTypeRows = $this->api()->wallets(
            $baseFilters + [
                'status' => $this->statusFilter ?: null,
            ],
        );
        $statusRows = $this->api()->wallets(
            $baseFilters + [
                'ownerType' => $this->ownerTypeFilter ?: null,
            ],
        );

        $perPage = 10;
        $page = $this->getPage();
        $total = count($all);
        $rows = array_slice($all, ($page - 1) * $perPage, $perPage);

        return view('livewire.wallets.wallet-list', [
            'rows' => $rows,
            'total' => $total,
            'perPage' => $perPage,
            'page' => $page,
            'totalAvailable' => array_sum(array_map(fn($r) => $r['availableBalance'], $all)),
            'totalFrozen' => array_sum(array_map(fn($r) => $r['frozenBalance'], $all)),
            'frozenCount' => count(array_filter($all, fn($r) => $r['status'] === 'FROZEN')),
            'ownerTypeOptions' => BackofficeEnums::optionsFromRows($ownerTypeRows, 'ownerType', ActorType::class, $this->ownerTypeFilter),
            'statusOptions' => BackofficeEnums::optionsFromRows($statusRows, 'status', WalletStatus::class, $this->statusFilter),
        ]);
    }
};
?>

<div>
    <x-page-header title="Wallets" subtitle="Customer, agent and merchant wallet balances" />

    @if ($notification)
        <div class="alert alert-{{ $notificationType }} mb-4">
            <x-icon name="{{ $notificationType === 'success' ? 'check' : 'alert-triangle' }}" size="15" />
            {{ $notification }}
        </div>
    @endif

    <div class="card">
        <div class="filter-bar">
            <div class="filter-search">
                <span class="filter-search-icon"><x-icon name="search" size="14" /></span>
                <input wire:model.live.debounce.300ms="search" type="text"
                    placeholder="Search wallet ID, owner, reference…" />
            </div>
            <select wire:model.live="ownerTypeFilter" class="filter-select">
                <option value="">All owner types</option>
                @foreach ($ownerTypeOptions as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter" class="filter-select">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 gap-3 border-b border-[var(--border-color)] p-5 md:grid-cols-3">
            <div>
                <div class="kpi-label">Available Balance</div>
                <x-amount :value="$totalAvailable" size="20" />
            </div>
            <div>
                <div class="kpi-label">Frozen Balance</div>
                <x-amount :value="$totalFrozen" size="20" />
            </div>
            <div>
                <div class="kpi-label">Frozen Wallets</div>
                <div class="kpi-value">{{ number_format($frozenCount) }}</div>
            </div>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Wallet</th>
                        <th>Owner</th>
                        <th>Type</th>
                        <th>Available</th>
                        <th>Frozen</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="table-row-link" wire:click="selectRow('{{ $row['id'] }}')">
                            <td>
                                <x-mono>{{ strtoupper($row['id']) }}</x-mono>
                                <div class="text-[11px] text-[var(--text-secondary)]">{{ $row['currency'] }} •
                                    v{{ $row['version'] }}</div>
                            </td>
                            <td>
                                <div class="font-medium">{{ $row['ownerLabel'] }}</div>
                                <x-mono>{{ $row['ownerRef'] }}</x-mono>
                            </td>
                            <td><span class="text-xs font-medium">{{ $this->enumLabel($row['ownerType']) }}</span></td>
                            <td><x-amount :value="$row['availableBalance']" size="12" /></td>
                            <td><x-amount :value="$row['frozenBalance']" size="12" /></td>
                            <td><x-badge :status="$row['status']" /></td>
                            <td><x-mono>{{ \Carbon\Carbon::parse($row['updatedAt'])->format('d M, H:i') }}</x-mono>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-state-icon">💼</div>
                                    <div class="empty-state-title">No wallets found</div>
                                    <div class="empty-state-text">Try adjusting your filters.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <span class="pagination-info">{{ $total }} total • showing {{ count($rows) }}</span>
            <div class="pagination-controls">
                <button class="pagination-btn" wire:click="previousPage" @disabled($page <= 1)>‹</button>
                <button class="pagination-btn active">{{ $page }}</button>
                <button class="pagination-btn" wire:click="nextPage" @disabled($page * $perPage >= $total)>›</button>
            </div>
        </div>
    </div>

    @if ($selected)
        <div class="drawer-overlay" wire:click="closeDrawer"></div>
        <div class="drawer">
            <div class="drawer-header">
                <div>
                    <span class="drawer-title">Wallet</span><br>
                    <span class="drawer-field-value">{{ $selected['id'] }}</span>
                </div>
                <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
            </div>
            <div class="drawer-body">
                <div class="mb-4 flex gap-2">
                    <x-badge :status="$selected['status']" />
                    <span
                        class="text-xs font-semibold text-[var(--text-secondary)]">{{ $this->enumLabel($selected['ownerType']) }}</span>
                </div>

                <div class="drawer-section">
                    <div class="drawer-section-title">Balances</div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Available</span>
                        <span class="drawer-field-value"><x-amount :value="$selected['availableBalance']" size="13" /></span>
                    </div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Frozen</span>
                        <span class="drawer-field-value"><x-amount :value="$selected['frozenBalance']" size="13" /></span>
                    </div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Total</span>
                        <span class="drawer-field-value"><x-amount :value="$selected['availableBalance'] + $selected['frozenBalance']" size="13" /></span>
                    </div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Currency</span>
                        <span class="drawer-field-value">{{ $selected['currency'] }}</span>
                    </div>
                </div>

                <div class="drawer-section">
                    <div class="drawer-section-title">Owner</div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Owner Type</span>
                        <span class="drawer-field-value">{{ $this->enumLabel($selected['ownerType']) }}</span>
                    </div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Owner ID</span>
                        <span class="drawer-field-value">{{ $selected['ownerId'] }}</span>
                    </div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Owner Name</span>
                        <span class="drawer-field-value !font-sans">{{ $selected['ownerLabel'] ?? '—' }}</span>
                    </div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">External Ref</span>
                        <span class="drawer-field-value">{{ $selected['ownerRef'] ?? '—' }}</span>
                    </div>
                </div>

                <div class="drawer-section">
                    <div class="drawer-section-title">Wallet</div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Wallet ID</span>
                        <span class="drawer-field-value">{{ $selected['id'] }}</span>
                    </div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Version</span>
                        <span class="drawer-field-value">{{ $selected['version'] }}</span>
                    </div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Created</span>
                        <span
                            class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['createdAt'])->format('d M Y, H:i') }}</span>
                    </div>
                    <div class="drawer-field">
                        <span class="drawer-field-label">Updated</span>
                        <span
                            class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['updatedAt'])->format('d M Y, H:i') }}</span>
                    </div>
                </div>

                @if ($showFreezeConfirm)
                    <div class="alert alert-warning">
                        <div>
                            <strong>Confirm freeze?</strong>
                            <br />Outgoing transactions will be blocked. Incoming credits remain allowed.
                            <div class="mt-2.5 flex gap-2">
                                <button class="btn btn-danger btn-sm" wire:click="freezeWallet">Yes, freeze</button>
                                <button class="btn btn-secondary btn-sm"
                                    wire:click="$set('showFreezeConfirm', false)">Cancel</button>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($showUnfreezeConfirm)
                    <div class="alert alert-info">
                        <div>
                            <strong>Confirm unfreeze?</strong>
                            <br />The wallet will resume normal operations.
                            <div class="mt-2.5 flex gap-2">
                                <button class="btn btn-primary btn-sm" wire:click="unfreezeWallet">Yes,
                                    unfreeze</button>
                                <button class="btn btn-secondary btn-sm"
                                    wire:click="$set('showUnfreezeConfirm', false)">Cancel</button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            @if (!$showFreezeConfirm && !$showUnfreezeConfirm)
                <div class="drawer-footer">
                    @if ($selected['status'] === 'FROZEN')
                        <button class="btn btn-primary btn-sm" wire:click="confirmUnfreeze">Unfreeze</button>
                    @elseif(in_array($selected['status'], ['ACTIVE', 'SUSPENDED']))
                        <button class="btn btn-warning btn-sm" wire:click="confirmFreeze">Freeze</button>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
