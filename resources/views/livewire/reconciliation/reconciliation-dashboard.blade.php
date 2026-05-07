<?php

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Services\Mock\MockDataService;

new class extends Component
{
    #[Url(as: 'tab')]
    public string $tab = 'incidents';

    public string $incidentStatusFilter = '';
    public string $runStatusFilter = '';

    public ?array $selectedIncident = null;
    public ?array $selectedRun = null;

    public bool $showResolveModal = false;
    public bool $showCloseIncidentModal = false;
    public string $notification = '';

    public array $resolveForm = [
        'note' => '',
        'suspenseAdjustmentAmount' => 0,
        'suspenseDirection' => '',
    ];

    public array $closeForm = [
        'note' => '',
        'clearSuspense' => false,
    ];

    public array $incidentStatuses = ['OPEN', 'UNDER_INVESTIGATION', 'RESOLVED', 'CLOSED'];
    public array $incidentTypes = ['DOUBLE_ENTRY_MISMATCH', 'BALANCE_MISMATCH', 'FLOAT_IDENTITY_BREACH'];
    public array $runStatuses = ['OK', 'MISMATCH'];
    public array $suspenseDirections = ['TO_SUSPENSE', 'FROM_SUSPENSE'];

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->closeDrawer();
        $this->incidentStatusFilter = '';
        $this->runStatusFilter = '';
    }

    public function selectIncident(string $id): void
    {
        $this->selectedIncident = MockDataService::reconciliationIncident($id);
        $this->selectedRun = null;
    }

    public function selectRun(string $id): void
    {
        $this->selectedRun = MockDataService::reconciliationRun($id);
        $this->selectedIncident = null;
    }

    public function closeDrawer(): void
    {
        $this->selectedIncident = null;
        $this->selectedRun = null;
        $this->showResolveModal = false;
        $this->showCloseIncidentModal = false;
    }

    public function investigateIncident(): void
    {
        if (!$this->selectedIncident) {
            return;
        }

        // Real: POST /api/v1/backoffice/reconciliation/incidents/{id}/investigate
        // Body: optional empty object. Returns 200 ReconciliationIncidentResponse.
        $this->notification = 'Reconciliation incident marked for investigation.';
        $this->closeDrawer();
    }

    public function openResolveModal(): void
    {
        if (!$this->selectedIncident) {
            return;
        }

        $this->resolveForm = [
            'note' => '',
            'suspenseAdjustmentAmount' => 0,
            'suspenseDirection' => '',
        ];
        $this->showCloseIncidentModal = false;
        $this->showResolveModal = true;
    }

    public function resolveIncident(): void
    {
        $this->validate([
            'resolveForm.note' => 'required|string|max:500',
            'resolveForm.suspenseAdjustmentAmount' => 'required|integer|min:0',
            'resolveForm.suspenseDirection' => 'nullable|in:' . implode(',', $this->suspenseDirections),
        ]);

        if ((int) $this->resolveForm['suspenseAdjustmentAmount'] > 0 && blank($this->resolveForm['suspenseDirection'])) {
            $this->addError('resolveForm.suspenseDirection', 'Suspense direction is required when amount is greater than zero.');
            return;
        }

        // Real: POST /api/v1/backoffice/reconciliation/incidents/{id}/resolve
        // Body: ResolveIncidentRequest { note, suspenseAdjustmentAmount, suspenseDirection? }.
        // Returns 201 when a RECONCILIATION_ADJUSTMENT approval is created, otherwise 200.
        $this->notification = 'Reconciliation resolve action submitted.';
        $this->showResolveModal = false;
        $this->closeDrawer();
    }

    public function openCloseIncidentModal(): void
    {
        if (!$this->selectedIncident) {
            return;
        }

        $this->closeForm = [
            'note' => '',
            'clearSuspense' => false,
        ];
        $this->showResolveModal = false;
        $this->showCloseIncidentModal = true;
    }

    public function closeIncident(): void
    {
        $this->validate([
            'closeForm.note' => 'required|string|max:500',
            'closeForm.clearSuspense' => 'boolean',
        ]);

        // Real: POST /api/v1/backoffice/reconciliation/incidents/{id}/close
        // Body: CloseIncidentRequest { note, clearSuspense }.
        // Returns 201 when a RECONCILIATION_ADJUSTMENT approval is created, otherwise 200.
        $this->notification = 'Reconciliation close action submitted.';
        $this->showCloseIncidentModal = false;
        $this->closeDrawer();
    }

    public function enumLabel(?string $value, string $fallback = '-'): string
    {
        return filled($value) ? str_replace('_', ' ', $value) : $fallback;
    }

    public function render(): \Illuminate\View\View
    {
        $allIncidents = MockDataService::reconciliationIncidents();
        $allRuns = MockDataService::reconciliationRuns();
        $activeIncidents = array_filter($allIncidents, fn($incident) => in_array($incident['status'], ['OPEN', 'UNDER_INVESTIGATION']));

        return view('livewire.reconciliation.reconciliation-dashboard', [
            'incidents' => MockDataService::reconciliationIncidents([
                'status' => $this->incidentStatusFilter ?: null,
            ]),
            'runs' => MockDataService::reconciliationRuns([
                'status' => $this->runStatusFilter ?: null,
            ]),
            'openIncidentCount' => count($activeIncidents),
            'resolvedPendingCloseCount' => count(array_filter($allIncidents, fn($incident) => $incident['status'] === 'RESOLVED')),
            'activeDiscrepancyAmount' => array_sum(array_map(fn($incident) => $incident['discrepancyAmount'], $activeIncidents)),
            'mismatchRunCount' => count(array_filter($allRuns, fn($run) => $run['status'] === 'MISMATCH')),
        ]);
    }
};
?>

<div>
    <x-page-header
        title="Reconciliation"
        subtitle="Ledger integrity, runs and incident handling"
    />

    @if($notification)
        <div class="alert alert-success mb-4"><x-icon name="check" size="15" /> {{ $notification }}</div>
    @endif

    <div class="card">
        <div class="tabs">
            <button class="tab @if($tab==='incidents') active @endif" wire:click="setTab('incidents')">Incidents</button>
            <button class="tab @if($tab==='runs') active @endif" wire:click="setTab('runs')">Runs</button>
        </div>

        <div class="filter-bar">
            @if($tab === 'incidents')
                <select wire:model.live="incidentStatusFilter" class="filter-select">
                    <option value="">All statuses</option>
                    @foreach($incidentStatuses as $status)
                        <option value="{{ $status }}">{{ $this->enumLabel($status) }}</option>
                    @endforeach
                </select>
                <div class="flex-1"></div>
                <span class="text-xs text-[var(--text-secondary)]">{{ count($incidentTypes) }} incident types monitored</span>
            @else
                <select wire:model.live="runStatusFilter" class="filter-select">
                    <option value="">All statuses</option>
                    @foreach($runStatuses as $status)
                        <option value="{{ $status }}">{{ $this->enumLabel($status) }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        @if($tab === 'incidents')
            <div class="grid grid-cols-1 gap-3 border-b border-[var(--border-color)] p-5 md:grid-cols-3">
                <div>
                    <div class="kpi-label">Open / Investigating</div>
                    <div class="kpi-value">{{ number_format($openIncidentCount) }}</div>
                </div>
                <div>
                    <div class="kpi-label">Active Discrepancy</div>
                    <x-amount :value="$activeDiscrepancyAmount" size="20" />
                </div>
                <div>
                    <div class="kpi-label">Resolved Pending Close</div>
                    <div class="kpi-value">{{ number_format($resolvedPendingCloseCount) }}</div>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Incident</th>
                            <th>Run</th>
                            <th>Type</th>
                            <th>Discrepancy</th>
                            <th>Suspense</th>
                            <th>Opened</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($incidents as $incident)
                            <tr class="table-row-link" wire:click="selectIncident('{{ $incident['id'] }}')">
                                <td>
                                    <div class="font-medium">{{ strtoupper($incident['id']) }}</div>
                                    <div class="max-w-[260px] truncate text-[11px] text-[var(--text-secondary)]">{{ $incident['description'] }}</div>
                                </td>
                                <td><x-mono>{{ strtoupper($incident['runId']) }}</x-mono></td>
                                <td><span class="text-xs font-medium">{{ $this->enumLabel($incident['incidentType']) }}</span></td>
                                <td><x-amount :value="$incident['discrepancyAmount']" size="12" /></td>
                                <td><x-mono>{{ $incident['suspenseEntryRef'] ?? '-' }}</x-mono></td>
                                <td><x-mono>{{ \Carbon\Carbon::parse($incident['openedAt'])->format('d M, H:i') }}</x-mono></td>
                                <td><x-badge :status="$incident['status']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No reconciliation incidents found</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination"><span class="pagination-info">{{ count($incidents) }} incidents</span></div>
        @else
            <div class="grid grid-cols-1 gap-3 border-b border-[var(--border-color)] p-5 md:grid-cols-2">
                <div>
                    <div class="kpi-label">Mismatch Runs</div>
                    <div class="kpi-value">{{ number_format($mismatchRunCount) }}</div>
                </div>
                <div>
                    <div class="kpi-label">Visible Runs</div>
                    <div class="kpi-value">{{ number_format(count($runs)) }}</div>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Run</th>
                            <th>Window</th>
                            <th>Transactions</th>
                            <th>Wallets</th>
                            <th>Mismatches</th>
                            <th>Completed</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($runs as $run)
                            <tr class="table-row-link" wire:click="selectRun('{{ $run['id'] }}')">
                                <td><x-mono>{{ strtoupper($run['id']) }}</x-mono></td>
                                <td>
                                    <x-mono>{{ \Carbon\Carbon::parse($run['windowFrom'])->format('d M H:i') }}</x-mono>
                                    <div><x-mono>{{ \Carbon\Carbon::parse($run['windowTo'])->format('d M H:i') }}</x-mono></div>
                                </td>
                                <td><x-mono>{{ number_format($run['transactionsChecked']) }}</x-mono></td>
                                <td><x-mono>{{ number_format($run['walletsChecked']) }}</x-mono></td>
                                <td><x-mono>{{ number_format($run['mismatchCount']) }}</x-mono></td>
                                <td><x-mono>{{ $run['completedAt'] ? \Carbon\Carbon::parse($run['completedAt'])->format('d M, H:i') : '-' }}</x-mono></td>
                                <td><x-badge :status="$run['status']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No reconciliation runs found</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination"><span class="pagination-info">{{ count($runs) }} runs</span></div>
        @endif
    </div>

    @if($selectedIncident)
        <div class="drawer-overlay" wire:click="closeDrawer"></div>
        <div class="drawer">
            <div class="drawer-header">
                <span class="drawer-title">Incident {{ strtoupper($selectedIncident['id']) }}</span>
                <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
            </div>
            <div class="drawer-body">
                <div class="mb-4 flex gap-2">
                    <x-badge :status="$selectedIncident['status']" />
                    <span class="text-xs font-semibold text-[var(--text-secondary)]">{{ $this->enumLabel($selectedIncident['incidentType']) }}</span>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Incident</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selectedIncident['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Run ID</span><span class="drawer-field-value">{{ $selectedIncident['runId'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Description</span><span class="drawer-field-value !font-sans">{{ $selectedIncident['description'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Discrepancy</span><span class="drawer-field-value"><x-amount :value="$selectedIncident['discrepancyAmount']" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Suspense Entry</span><span class="drawer-field-value">{{ $selectedIncident['suspenseEntryRef'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Opened</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedIncident['openedAt'])->format('d M Y, H:i') }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Resolution</div>
                    <div class="drawer-field"><span class="drawer-field-label">Investigated By</span><span class="drawer-field-value">{{ $selectedIncident['investigatedBy'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Investigated At</span><span class="drawer-field-value">{{ $selectedIncident['investigatedAt'] ? \Carbon\Carbon::parse($selectedIncident['investigatedAt'])->format('d M Y, H:i') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Resolved By</span><span class="drawer-field-value">{{ $selectedIncident['resolvedBy'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Resolved At</span><span class="drawer-field-value">{{ $selectedIncident['resolvedAt'] ? \Carbon\Carbon::parse($selectedIncident['resolvedAt'])->format('d M Y, H:i') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Resolution Note</span><span class="drawer-field-value !font-sans">{{ $selectedIncident['resolutionNote'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Closed By</span><span class="drawer-field-value">{{ $selectedIncident['closedBy'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Closed At</span><span class="drawer-field-value">{{ $selectedIncident['closedAt'] ? \Carbon\Carbon::parse($selectedIncident['closedAt'])->format('d M Y, H:i') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Closure Note</span><span class="drawer-field-value !font-sans">{{ $selectedIncident['closureNote'] ?? '-' }}</span></div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($selectedIncident['status'] === 'OPEN')
                    <button class="btn btn-secondary btn-sm" wire:click="investigateIncident">Investigate</button>
                @endif
                @if(in_array($selectedIncident['status'], ['OPEN', 'UNDER_INVESTIGATION']))
                    <button class="btn btn-primary btn-sm" wire:click="openResolveModal">Resolve</button>
                @endif
                @if($selectedIncident['status'] === 'RESOLVED')
                    <button class="btn btn-primary btn-sm" wire:click="openCloseIncidentModal">Close</button>
                @endif
            </div>
        </div>
    @endif

    @if($selectedRun)
        <div class="drawer-overlay" wire:click="closeDrawer"></div>
        <div class="drawer">
            <div class="drawer-header">
                <span class="drawer-title">Run {{ strtoupper($selectedRun['id']) }}</span>
                <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
            </div>
            <div class="drawer-body">
                <div class="mb-4"><x-badge :status="$selectedRun['status']" /></div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Run</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selectedRun['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Started</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedRun['startedAt'])->format('d M Y, H:i') }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Completed</span><span class="drawer-field-value">{{ $selectedRun['completedAt'] ? \Carbon\Carbon::parse($selectedRun['completedAt'])->format('d M Y, H:i') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Window From</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedRun['windowFrom'])->format('d M Y, H:i') }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Window To</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedRun['windowTo'])->format('d M Y, H:i') }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Checks</div>
                    <div class="drawer-field"><span class="drawer-field-label">Transactions</span><span class="drawer-field-value">{{ number_format($selectedRun['transactionsChecked']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Wallets</span><span class="drawer-field-value">{{ number_format($selectedRun['walletsChecked']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Mismatches</span><span class="drawer-field-value">{{ number_format($selectedRun['mismatchCount']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Source</span><span class="drawer-field-value">{{ $selectedRun['details']['source'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Ledger Difference</span><span class="drawer-field-value"><x-amount :value="$selectedRun['details']['ledgerDifference'] ?? 0" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Wallet Difference</span><span class="drawer-field-value"><x-amount :value="$selectedRun['details']['walletDifference'] ?? 0" size="12" /></span></div>
                </div>
            </div>
        </div>
    @endif

    @if($showResolveModal)
        <div class="modal-overlay" wire:click.self="$set('showResolveModal', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">Resolve Incident</span>
                    <button class="modal-close" wire:click="$set('showResolveModal', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="form-label">Note <span class="form-required">*</span></label>
                            <textarea wire:model="resolveForm.note" rows="3" class="form-textarea"></textarea>
                        </div>
                        <div>
                            <label class="form-label">Suspense Adjustment Amount <span class="form-required">*</span></label>
                            <input wire:model="resolveForm.suspenseAdjustmentAmount" type="number" min="0" class="form-input is-mono" />
                        </div>
                        <div>
                            <label class="form-label">Suspense Direction</label>
                            <select wire:model="resolveForm.suspenseDirection" class="form-select">
                                <option value="">None</option>
                                @foreach($suspenseDirections as $direction)
                                    <option value="{{ $direction }}">{{ $this->enumLabel($direction) }}</option>
                                @endforeach
                            </select>
                            @error('resolveForm.suspenseDirection') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('showResolveModal', false)">Cancel</button>
                    <button class="btn btn-primary btn-md" wire:click="resolveIncident">Resolve</button>
                </div>
            </div>
        </div>
    @endif

    @if($showCloseIncidentModal)
        <div class="modal-overlay" wire:click.self="$set('showCloseIncidentModal', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">Close Incident</span>
                    <button class="modal-close" wire:click="$set('showCloseIncidentModal', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="form-label">Note <span class="form-required">*</span></label>
                            <textarea wire:model="closeForm.note" rows="3" class="form-textarea"></textarea>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-xs">
                            <input wire:model="closeForm.clearSuspense" type="checkbox" />
                            <span>Clear suspense</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('showCloseIncidentModal', false)">Cancel</button>
                    <button class="btn btn-primary btn-md" wire:click="closeIncident">Close</button>
                </div>
            </div>
        </div>
    @endif
</div>
