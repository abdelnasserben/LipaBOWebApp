<?php

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Enums\Backoffice\CardStatus;
use App\Enums\Backoffice\CardStockStatus;
use App\Enums\Backoffice\CardType;
use App\Livewire\Concerns\UsesBackofficeEnums;
use App\Services\Api\UsesBackofficeApi;
use App\Support\BackofficeEnums;

new class extends Component
{
    use UsesBackofficeApi;
    use UsesBackofficeEnums;
    #[Url(as: 'tab')]
    public string $tab = 'cards';

    public string $customerIdFilter = '';
    public string $cardStatusFilter = '';
    public string $cardTypeFilter = '';
    public string $stockStatusFilter = '';
    public string $stockAgentFilter = '';
    public string $stockBatchFilter = '';

    public ?array $selectedCard = null;
    public ?array $selectedStock = null;
    public bool $showCloseModal = false;
    public bool $showImportModal = false;
    public bool $showAssignModal = false;
    public string $closeReason = '';
    public string $notification = '';
    public string $notificationType = 'success';

    public array $importBatch = [
        'batchRef' => '',
        'producedAt' => '',
    ];

    public array $importCards = [
        ['nfcUid' => '', 'internalCardNumber' => '', 'authKeyEncryptedBase64' => '', 'authKeyVersion' => 1],
    ];

    public array $assignStock = [
        'agentId' => '',
        'cardStockIds' => [],
    ];

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->closeDrawer();
        $this->showImportModal = false;
        $this->showAssignModal = false;
    }

    public function selectCard(string $id): void
    {
        $this->selectedCard = $this->api()->card($id);
        $this->selectedStock = null;
    }

    public function selectStock(string $id): void
    {
        $this->selectedStock = $this->api()->cardStockItem($id);
        $this->selectedCard = null;
    }

    public function closeDrawer(): void
    {
        $this->selectedCard = null;
        $this->selectedStock = null;
        $this->showCloseModal = false;
        $this->closeReason = '';
    }

    public function blockCard(): void
    {
        $this->api()->blockCard($this->selectedCard['id']);
        $this->notify('Card block request applied.');
        $this->closeDrawer();
    }

    public function unblockCard(): void
    {
        $this->api()->unblockCard($this->selectedCard['id']);
        $this->notify('Card unblocked.');
        $this->closeDrawer();
    }

    public function reportLost(): void
    {
        $this->api()->reportCardLost($this->selectedCard['id']);
        $this->notify('Card reported lost.');
        $this->closeDrawer();
    }

    public function reportStolen(): void
    {
        $this->api()->reportCardStolen($this->selectedCard['id']);
        $this->notify('Card reported stolen.');
        $this->closeDrawer();
    }

    public function closeCard(): void
    {
        $this->validate(['closeReason' => 'nullable|string|max:500']);
        $this->api()->closeCard($this->selectedCard['id'], $this->closeReason);
        $this->notify('Card closed.');
        $this->closeDrawer();
    }

    public function addImportCardRow(): void
    {
        $this->importCards[] = ['nfcUid' => '', 'internalCardNumber' => '', 'authKeyEncryptedBase64' => '', 'authKeyVersion' => 1];
    }

    public function removeImportCardRow(int $index): void
    {
        if (count($this->importCards) === 1) {
            return;
        }

        unset($this->importCards[$index]);
        $this->importCards = array_values($this->importCards);
    }

    public function submitImportBatch(): void
    {
        $this->validate([
            'importBatch.batchRef' => 'required|string',
            'importBatch.producedAt' => 'nullable|date',
            'importCards' => 'required|array|min:1',
            'importCards.*.nfcUid' => ['required', 'string', 'size:14', 'regex:/^[0-9A-Fa-f]{14}$/'],
            'importCards.*.internalCardNumber' => 'required|string',
            'importCards.*.authKeyEncryptedBase64' => 'nullable|string',
            'importCards.*.authKeyVersion' => 'required|integer|min:0',
        ]);

        $this->api()->importCardStock([
            'batchRef' => $this->importBatch['batchRef'],
            'producedAt' => $this->importBatch['producedAt'] ?: null,
            'cards' => $this->importCards,
        ]);
        $this->notify('Card stock batch imported.');
        $this->resetImportModal();
        $this->showImportModal = false;
    }

    public function openAssignModal(): void
    {
        $this->resetAssignModal();
        $this->showAssignModal = true;
    }

    public function assignCardStock(): void
    {
        $this->validate([
            'assignStock.agentId' => 'required|string',
            'assignStock.cardStockIds' => 'required|array|min:1',
        ]);

        $this->api()->assignCardStock($this->assignStock);
        $this->notify('Card stock assigned to agent.');
        $this->resetAssignModal();
        $this->showAssignModal = false;
    }

    private function resetImportModal(): void
    {
        $this->importBatch = [
            'batchRef' => '',
            'producedAt' => '',
        ];
        $this->importCards = [
            ['nfcUid' => '', 'internalCardNumber' => '', 'authKeyEncryptedBase64' => '', 'authKeyVersion' => 1],
        ];
    }

    private function resetAssignModal(): void
    {
        $this->assignStock = [
            'agentId' => '',
            'cardStockIds' => [],
        ];
    }

    private function notify(string $message, string $type = 'success'): void
    {
        $this->notification = $message;
        $this->notificationType = $type;
    }

    private function optionalTextFilter(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function optionalUuidFilter(string $value): ?string
    {
        $value = $this->optionalTextFilter($value);

        if ($value === null) {
            return null;
        }

        return config('komopay.use_mock_api') || $this->isUuid($value) ? $value : null;
    }

    private function hasInvalidUuidFilter(string $value): bool
    {
        $value = trim($value);

        return ! config('komopay.use_mock_api') && $value !== '' && ! $this->isUuid($value);
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value) === 1;
    }

    public function render(): \Illuminate\View\View
    {
        $api = $this->api();
        $customerIdFilter = $this->optionalUuidFilter($this->customerIdFilter);
        $stockAgentFilter = $this->optionalUuidFilter($this->stockAgentFilter);
        $stockBatchFilter = $this->optionalTextFilter($this->stockBatchFilter);

        $cardRows = $api->cards([
            'customerId' => $customerIdFilter,
            'status' => $this->cardStatusFilter ?: null,
            'cardType' => $this->cardTypeFilter ?: null,
        ]);
        $cardStatusRows = $api->cards([
            'customerId' => $customerIdFilter,
            'cardType' => $this->cardTypeFilter ?: null,
        ]);
        $cardTypeRows = $api->cards([
            'customerId' => $customerIdFilter,
            'status' => $this->cardStatusFilter ?: null,
        ]);
        $stockRows = $api->cardStock([
            'status' => $this->stockStatusFilter ?: null,
            'agentId' => $stockAgentFilter,
            'batchRef' => $stockBatchFilter,
        ]);
        $stockStatusRows = $api->cardStock([
            'agentId' => $stockAgentFilter,
            'batchRef' => $stockBatchFilter,
        ]);

        return view('livewire.cards.card-management', [
            'cards' => $cardRows,
            'stockRows' => $stockRows,
            'availableStock' => $api->cardStock(['status' => CardStockStatus::IN_WAREHOUSE->value]),
            'cardStatusOptions' => BackofficeEnums::optionsFromRows($cardStatusRows, 'status', CardStatus::class, $this->cardStatusFilter),
            'cardTypeOptions' => BackofficeEnums::optionsFromRows($cardTypeRows, 'cardType', CardType::class, $this->cardTypeFilter),
            'stockStatusOptions' => BackofficeEnums::optionsFromRows($stockStatusRows, 'status', CardStockStatus::class, $this->stockStatusFilter),
            'customerIdFilterInvalid' => $this->hasInvalidUuidFilter($this->customerIdFilter),
            'stockAgentFilterInvalid' => $this->hasInvalidUuidFilter($this->stockAgentFilter),
        ]);
    }
};
?>

<div>
    <x-page-header
        title="Cards"
        subtitle="Card lifecycle and physical stock management"
    />

    @if($notification)
        <div class="alert alert-{{ $notificationType }} mb-4">
            <x-icon name="{{ $notificationType === 'danger' ? 'alert-triangle' : 'check' }}" size="15" />
            {{ $notification }}
        </div>
    @endif

    <div class="card">
        <div class="tabs">
            <button class="tab @if($tab==='cards') active @endif" wire:click="setTab('cards')">Cards</button>
            <button class="tab @if($tab==='stock') active @endif" wire:click="setTab('stock')">Card Stock</button>
        </div>

        <div class="filter-bar">
            @if($tab === 'cards')
                <div>
                    <input wire:model.live.debounce.300ms="customerIdFilter" type="text" class="filter-select !cursor-text" placeholder="Customer ID" />
                    @if($customerIdFilterInvalid)
                        <div class="form-error mt-1">Enter a full UUID to filter.</div>
                    @endif
                </div>
                <select wire:model.live="cardStatusFilter" class="filter-select">
                    <option value="">All statuses</option>
                    @foreach($cardStatusOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <select wire:model.live="cardTypeFilter" class="filter-select">
                    <option value="">All card types</option>
                    @foreach($cardTypeOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            @else
                <input wire:model.live.debounce.300ms="stockBatchFilter" type="text" class="filter-select !cursor-text" placeholder="Batch ref" />
                <div>
                    <input wire:model.live.debounce.300ms="stockAgentFilter" type="text" class="filter-select !cursor-text" placeholder="Agent ID" />
                    @if($stockAgentFilterInvalid)
                        <div class="form-error mt-1">Enter a full UUID to filter.</div>
                    @endif
                </div>
                <select wire:model.live="stockStatusFilter" class="filter-select">
                    <option value="">All statuses</option>
                    @foreach($stockStatusOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <div class="flex-1"></div>
                <button class="btn btn-secondary btn-sm" wire:click="$set('showImportModal', true)">
                    <x-icon name="upload" size="13" /> Import Batch
                </button>
                <button class="btn btn-primary btn-sm" wire:click="openAssignModal">
                    <x-icon name="plus" size="13" /> Assign Stock
                </button>
            @endif
        </div>

        @if($tab === 'cards')
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Card</th>
                            <th>Customer</th>
                            <th>Wallet</th>
                            <th>Type</th>
                            <th>PIN</th>
                            <th>Last Used</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cards as $card)
                            <tr class="table-row-link" wire:click="selectCard('{{ $card['id'] }}')">
                                <td>
                                    <div class="font-medium">{{ $card['internalCardNumber'] }}</div>
                                    <x-mono>{{ $card['nfcUid'] ?? '-' }}</x-mono>
                                </td>
                                <td><x-mono>{{ $card['customerId'] }}</x-mono></td>
                                <td><x-mono>{{ $card['walletId'] }}</x-mono></td>
                                <td><span class="text-xs font-medium">{{ $this->enumLabel($card['cardType']) }}</span></td>
                                <td><x-badge :status="$card['pinEnabled'] ? 'ACTIVE' : 'INACTIVE'" :label="$card['pinEnabled'] ? 'Enabled' : 'Disabled'" /></td>
                                <td><x-mono>{{ $card['lastUsedAt'] ? \Carbon\Carbon::parse($card['lastUsedAt'])->format('d M, H:i') : '-' }}</x-mono></td>
                                <td><x-badge :status="$card['status']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No cards found</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination"><span class="pagination-info">{{ count($cards) }} cards</span></div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Stock</th>
                            <th>Batch</th>
                            <th>Agent</th>
                            <th>Sold To</th>
                            <th>Key Version</th>
                            <th>Imported</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stockRows as $stock)
                            <tr class="table-row-link" wire:click="selectStock('{{ $stock['id'] }}')">
                                <td>
                                    <div class="font-medium">{{ $stock['internalCardNumber'] }}</div>
                                    <x-mono>{{ $stock['nfcUid'] }}</x-mono>
                                </td>
                                <td><x-mono>{{ $stock['batchRef'] }}</x-mono></td>
                                <td><x-mono>{{ $stock['assignedAgentId'] ?? '-' }}</x-mono></td>
                                <td><x-mono>{{ $stock['soldToCustomerId'] ?? '-' }}</x-mono></td>
                                <td><x-mono>v{{ $stock['authKeyVersion'] }}</x-mono></td>
                                <td><x-mono>{{ \Carbon\Carbon::parse($stock['importedAt'])->format('d M Y') }}</x-mono></td>
                                <td><x-badge :status="$stock['status']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No stock found</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination"><span class="pagination-info">{{ count($stockRows) }} stock rows</span></div>
        @endif
    </div>

    @if($selectedCard)
        <div class="drawer-overlay" wire:click="closeDrawer"></div>
        <div class="drawer">
            <div class="drawer-header">
                <span class="drawer-title">{{ $selectedCard['internalCardNumber'] }}</span>
                <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
            </div>
            <div class="drawer-body">
                <div class="mb-4 flex gap-2">
                    <x-badge :status="$selectedCard['status']" />
                    <span class="text-xs font-semibold text-[var(--text-secondary)]">{{ $this->enumLabel($selectedCard['cardType']) }}</span>
                </div>
                @if($notification && $notificationType === 'danger')
                    <div class="alert alert-danger mb-4"><x-icon name="alert-triangle" size="15" /> {{ $notification }}</div>
                @endif
                <div class="drawer-section">
                    <div class="drawer-section-title">Card</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selectedCard['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">NFC UID</span><span class="drawer-field-value">{{ $selectedCard['nfcUid'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Customer</span><span class="drawer-field-value">{{ $selectedCard['customerId'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Wallet</span><span class="drawer-field-value">{{ $selectedCard['walletId'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">PIN</span><span class="drawer-field-value">{{ $selectedCard['pinEnabled'] ? 'Enabled' : 'Disabled' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Issued By</span><span class="drawer-field-value">{{ $selectedCard['issuedByAgentId'] ?? '-' }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Lifecycle</div>
                    <div class="drawer-field"><span class="drawer-field-label">Issued</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedCard['issuedAt'])->format('d M Y, H:i') }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Activated</span><span class="drawer-field-value">{{ $selectedCard['activatedAt'] ? \Carbon\Carbon::parse($selectedCard['activatedAt'])->format('d M Y, H:i') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Expires</span><span class="drawer-field-value">{{ $selectedCard['expiresAt'] ? \Carbon\Carbon::parse($selectedCard['expiresAt'])->format('d M Y') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Last Used</span><span class="drawer-field-value">{{ $selectedCard['lastUsedAt'] ? \Carbon\Carbon::parse($selectedCard['lastUsedAt'])->format('d M Y, H:i') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Last Terminal</span><span class="drawer-field-value">{{ $selectedCard['lastUsedTerminalId'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Replacement Of</span><span class="drawer-field-value">{{ $selectedCard['replacementOfCardId'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Replaced By</span><span class="drawer-field-value">{{ $selectedCard['replacedByCardId'] ?? '-' }}</span></div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($selectedCard['status'] === 'BLOCKED')
                    <button class="btn btn-primary btn-sm" wire:click="unblockCard">Unblock</button>
                @elseif(!in_array($selectedCard['status'], ['CLOSED', 'LOST', 'STOLEN']))
                    <button class="btn btn-warning btn-sm" wire:click="blockCard">Block</button>
                @endif
                @if(!in_array($selectedCard['status'], ['CLOSED', 'LOST', 'STOLEN']))
                    <button class="btn btn-secondary btn-sm" wire:click="reportLost">Report Lost</button>
                    <button class="btn btn-secondary btn-sm" wire:click="reportStolen">Report Stolen</button>
                @endif
                @if($selectedCard['status'] !== 'CLOSED')
                    <button class="btn btn-danger btn-sm" wire:click="$set('showCloseModal', true)">Close</button>
                @endif
            </div>
        </div>
    @endif

    @if($selectedStock)
        <div class="drawer-overlay" wire:click="closeDrawer"></div>
        <div class="drawer">
            <div class="drawer-header">
                <span class="drawer-title">{{ $selectedStock['internalCardNumber'] }}</span>
                <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
            </div>
            <div class="drawer-body">
                <div class="mb-4"><x-badge :status="$selectedStock['status']" /></div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Stock</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selectedStock['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">NFC UID</span><span class="drawer-field-value">{{ $selectedStock['nfcUid'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Batch</span><span class="drawer-field-value">{{ $selectedStock['batchRef'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Produced</span><span class="drawer-field-value">{{ $selectedStock['producedAt'] ? \Carbon\Carbon::parse($selectedStock['producedAt'])->format('d M Y') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Imported</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedStock['importedAt'])->format('d M Y, H:i') }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Imported By</span><span class="drawer-field-value">{{ $selectedStock['importedByUserId'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Auth Key</span><span class="drawer-field-value">v{{ $selectedStock['authKeyVersion'] }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Assignment</div>
                    <div class="drawer-field"><span class="drawer-field-label">Agent</span><span class="drawer-field-value">{{ $selectedStock['assignedAgentId'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Assigned At</span><span class="drawer-field-value">{{ $selectedStock['assignedAt'] ? \Carbon\Carbon::parse($selectedStock['assignedAt'])->format('d M Y, H:i') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Sold To</span><span class="drawer-field-value">{{ $selectedStock['soldToCustomerId'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Sold At</span><span class="drawer-field-value">{{ $selectedStock['soldAt'] ? \Carbon\Carbon::parse($selectedStock['soldAt'])->format('d M Y, H:i') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Card ID</span><span class="drawer-field-value">{{ $selectedStock['cardId'] ?? '-' }}</span></div>
                </div>
            </div>
        </div>
    @endif

    @if($showCloseModal)
        <div class="modal-overlay" wire:click.self="$set('showCloseModal', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">Close Card</span>
                    <button class="modal-close" wire:click="$set('showCloseModal', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    @if($notification && $notificationType === 'danger')
                        <div class="alert alert-danger mb-4"><x-icon name="alert-triangle" size="15" /> {{ $notification }}</div>
                    @endif
                    <label class="form-label">Reason</label>
                    <textarea wire:model="closeReason" class="form-textarea" rows="3"></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('showCloseModal', false)">Cancel</button>
                    <button class="btn btn-danger btn-md" wire:click="closeCard">Close Card</button>
                </div>
            </div>
        </div>
    @endif

    @if($showImportModal)
        <div class="modal-overlay" wire:click.self="$set('showImportModal', false)">
            <div class="modal modal-lg">
                <div class="modal-header">
                    <span class="modal-title">Import Card Stock</span>
                    <button class="modal-close" wire:click="$set('showImportModal', false)"><x-icon name="x" size="18" /></button>
                </div>
                <form wire:submit.prevent="submitImportBatch">
                    <div class="modal-body">
                        @if($notification && $notificationType === 'danger')
                            <div class="alert alert-danger mb-4"><x-icon name="alert-triangle" size="15" /> {{ $notification }}</div>
                        @endif
                        <div class="mb-4 grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">Batch Ref <span class="form-required">*</span></label>
                                <input wire:model="importBatch.batchRef" type="text" class="form-input is-mono" placeholder="e.g. BATCH-2026-05-A" />
                                @error('importBatch.batchRef') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="form-label">Produced At</label>
                                <input wire:model="importBatch.producedAt" type="date" class="form-input" />
                                @error('importBatch.producedAt') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        @error('importCards') <div class="form-error mb-2">{{ $message }}</div> @enderror
                        <div class="flex flex-col gap-3">
                            @foreach($importCards as $index => $row)
                                <div>
                                    <div class="grid grid-cols-[1fr_1fr_90px_32px] gap-2">
                                        <input wire:model="importCards.{{ $index }}.nfcUid" type="text" class="form-input is-mono" placeholder="NFC UID" maxlength="14" />
                                        <input wire:model="importCards.{{ $index }}.internalCardNumber" type="text" class="form-input is-mono" placeholder="Internal number" />
                                        <input wire:model="importCards.{{ $index }}.authKeyVersion" type="number" min="0" class="form-input is-mono" placeholder="Key v" />
                                        <button class="btn btn-secondary btn-sm !px-2" wire:click="removeImportCardRow({{ $index }})" type="button"><x-icon name="x" size="13" /></button>
                                    </div>
                                    @error("importCards.$index.nfcUid") <div class="form-error">{{ $message }}</div> @enderror
                                    @error("importCards.$index.internalCardNumber") <div class="form-error">{{ $message }}</div> @enderror
                                    @error("importCards.$index.authKeyVersion") <div class="form-error">{{ $message }}</div> @enderror
                                </div>
                            @endforeach
                        </div>
                        <button class="btn btn-ghost btn-sm mt-3" wire:click="addImportCardRow" wire:loading.attr="disabled" wire:target="submitImportBatch" type="button">
                            <x-icon name="plus" size="13" /> Add Card
                        </button>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary btn-md" wire:click="$set('showImportModal', false)" wire:loading.attr="disabled" wire:target="submitImportBatch" type="button">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-md justify-center" wire:loading.attr="disabled" wire:target="submitImportBatch">
                            <span wire:loading.remove wire:target="submitImportBatch">Import Batch</span>
                            <span wire:loading wire:target="submitImportBatch">Importing...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showAssignModal)
        <div class="modal-overlay" wire:click.self="$set('showAssignModal', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">Assign Card Stock</span>
                    <button class="modal-close" wire:click="$set('showAssignModal', false)"><x-icon name="x" size="18" /></button>
                </div>
                <form wire:submit.prevent="assignCardStock">
                    <div class="modal-body">
                        @if($notification && $notificationType === 'danger')
                            <div class="alert alert-danger mb-4"><x-icon name="alert-triangle" size="15" /> {{ $notification }}</div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label">Agent ID <span class="form-required">*</span></label>
                            <input wire:model="assignStock.agentId" type="text" class="form-input is-mono" placeholder="ag01" />
                            @error('assignStock.agentId') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Stock <span class="form-required">*</span></label>
                            <div class="flex max-h-56 flex-col gap-2 overflow-y-auto rounded-md border border-[var(--border-color)] p-3">
                                @forelse($availableStock as $stock)
                                    <label class="flex cursor-pointer items-center gap-2 text-xs">
                                        <input type="checkbox" wire:model="assignStock.cardStockIds" value="{{ $stock['id'] }}" />
                                        <span class="text-mono">{{ $stock['internalCardNumber'] }}</span>
                                        <span class="text-[var(--text-secondary)]">{{ $stock['batchRef'] }}</span>
                                    </label>
                                @empty
                                    <span class="text-xs text-[var(--text-secondary)]">No warehouse stock available</span>
                                @endforelse
                            </div>
                            @error('assignStock.cardStockIds') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary btn-md" wire:click="$set('showAssignModal', false)" wire:loading.attr="disabled" wire:target="assignCardStock" type="button">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-md justify-center" wire:loading.attr="disabled" wire:target="assignCardStock">
                            <span wire:loading.remove wire:target="assignCardStock">Assign Stock</span>
                            <span wire:loading wire:target="assignCardStock">Assigning...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
