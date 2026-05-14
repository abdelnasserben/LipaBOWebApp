<?php

use Livewire\Component;
use App\Exceptions\BackofficeApiException;
use App\Enums\Backoffice\TerminalStatus;
use App\Livewire\Concerns\UsesBackofficeEnums;
use App\Livewire\Concerns\WithApiCursorPagination;
use App\Services\Api\UsesBackofficeApi;
use App\Support\BackofficeEnums;

new class extends Component {
    use WithApiCursorPagination;
    use UsesBackofficeApi;
    use UsesBackofficeEnums;
    public string $merchantIdFilter = '';
    public string $statusFilter = '';
    public ?array $selected = null;
    public ?array $provisionResponse = null;
    public bool $showRegisterModal = false;
    public ?string $pendingTerminalAction = null;
    public array $terminalActionConfirmation = [];
    public string $terminalActionError = '';
    public string $notification = '';
    public string $notificationType = 'success';

    public array $newTerminal = [
        'serialNumber' => '',
        'deviceModel' => '',
        'androidVersion' => '',
        'appVersion' => '',
        'merchantId' => '',
    ];

    public function updatingMerchantIdFilter(): void { $this->resetCursorPage('terminals'); }
    public function updatingStatusFilter(): void { $this->resetCursorPage('terminals'); }

    public function selectRow(string $id): void
    {
        $this->selected = $this->api()->terminal($id);
        $this->provisionResponse = null;
    }

    public function closeDrawer(): void
    {
        $this->cancelTerminalAction();
        $this->selected = null;
        $this->provisionResponse = null;
    }

    public function openRegisterModal(): void
    {
        $this->notification = '';
        $this->notificationType = 'success';
        $this->resetValidation();
        $this->showRegisterModal = true;
    }

    public function registerTerminal(): void
    {
        $this->validate([
            'newTerminal.serialNumber' => 'required|string|max:100',
            'newTerminal.deviceModel' => 'nullable|string|max:100',
            'newTerminal.androidVersion' => 'nullable|string|max:30',
            'newTerminal.appVersion' => 'nullable|string|max:30',
            'newTerminal.merchantId' => 'required|string',
        ]);

        $this->api()->createTerminal($this->newTerminal);
        $this->notify('Terminal registered.');
        $this->showRegisterModal = false;
        $this->newTerminal = ['serialNumber' => '', 'deviceModel' => '', 'androidVersion' => '', 'appVersion' => '', 'merchantId' => ''];
    }

    public function provision(): void
    {
        $this->openTerminalAction('provision');
    }

    public function suspend(): void
    {
        $this->openTerminalAction('suspend');
    }

    public function reactivate(): void
    {
        $this->openTerminalAction('reactivate');
    }

    public function cancelTerminalAction(): void
    {
        $this->pendingTerminalAction = null;
        $this->terminalActionConfirmation = [];
        $this->terminalActionError = '';
    }

    public function confirmTerminalAction(): void
    {
        if (!$this->pendingTerminalAction || !$this->selected) {
            return;
        }

        if (!$this->terminalActionAllowed($this->pendingTerminalAction)) {
            $this->terminalActionError = 'This action is no longer available for the selected terminal.';

            return;
        }

        $action = $this->pendingTerminalAction;
        $this->terminalActionError = '';

        try {
            match ($action) {
                'provision' => $this->performProvision(),
                'suspend' => $this->performSuspend(),
                'reactivate' => $this->performReactivate(),
            };
        } catch (BackofficeApiException $e) {
            if ($e->status === 401) {
                throw $e;
            }

            $this->terminalActionError = $e->userMessage();

            return;
        }

        $this->cancelTerminalAction();
    }

    private function openTerminalAction(string $action): void
    {
        if (!$this->selected) {
            return;
        }

        if (!$this->terminalActionAllowed($action)) {
            $this->notify('This action is not available for the selected terminal.', 'danger');

            return;
        }

        $this->pendingTerminalAction = $action;
        $this->terminalActionConfirmation = $this->terminalActionConfig($action);
        $this->terminalActionError = '';
    }

    private function performProvision(): void
    {
        $resp = $this->api()->provisionTerminal($this->selected['id']);
        // The mock returns just ['ok' => true]; synthesize the spec'd response
        // so the existing UI (which displays rawApiKey, etc.) keeps working.
        $issuedAt = now()->toIso8601String();
        $expiresAt = now()->addYear()->toIso8601String();
        $this->provisionResponse = $resp + [
            'terminalId' => $this->selected['id'],
            'serialNumber' => $this->selected['serialNumber'] ?? null,
            'status' => 'ACTIVE',
            'rawApiKey' => 'lipa_live_' . strtoupper($this->selected['id']) . '_7F4C9D2A',
            'apiKeyIssuedAt' => $issuedAt,
            'apiKeyExpiresAt' => $expiresAt,
        ];
        $this->refreshSelected();
        $this->notify('Terminal provisioned.');
    }

    private function performSuspend(): void
    {
        $this->api()->suspendTerminal($this->selected['id']);
        $this->notify('Terminal suspended.');
        $this->closeDrawer();
    }

    private function performReactivate(): void
    {
        $this->api()->reactivateTerminal($this->selected['id']);
        $this->notify('Terminal reactivated.');
        $this->closeDrawer();
    }

    private function terminalActionAllowed(string $action): bool
    {
        $status = $this->selected['status'] ?? null;

        return match ($action) {
            'provision' => $status !== 'REVOKED',
            'suspend' => in_array($status, ['REGISTERED', 'ACTIVE'], true),
            'reactivate' => $status === 'SUSPENDED',
            default => false,
        };
    }

    private function terminalActionConfig(string $action): array
    {
        $serial = (string) ($this->selected['serialNumber'] ?? 'Selected terminal');
        $id = (string) ($this->selected['id'] ?? '');

        return match ($action) {
            'provision' => [
                'title' => 'Provision terminal',
                'message' => 'This will request fresh API credentials for the selected terminal.',
                'confirmLabel' => 'Provision terminal',
                'cancelLabel' => 'Cancel',
                'style' => 'primary',
                'entityLabel' => 'Terminal',
                'entityName' => $serial,
                'entityId' => $id,
            ],
            'suspend' => [
                'title' => 'Suspend terminal',
                'message' => 'Suspending this terminal will prevent it from processing operations until it is reactivated.',
                'confirmLabel' => 'Suspend terminal',
                'cancelLabel' => 'Cancel',
                'style' => 'warning',
                'entityLabel' => 'Terminal',
                'entityName' => $serial,
                'entityId' => $id,
            ],
            'reactivate' => [
                'title' => 'Reactivate terminal',
                'message' => 'Reactivating this terminal will allow it to be used again.',
                'confirmLabel' => 'Reactivate terminal',
                'cancelLabel' => 'Cancel',
                'style' => 'primary',
                'entityLabel' => 'Terminal',
                'entityName' => $serial,
                'entityId' => $id,
            ],
        };
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

        return !config('komopay.use_mock_api') && $value !== '' && !$this->isUuid($value);
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value) === 1;
    }

    private function refreshSelected(): void
    {
        $id = $this->selected['id'] ?? null;

        if (!is_string($id) || $id === '') {
            return;
        }

        $this->selected = $this->api()->terminal($id) ?? $this->selected;
    }

    public function render(): \Illuminate\View\View
    {
        $merchantIdFilter = $this->optionalUuidFilter($this->merchantIdFilter);

        $page = $this->api()->terminalsPage($this->cursorPageQuery('terminals') + [
            'merchantId' => $merchantIdFilter,
            'status' => $this->statusFilter ?: null,
        ]);
        $rows = $page['data'];
        $statusRows = $this->api()->terminals([
            'merchantId' => $merchantIdFilter,
            'limit' => 100,
        ]);

        return view('livewire.terminals.terminal-management', [
            'rows' => $rows,
            'paginator' => $this->cursorPaginator('terminals', $page, count($rows), 'terminals'),
            'statusOptions' => BackofficeEnums::optionsFromRows($statusRows, 'status', TerminalStatus::class, $this->statusFilter),
            'merchantIdFilterInvalid' => $this->hasInvalidUuidFilter($this->merchantIdFilter),
        ]);
    }
};
?>

<div>
    <x-page-header title="Terminals" subtitle="Merchant POS terminals and provisioning">
        <x-slot:actions>
            <button class="btn btn-primary btn-md" wire:click="openRegisterModal">
                <x-icon name="plus" size="13" /> Register Terminal
            </button>
        </x-slot:actions>
    </x-page-header>

    @if ($notification)
        <div class="alert alert-{{ $notificationType }} mb-4">
            <x-icon name="{{ $notificationType === 'danger' ? 'alert-triangle' : 'check' }}" size="15" />
            {{ $notification }}
        </div>
    @endif

    <div class="card">
        <div class="filter-bar">
            <div>
                <input wire:model.live.debounce.300ms="merchantIdFilter" type="text"
                    class="filter-select !cursor-text" placeholder="Merchant ID" />
                @if ($merchantIdFilterInvalid)
                    <div class="form-error mt-1">Enter a full UUID to filter.</div>
                @endif
            </div>
            <select wire:model.live="statusFilter" class="filter-select">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Terminal</th>
                        <th>Merchant</th>
                        <th>Model</th>
                        <th>App</th>
                        <th>API Key</th>
                        <th>Last Auth</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="table-row-link" wire:click="selectRow('{{ $row['id'] }}')">
                            <td>
                                <div class="font-medium">{{ $row['serialNumber'] }}</div>
                                <x-mono>{{ $row['id'] }}</x-mono>
                            </td>
                            <td><x-mono>{{ $row['merchantId'] }}</x-mono></td>
                            <td>
                                <span class="text-xs">{{ $row['deviceModel'] ?? '-' }}</span>
                                <div class="text-[11px] text-[var(--text-secondary)]">Android
                                    {{ $row['androidVersion'] ?? '-' }}</div>
                            </td>
                            <td><x-mono>{{ $row['appVersion'] ?? '-' }}</x-mono></td>
                            <td><x-mono>{{ $row['apiKeyIssuedAt'] ? \Carbon\Carbon::parse($row['apiKeyIssuedAt'])->format('d M Y') : 'Not issued' }}</x-mono>
                            </td>
                            <td><x-mono>{{ $row['lastAuthAt'] ? \Carbon\Carbon::parse($row['lastAuthAt'])->format('d M, H:i') : '-' }}</x-mono>
                            </td>
                            <td><x-badge :status="$row['status']" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-state-title">No terminals found</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-cursor-pagination :paginator="$paginator" />
    </div>

    @if ($selected)
        <div class="drawer-overlay" wire:click="closeDrawer"></div>
        <div class="drawer">
            <div class="drawer-header">
                <div>
                    <span class="drawer-title">Terminal</span><br>
                    <span class="drawer-field-value">{{ $selected['serialNumber'] }}</span>
                </div>
                <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
            </div>
            <div class="drawer-body">
                <div class="mb-4"><x-badge :status="$selected['status']" /></div>
                @if ($notification && $notificationType === 'danger')
                    <div class="alert alert-danger mb-4"><x-icon name="alert-triangle" size="15" />
                        {{ $notification }}</div>
                @endif

                @if ($provisionResponse)
                    <div class="drawer-section">
                        <div class="drawer-section-title">Provisioning</div>
                        <div class="rounded-md border border-[var(--green)] bg-[var(--green-bg)] p-3">
                            <div class="mb-1 text-[11px] font-semibold uppercase text-[var(--green)]">Raw API Key</div>
                            <div class="break-all text-mono text-xs text-[var(--text-primary)]">
                                {{ $provisionResponse['rawApiKey'] }}</div>
                        </div>
                    </div>
                @endif

                <div class="drawer-section">
                    <div class="drawer-section-title">Device</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span
                            class="drawer-field-value">{{ $selected['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Serial</span><span
                            class="drawer-field-value">{{ $selected['serialNumber'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Model</span><span
                            class="drawer-field-value">{{ $selected['deviceModel'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Android</span><span
                            class="drawer-field-value">{{ $selected['androidVersion'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">App Version</span><span
                            class="drawer-field-value">{{ $selected['appVersion'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Merchant</span><span
                            class="drawer-field-value">{{ $selected['merchantId'] }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Authentication</div>
                    <div class="drawer-field"><span class="drawer-field-label">API Key Issued</span><span
                            class="drawer-field-value">{{ $selected['apiKeyIssuedAt'] ? \Carbon\Carbon::parse($selected['apiKeyIssuedAt'])->format('d M Y, H:i') : '-' }}</span>
                    </div>
                    <div class="drawer-field"><span class="drawer-field-label">API Key Expires</span><span
                            class="drawer-field-value">{{ $selected['apiKeyExpiresAt'] ? \Carbon\Carbon::parse($selected['apiKeyExpiresAt'])->format('d M Y, H:i') : '-' }}</span>
                    </div>
                    <div class="drawer-field"><span class="drawer-field-label">Last Auth</span><span
                            class="drawer-field-value">{{ $selected['lastAuthAt'] ? \Carbon\Carbon::parse($selected['lastAuthAt'])->format('d M Y, H:i') : '-' }}</span>
                    </div>
                    <div class="drawer-field"><span class="drawer-field-label">Auth Failures</span><span
                            class="drawer-field-value">{{ number_format($selected['authFailedCount']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Registered</span><span
                            class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['registeredAt'])->format('d M Y, H:i') }}</span>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                @if ($selected['status'] !== 'REVOKED')
                    <button class="btn btn-secondary btn-sm" wire:click="provision">
                        <x-icon name="key" size="13" /> Provision
                    </button>
                @endif
                @if (in_array($selected['status'], ['REGISTERED', 'ACTIVE']))
                    <button class="btn btn-warning btn-sm" wire:click="suspend">Suspend</button>
                @elseif($selected['status'] === 'SUSPENDED')
                    <button class="btn btn-primary btn-sm" wire:click="reactivate">Reactivate</button>
                @endif
            </div>
        </div>
    @endif

    @if ($pendingTerminalAction && $terminalActionConfirmation)
        <x-confirmation-modal :title="$terminalActionConfirmation['title']" :message="$terminalActionConfirmation['message']" :confirm-label="$terminalActionConfirmation['confirmLabel']" :cancel-label="$terminalActionConfirmation['cancelLabel']" :action-style="$terminalActionConfirmation['style']"
            confirm-action="confirmTerminalAction" cancel-action="cancelTerminalAction" :entity-label="$terminalActionConfirmation['entityLabel']"
            :entity-name="$terminalActionConfirmation['entityName']" :entity-id="$terminalActionConfirmation['entityId']" :error="$terminalActionError" />
    @endif

    @if ($showRegisterModal)
        <div class="modal-overlay" wire:click.self="$set('showRegisterModal', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">Register Terminal</span>
                    <button class="modal-close" wire:click="$set('showRegisterModal', false)"><x-icon name="x"
                            size="18" /></button>
                </div>
                <div class="modal-body">
                    @if ($notification && $notificationType === 'danger')
                        <div class="alert alert-danger mb-4"><x-icon name="alert-triangle" size="15" />
                            {{ $notification }}</div>
                    @endif
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="form-label">Serial Number <span class="form-required">*</span></label>
                            <input wire:model="newTerminal.serialNumber" type="text" class="form-input is-mono"
                                placeholder="e.g. SN-PAX-A920-001234" />
                            @error('newTerminal.serialNumber')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label">Merchant ID <span class="form-required">*</span></label>
                            <input wire:model="newTerminal.merchantId" type="text" class="form-input is-mono"
                                placeholder="Merchant UUID" />
                            @error('newTerminal.merchantId')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">Device Model</label>
                                <input wire:model="newTerminal.deviceModel" type="text" class="form-input"
                                    placeholder="e.g. PAX A920" />
                                @error('newTerminal.deviceModel')
                                    <div class="form-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Android Version</label>
                                <input wire:model="newTerminal.androidVersion" type="text"
                                    class="form-input is-mono" placeholder="e.g. 11" />
                                @error('newTerminal.androidVersion')
                                    <div class="form-error">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div>
                            <label class="form-label">App Version</label>
                            <input wire:model="newTerminal.appVersion" type="text" class="form-input is-mono"
                                placeholder="e.g. 1.4.2" />
                            @error('newTerminal.appVersion')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md"
                        wire:click="$set('showRegisterModal', false)">Cancel</button>
                    <button class="btn btn-primary btn-md" wire:click="registerTerminal">Register</button>
                </div>
            </div>
        </div>
    @endif
</div>
