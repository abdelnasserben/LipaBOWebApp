<?php

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Services\Mock\MockDataService;

new class extends Component
{
    #[Url(as: 'tab')]
    public string $tab = 'transactions';

    // Transactions summary filters
    public string $txFrom = '2026-04-01';
    public string $txTo = '2026-05-06';
    public string $txGroupBy = 'MONTH';
    public string $txTypeFilter = '';

    // AML filters
    public string $amlFrom = '2026-05-01';
    public string $amlTo = '2026-05-06';
    public int $amlThreshold = 500000;

    // Exports
    public string $exportTypeFilter = '';
    public ?array $selectedExport = null;

    public bool $showExportModal = false;
    public string $notification = '';

    public array $newExport = [
        'reportType' => 'TRANSACTIONS_SUMMARY',
        'periodFrom' => '',
        'periodTo' => '',
        'recordCount' => 0,
    ];

    public array $groupByOptions = ['DAY', 'WEEK', 'MONTH', 'YEAR'];
    public array $transactionTypes = ['CASH_IN', 'CASH_OUT', 'PAYMENT', 'P2P_TRANSFER', 'SERVICE_PAYMENT', 'CARD_SALE'];
    public array $reportTypes = ['TRANSACTIONS_SUMMARY', 'KYC_SUMMARY', 'AML_LARGE_TRANSACTIONS', 'FLOAT', 'ACTORS_SUMMARY'];

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->selectedExport = null;
        $this->showExportModal = false;
    }

    public function selectExport(string $id): void
    {
        $this->selectedExport = MockDataService::reportExport($id);
    }

    public function closeDrawer(): void
    {
        $this->selectedExport = null;
    }

    public function openExportModal(): void
    {
        $this->newExport = [
            'reportType' => $this->defaultExportType(),
            'periodFrom' => '',
            'periodTo' => '',
            'recordCount' => 0,
        ];
        $this->showExportModal = true;
    }

    public function submitExport(): void
    {
        $this->validate([
            'newExport.reportType' => 'required|in:' . implode(',', $this->reportTypes),
            'newExport.periodFrom' => 'nullable|date',
            'newExport.periodTo' => 'nullable|date|after_or_equal:newExport.periodFrom',
            'newExport.recordCount' => 'integer|min:0',
        ]);

        // Real: POST /api/v1/backoffice/reports/exports
        // Query: reportType (required), periodFrom?, periodTo?, recordCount=0.
        // Permission: REPORT_REGULATORY_EXPORT. Returns 200 ApiResponse<ReportExportResponse>.
        $this->notification = 'Report export record created.';
        $this->showExportModal = false;
    }

    public function downloadCsv(string $reportType): void
    {
        // Real: GET /api/v1/backoffice/reports/{path}?format=csv
        // Permission: REPORT_REGULATORY_EXPORT. Returns raw CSV (Content-Type: text/csv).
        // Each access writes a REGULATORY_REPORT_EXPORTED audit event.
        $this->notification = $this->enumLabel($reportType) . ' CSV download triggered.';
    }

    public function enumLabel(?string $value, string $fallback = '-'): string
    {
        return filled($value) ? str_replace('_', ' ', $value) : $fallback;
    }

    private function defaultExportType(): string
    {
        return match ($this->tab) {
            'kyc' => 'KYC_SUMMARY',
            'aml' => 'AML_LARGE_TRANSACTIONS',
            'float' => 'FLOAT',
            'actors' => 'ACTORS_SUMMARY',
            default => 'TRANSACTIONS_SUMMARY',
        };
    }

    public function render(): \Illuminate\View\View
    {
        $txReport = MockDataService::transactionSummaryReport([
            'from' => $this->txFrom ? $this->txFrom . 'T00:00:00Z' : null,
            'to' => $this->txTo ? $this->txTo . 'T23:59:59Z' : null,
            'groupBy' => $this->txGroupBy,
            'type' => $this->txTypeFilter ?: null,
        ]);

        $kyc = MockDataService::kycSummaryReport();
        $aml = MockDataService::amlLargeTransactions([
            'from' => $this->amlFrom ? $this->amlFrom . 'T00:00:00Z' : null,
            'to' => $this->amlTo ? $this->amlTo . 'T23:59:59Z' : null,
            'thresholdKmf' => $this->amlThreshold,
        ]);
        $float = MockDataService::floatReport();
        $actors = MockDataService::actorSummaryReport();
        $exports = MockDataService::reportExports([
            'reportType' => $this->exportTypeFilter ?: null,
        ]);

        return view('livewire.reports.reports-dashboard', [
            'txReport' => $txReport,
            'txTotalCount' => array_sum(array_map(fn($l) => $l['count'], $txReport['lines'])),
            'txTotalAmount' => array_sum(array_map(fn($l) => $l['totalAmountKmf'], $txReport['lines'])),
            'txTotalFees' => array_sum(array_map(fn($l) => $l['totalFeesKmf'], $txReport['lines'])),
            'txTotalCommissions' => array_sum(array_map(fn($l) => $l['totalCommissionsKmf'], $txReport['lines'])),
            'kyc' => $kyc,
            'kycTotal' => array_sum(array_map(fn($l) => $l['count'], $kyc['lines'])),
            'aml' => $aml,
            'amlTotalAmount' => array_sum(array_map(fn($r) => $r['requestedAmountKmf'], $aml)),
            'float' => $float,
            'actors' => $actors,
            'actorsTotal' => array_sum(array_map(fn($l) => $l['count'], $actors['lines'])),
            'exports' => $exports,
        ]);
    }
};
?>

<div>
    <x-page-header
        title="Reports"
        subtitle="Regulatory and operational reporting"
    />

    @if($notification)
        <div class="alert alert-success mb-4"><x-icon name="check" size="15" /> {{ $notification }}</div>
    @endif

    <div class="card">
        <div class="tabs">
            <button class="tab @if($tab==='transactions') active @endif" wire:click="setTab('transactions')">Transactions</button>
            <button class="tab @if($tab==='kyc') active @endif" wire:click="setTab('kyc')">KYC</button>
            <button class="tab @if($tab==='aml') active @endif" wire:click="setTab('aml')">AML</button>
            <button class="tab @if($tab==='float') active @endif" wire:click="setTab('float')">Float</button>
            <button class="tab @if($tab==='actors') active @endif" wire:click="setTab('actors')">Actors</button>
            <button class="tab @if($tab==='exports') active @endif" wire:click="setTab('exports')">Exports</button>
        </div>

        @if($tab === 'transactions')
            <div class="filter-bar">
                <input wire:model.live.debounce.500ms="txFrom" type="date" class="filter-select" />
                <input wire:model.live.debounce.500ms="txTo" type="date" class="filter-select" />
                <select wire:model.live="txGroupBy" class="filter-select">
                    @foreach($groupByOptions as $opt)
                        <option value="{{ $opt }}">{{ $this->enumLabel($opt) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="txTypeFilter" class="filter-select">
                    <option value="">All types</option>
                    @foreach($transactionTypes as $type)
                        <option value="{{ $type }}">{{ $this->enumLabel($type) }}</option>
                    @endforeach
                </select>
                <div class="flex-1"></div>
                <button class="btn btn-secondary btn-sm" wire:click="downloadCsv('TRANSACTIONS_SUMMARY')">
                    <x-icon name="download" size="13" /> Export CSV
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3 border-b border-[var(--border-color)] p-5 md:grid-cols-4">
                <div>
                    <div class="kpi-label">Transactions</div>
                    <div class="kpi-value">{{ number_format($txTotalCount) }}</div>
                </div>
                <div>
                    <div class="kpi-label">Total Amount</div>
                    <x-amount :value="$txTotalAmount" size="20" />
                </div>
                <div>
                    <div class="kpi-label">Total Fees</div>
                    <x-amount :value="$txTotalFees" size="20" />
                </div>
                <div>
                    <div class="kpi-label">Total Commissions</div>
                    <x-amount :value="$txTotalCommissions" size="20" />
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Period</th>
                            <th>Count</th>
                            <th>Amount</th>
                            <th>Fees</th>
                            <th>Commissions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($txReport['lines'] as $line)
                            <tr>
                                <td><span class="text-xs font-medium">{{ $this->enumLabel($line['type']) }}</span></td>
                                <td><x-mono>{{ \Carbon\Carbon::parse($line['period'])->format('d M Y') }}</x-mono></td>
                                <td><x-mono>{{ number_format($line['count']) }}</x-mono></td>
                                <td><x-amount :value="$line['totalAmountKmf']" size="12" /></td>
                                <td><x-amount :value="$line['totalFeesKmf']" size="12" /></td>
                                <td><x-amount :value="$line['totalCommissionsKmf']" size="12" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="empty-state"><div class="empty-state-title">No transaction summary lines</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <span class="pagination-info">{{ count($txReport['lines']) }} lines • grouped by {{ $this->enumLabel($txReport['groupBy']) }}</span>
            </div>

        @elseif($tab === 'kyc')
            <div class="filter-bar">
                <div class="text-xs text-[var(--text-secondary)]">Snapshot of KYC distribution across all actor types.</div>
                <div class="flex-1"></div>
                <button class="btn btn-secondary btn-sm" wire:click="downloadCsv('KYC_SUMMARY')">
                    <x-icon name="download" size="13" /> Export CSV
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3 border-b border-[var(--border-color)] p-5 md:grid-cols-2">
                <div>
                    <div class="kpi-label">Lines</div>
                    <div class="kpi-value">{{ number_format(count($kyc['lines'])) }}</div>
                </div>
                <div>
                    <div class="kpi-label">Total Actors</div>
                    <div class="kpi-value">{{ number_format($kycTotal) }}</div>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Actor Type</th>
                            <th>KYC Level</th>
                            <th>Status</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kyc['lines'] as $line)
                            <tr>
                                <td><span class="text-xs font-medium">{{ $this->enumLabel($line['actorType']) }}</span></td>
                                <td><x-badge :status="$line['kycLevel']" /></td>
                                <td><x-badge :status="$line['status']" /></td>
                                <td><x-mono>{{ number_format($line['count']) }}</x-mono></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @elseif($tab === 'aml')
            <div class="filter-bar">
                <input wire:model.live.debounce.500ms="amlFrom" type="date" class="filter-select" />
                <input wire:model.live.debounce.500ms="amlTo" type="date" class="filter-select" />
                <input wire:model.live.debounce.500ms="amlThreshold" type="number" min="0" class="filter-select is-mono" placeholder="Threshold (KMF)" />
                <div class="flex-1"></div>
                <span class="text-xs text-[var(--text-secondary)]">{{ count($aml) }} flagged</span>
            </div>

            <div class="grid grid-cols-1 gap-3 border-b border-[var(--border-color)] p-5 md:grid-cols-3">
                <div>
                    <div class="kpi-label">Threshold</div>
                    <x-amount :value="$amlThreshold" size="20" />
                </div>
                <div>
                    <div class="kpi-label">Flagged</div>
                    <div class="kpi-value">{{ number_format(count($aml)) }}</div>
                </div>
                <div>
                    <div class="kpi-label">Total Amount</div>
                    <x-amount :value="$amlTotalAmount" size="20" />
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Transaction</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Fee</th>
                            <th>Source / Destination</th>
                            <th>Initiator</th>
                            <th>Channel</th>
                            <th>Created</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($aml as $row)
                            <tr>
                                <td><x-mono>{{ strtoupper($row['id']) }}</x-mono></td>
                                <td><span class="text-xs font-medium">{{ $this->enumLabel($row['type']) }}</span></td>
                                <td><x-amount :value="$row['requestedAmountKmf']" size="12" /></td>
                                <td><x-amount :value="$row['feeAmountKmf']" size="12" /></td>
                                <td>
                                    <x-mono>{{ $row['sourceWalletId'] }}</x-mono>
                                    <div><x-mono>→ {{ $row['destinationWalletId'] }}</x-mono></div>
                                </td>
                                <td>
                                    <span class="text-xs font-medium">{{ $this->enumLabel($row['initiatorType']) }}</span>
                                    <div><x-mono>{{ \Illuminate\Support\Str::limit($row['initiatorId'], 16) }}</x-mono></div>
                                </td>
                                <td><span class="text-xs">{{ $this->enumLabel($row['channelType']) }}</span></td>
                                <td><x-mono>{{ \Carbon\Carbon::parse($row['createdAt'])->format('d M, H:i') }}</x-mono></td>
                                <td><x-badge :status="$row['status']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><div class="empty-state"><div class="empty-state-title">No transactions above threshold</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif($tab === 'float')
            <div class="filter-bar">
                <div class="text-xs text-[var(--text-secondary)]">Generated {{ \Carbon\Carbon::parse($float['generatedAt'])->format('d M Y, H:i') }}</div>
                <div class="flex-1"></div>
                @if($float['doubleEntryIntegrityOk'])
                    <span class="badge badge-active">Ledger OK</span>
                @else
                    <span class="badge badge-mismatch">Ledger Mismatch</span>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-3 border-b border-[var(--border-color)] p-5 md:grid-cols-3">
                <div>
                    <div class="kpi-label">Actor Total</div>
                    <x-amount :value="$float['actorTotalBalanceKmf']" size="24" />
                </div>
                <div>
                    <div class="kpi-label">System Float</div>
                    <x-amount :value="$float['systemFloatBalanceKmf']" size="24" />
                </div>
                <div>
                    <div class="kpi-label">Float Discrepancy</div>
                    <x-amount :value="$float['floatDiscrepancy']" size="24" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
                <div class="rounded-md border border-[var(--border-color)] p-4">
                    <div class="drawer-section-title">Actor Balances</div>
                    <div class="drawer-field"><span class="drawer-field-label">Customers</span><span class="drawer-field-value"><x-amount :value="$float['customerTotalBalanceKmf']" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Merchants</span><span class="drawer-field-value"><x-amount :value="$float['merchantTotalBalanceKmf']" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Agents</span><span class="drawer-field-value"><x-amount :value="$float['agentTotalBalanceKmf']" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Actor Total</span><span class="drawer-field-value"><x-amount :value="$float['actorTotalBalanceKmf']" size="12" /></span></div>
                </div>
                <div class="rounded-md border border-[var(--border-color)] p-4">
                    <div class="drawer-section-title">System Wallets</div>
                    <div class="drawer-field"><span class="drawer-field-label">Float</span><span class="drawer-field-value"><x-amount :value="$float['systemFloatBalanceKmf']" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Liquidity</span><span class="drawer-field-value"><x-amount :value="$float['systemLiquidityBalanceKmf']" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Revenue</span><span class="drawer-field-value"><x-amount :value="$float['systemRevenueBalanceKmf']" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Commissions</span><span class="drawer-field-value"><x-amount :value="$float['systemCommissionsBalanceKmf']" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Suspense</span><span class="drawer-field-value"><x-amount :value="$float['systemSuspenseBalanceKmf']" size="12" /></span></div>
                </div>
                <div class="rounded-md border border-[var(--border-color)] p-4 md:col-span-2">
                    <div class="drawer-section-title">Ledger Integrity</div>
                    <div class="drawer-field"><span class="drawer-field-label">Total Debit</span><span class="drawer-field-value"><x-amount :value="$float['ledgerTotalDebitKmf']" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Total Credit</span><span class="drawer-field-value"><x-amount :value="$float['ledgerTotalCreditKmf']" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Double-entry</span><span class="drawer-field-value">{{ $float['doubleEntryIntegrityOk'] ? 'OK' : 'BREACH' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Float Discrepancy</span><span class="drawer-field-value"><x-amount :value="$float['floatDiscrepancy']" size="12" /></span></div>
                </div>
            </div>

        @elseif($tab === 'actors')
            <div class="filter-bar">
                <div class="text-xs text-[var(--text-secondary)]">Distribution of actors by type and status.</div>
                <div class="flex-1"></div>
                <button class="btn btn-secondary btn-sm" wire:click="downloadCsv('ACTORS_SUMMARY')">
                    <x-icon name="download" size="13" /> Export CSV
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3 border-b border-[var(--border-color)] p-5 md:grid-cols-2">
                <div>
                    <div class="kpi-label">Lines</div>
                    <div class="kpi-value">{{ number_format(count($actors['lines'])) }}</div>
                </div>
                <div>
                    <div class="kpi-label">Total Actors</div>
                    <div class="kpi-value">{{ number_format($actorsTotal) }}</div>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Actor Type</th>
                            <th>Status</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($actors['lines'] as $line)
                            <tr>
                                <td><span class="text-xs font-medium">{{ $this->enumLabel($line['actorType']) }}</span></td>
                                <td><x-badge :status="$line['status']" /></td>
                                <td><x-mono>{{ number_format($line['count']) }}</x-mono></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @else
            <div class="filter-bar">
                <select wire:model.live="exportTypeFilter" class="filter-select">
                    <option value="">All report types</option>
                    @foreach($reportTypes as $type)
                        <option value="{{ $type }}">{{ $this->enumLabel($type) }}</option>
                    @endforeach
                </select>
                <div class="flex-1"></div>
                <button class="btn btn-primary btn-sm" wire:click="openExportModal">
                    <x-icon name="plus" size="13" /> New Export
                </button>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Export</th>
                            <th>Report Type</th>
                            <th>Period</th>
                            <th>Records</th>
                            <th>Generated By</th>
                            <th>Generated At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($exports as $row)
                            <tr class="table-row-link" wire:click="selectExport('{{ $row['id'] }}')">
                                <td><x-mono>{{ strtoupper($row['id']) }}</x-mono></td>
                                <td><span class="text-xs font-medium">{{ $this->enumLabel($row['reportType']) }}</span></td>
                                <td>
                                    @if($row['periodFrom'])
                                        <x-mono>{{ \Carbon\Carbon::parse($row['periodFrom'])->format('d M Y') }} → {{ $row['periodTo'] ? \Carbon\Carbon::parse($row['periodTo'])->format('d M Y') : '-' }}</x-mono>
                                    @else
                                        <span class="text-xs text-[var(--text-secondary)]">—</span>
                                    @endif
                                </td>
                                <td><x-mono>{{ number_format($row['recordCount']) }}</x-mono></td>
                                <td><x-mono>{{ \Illuminate\Support\Str::limit($row['generatedByUserId'], 16) }}</x-mono></td>
                                <td><x-mono>{{ \Carbon\Carbon::parse($row['generatedAt'])->format('d M, H:i') }}</x-mono></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="empty-state"><div class="empty-state-title">No export records</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination"><span class="pagination-info">{{ count($exports) }} exports</span></div>
        @endif
    </div>

    @if($selectedExport)
        <div class="drawer-overlay" wire:click="closeDrawer"></div>
        <div class="drawer">
            <div class="drawer-header">
                <span class="drawer-title">Export {{ strtoupper($selectedExport['id']) }}</span>
                <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
            </div>
            <div class="drawer-body">
                <div class="mb-4">
                    <span class="text-xs font-semibold text-[var(--text-secondary)]">{{ $this->enumLabel($selectedExport['reportType']) }}</span>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Export</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selectedExport['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Report Type</span><span class="drawer-field-value">{{ $this->enumLabel($selectedExport['reportType']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Period From</span><span class="drawer-field-value">{{ $selectedExport['periodFrom'] ? \Carbon\Carbon::parse($selectedExport['periodFrom'])->format('d M Y, H:i') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Period To</span><span class="drawer-field-value">{{ $selectedExport['periodTo'] ? \Carbon\Carbon::parse($selectedExport['periodTo'])->format('d M Y, H:i') : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Records</span><span class="drawer-field-value">{{ number_format($selectedExport['recordCount']) }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Generated By</span><span class="drawer-field-value">{{ $selectedExport['generatedByUserId'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Generated At</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedExport['generatedAt'])->format('d M Y, H:i') }}</span></div>
                </div>
            </div>
        </div>
    @endif

    @if($showExportModal)
        <div class="modal-overlay" wire:click.self="$set('showExportModal', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">New Report Export</span>
                    <button class="modal-close" wire:click="$set('showExportModal', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="form-label">Report Type <span class="form-required">*</span></label>
                            <select wire:model="newExport.reportType" class="form-select">
                                @foreach($reportTypes as $type)
                                    <option value="{{ $type }}">{{ $this->enumLabel($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Period From</label>
                            <input wire:model="newExport.periodFrom" type="date" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Period To</label>
                            <input wire:model="newExport.periodTo" type="date" class="form-input" />
                            @error('newExport.periodTo') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Record Count</label>
                            <input wire:model="newExport.recordCount" type="number" min="0" class="form-input is-mono" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('showExportModal', false)">Cancel</button>
                    <button class="btn btn-primary btn-md" wire:click="submitExport">Create Export</button>
                </div>
            </div>
        </div>
    @endif
</div>
