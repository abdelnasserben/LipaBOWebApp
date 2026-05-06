<?php

use Livewire\Component;
use App\Services\Mock\MockDataService;

new class extends Component
{
    public string $merchantIdFilter = '';
    public string $statusFilter = '';
    public ?array $selected = null;
    public ?array $provisionResponse = null;
    public bool $showRegisterModal = false;
    public string $notification = '';

    public array $newTerminal = [
        'serialNumber' => '',
        'deviceModel' => '',
        'androidVersion' => '',
        'appVersion' => '',
        'merchantId' => '',
    ];

    public array $terminalStatuses = ['REGISTERED', 'ACTIVE', 'SUSPENDED', 'REVOKED'];

    public function selectRow(string $id): void
    {
        $this->selected = MockDataService::terminal($id);
        $this->provisionResponse = null;
    }

    public function closeDrawer(): void
    {
        $this->selected = null;
        $this->provisionResponse = null;
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

        // Real: POST /api/v1/backoffice/terminals
        // Body: RegisterTerminalRequest
        $this->notification = 'Terminal registered.';
        $this->showRegisterModal = false;
        $this->newTerminal = ['serialNumber' => '', 'deviceModel' => '', 'androidVersion' => '', 'appVersion' => '', 'merchantId' => ''];
    }

    public function provision(): void
    {
        if (!$this->selected) {
            return;
        }

        // Real: POST /api/v1/backoffice/terminals/{id}/provision
        // rawApiKey is returned only in this response.
        $issuedAt = now()->toIso8601String();
        $expiresAt = now()->addYear()->toIso8601String();
        $this->provisionResponse = [
            'terminalId' => $this->selected['id'],
            'serialNumber' => $this->selected['serialNumber'],
            'status' => 'ACTIVE',
            'rawApiKey' => 'lipa_live_' . strtoupper($this->selected['id']) . '_7F4C9D2A',
            'apiKeyIssuedAt' => $issuedAt,
            'apiKeyExpiresAt' => $expiresAt,
        ];
        $this->notification = 'Terminal provisioned.';
    }

    public function suspend(): void
    {
        // Real: POST /api/v1/backoffice/terminals/{id}/suspend
        $this->notification = 'Terminal suspended.';
        $this->closeDrawer();
    }

    public function reactivate(): void
    {
        // Real: POST /api/v1/backoffice/terminals/{id}/reactivate
        $this->notification = 'Terminal reactivated.';
        $this->closeDrawer();
    }

    public function enumLabel(?string $value, string $fallback = '-'): string
    {
        return filled($value) ? str_replace('_', ' ', $value) : $fallback;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.terminals.terminal-management', [
            'rows' => MockDataService::terminals([
                'merchantId' => $this->merchantIdFilter ?: null,
                'status' => $this->statusFilter ?: null,
            ]),
        ]);
    }
};
?>

<div>
    @if($notification)
        <div class="alert alert-success mb-4"><x-icon name="check" size="15" /> {{ $notification }}</div>
    @endif

    <div class="card">
        <div class="filter-bar">
            <input wire:model.live.debounce.300ms="merchantIdFilter" type="text" class="filter-select !cursor-text" placeholder="Merchant ID" />
            <select wire:model.live="statusFilter" class="filter-select">
                <option value="">All statuses</option>
                @foreach($terminalStatuses as $status)
                    <option value="{{ $status }}">{{ $this->enumLabel($status) }}</option>
                @endforeach
            </select>
            <div class="flex-1"></div>
            <button class="btn btn-primary btn-sm" wire:click="$set('showRegisterModal', true)">
                <x-icon name="plus" size="13" /> Register Terminal
            </button>
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
                                <div class="text-[11px] text-[var(--text-secondary)]">Android {{ $row['androidVersion'] ?? '-' }}</div>
                            </td>
                            <td><x-mono>{{ $row['appVersion'] ?? '-' }}</x-mono></td>
                            <td><x-mono>{{ $row['apiKeyIssuedAt'] ? \Carbon\Carbon::parse($row['apiKeyIssuedAt'])->format('d M Y') : 'Not issued' }}</x-mono></td>
                            <td><x-mono>{{ $row['lastAuthAt'] ? \Carbon\Carbon::parse($row['lastAuthAt'])->format('d M, H:i') : '-' }}</x-mono></td>
                            <td><x-badge :status="$row['status']" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No terminals found</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination"><span class="pagination-info">{{ count($rows) }} terminals</span></div>
    </div>

    @if($selected)
        <div class="drawer-overlay" wire:click="closeDrawer"></div>
        <div class="drawer">
            <div class="drawer-header">
                <span class="drawer-title">{{ $selected['serialNumber'] }}</span>
                <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
            </div>
            <div class="drawer-body">
                <div class="mb-4"><x-badge :status="$selected['status']" /></div>

                @if($provisionResponse)
                    <div class="drawer-section">
                        <div class="drawer-section-title">Provisioning</div>
                        <div class="rounded-md border border-[var(--green)] bg-[var(--green-bg)] p-3">
                            <div class="mb-1 text-[11px] font-semibold uppercase text-[var(--green)]">Raw API Key</div>
                            <div class="break-all text-mono text-xs text-[var(--text-primary)]">{{ $provisionResponse['rawApiKey'] }}</div>
                        </div>
                    </div>
                @endif

                <div class="drawer-section">
                    <div class="drawer-section-title">Device</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selected['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Serial</span><span class="drawer-field-value">{{ $selected['serialNumber'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Model</span><span class="drawer-field-value">{{ $selected['deviceModel'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Android</span><span class="drawer-field-value">{{ $selected['androidVersion'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">App Version</span><span class="drawer-field-value">{{ $selected['appVersion'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Merchant</span><span class="drawer-field-value">{{ $selected['merchantId'] }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Authentication</div>
                    <div class="drawer-field"><span class="drawer-field-label">API Key Issued</span><span class="drawer-field-value">{{ $selected['apiKeyIssuedAt'] ? \Carbon\Carbon::parse($selected['apiKeyIssuedAt'])->format('d M Y, H:i') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">API Key Expires</span><span class="drawer-field-value">{{ $selected['apiKeyExpiresAt'] ? \Carbon\Carbon::parse($selected['apiKeyExpiresAt'])->format('d M Y, H:i') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Last Auth</span><span class="drawer-field-value">{{ $selected['lastAuthAt'] ? \Carbon\Carbon::parse($selected['lastAuthAt'])->format('d M Y, H:i') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Auth Failures</span><span class="drawer-field-value">{{ number_format($selected['authFailedCount']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Registered</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['registeredAt'])->format('d M Y, H:i') }}</span></div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($selected['status'] !== 'REVOKED')
                    <button class="btn btn-secondary btn-sm" wire:click="provision">
                        <x-icon name="key" size="13" /> Provision
                    </button>
                @endif
                @if(in_array($selected['status'], ['REGISTERED', 'ACTIVE']))
                    <button class="btn btn-warning btn-sm" wire:click="suspend">Suspend</button>
                @elseif($selected['status'] === 'SUSPENDED')
                    <button class="btn btn-primary btn-sm" wire:click="reactivate">Reactivate</button>
                @endif
            </div>
        </div>
    @endif

    @if($showRegisterModal)
        <div class="modal-overlay" wire:click.self="$set('showRegisterModal', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">Register Terminal</span>
                    <button class="modal-close" wire:click="$set('showRegisterModal', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="form-label">Serial Number <span class="form-required">*</span></label>
                            <input wire:model="newTerminal.serialNumber" type="text" class="form-input is-mono" />
                        </div>
                        <div>
                            <label class="form-label">Merchant ID <span class="form-required">*</span></label>
                            <input wire:model="newTerminal.merchantId" type="text" class="form-input is-mono" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">Device Model</label>
                                <input wire:model="newTerminal.deviceModel" type="text" class="form-input" />
                            </div>
                            <div>
                                <label class="form-label">Android Version</label>
                                <input wire:model="newTerminal.androidVersion" type="text" class="form-input is-mono" />
                            </div>
                        </div>
                        <div>
                            <label class="form-label">App Version</label>
                            <input wire:model="newTerminal.appVersion" type="text" class="form-input is-mono" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('showRegisterModal', false)">Cancel</button>
                    <button class="btn btn-primary btn-md" wire:click="registerTerminal">Register</button>
                </div>
            </div>
        </div>
    @endif
</div>
