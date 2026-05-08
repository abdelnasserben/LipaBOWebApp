<?php

use Livewire\Component;
use App\Livewire\Concerns\UsesBackofficeEnums;
use App\Services\Api\UsesBackofficeApi;

new class extends Component {
    use UsesBackofficeApi;
    use UsesBackofficeEnums;

    public array $stats = [];
    public string $period = 'today';

    public function mount(): void
    {
        $this->stats = $this->api()->dashboardStats();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.dashboard');
    }
};
?>

<div>
    <x-page-header
        title="Dashboard"
        subtitle="Snapshot of today's activity across the platform"
    />

    {{-- Pending Approvals | Reconciliation incidents Alerts --}}
    @if ($stats['pendingApprovals'] > 0)
        <div class="alert alert-warning mb-5">
            <x-icon name="alert-triangle" size="15" />
            <div>
                <strong>{{ $stats['pendingApprovals'] }} approval{{ $stats['pendingApprovals'] > 1 ? 's' : '' }}
                    awaiting action</strong>
                — maker-checker requests require your review.
                <a href="{{ route('approvals') }}" class="ml-2 font-semibold text-[inherit] underline">Review now →</a>
            </div>
        </div>
    @endif

    @if ($stats['openReconciliation'] > 0)
        <div class="alert alert-danger mb-5">
            <x-icon name="shield-alert" size="15" />
            <div>
                <strong>{{ $stats['openReconciliation'] }} reconciliation incident{{ $stats['openReconciliation'] > 1 ? 's' : '' }}
                    currently open</strong>
                — discrepancies require operational investigation.
                <a href="{{ route('reconciliation') }}" class="ml-2 font-semibold text-[inherit] underline">Investigate →</a>
            </div>
        </div>
    @endif

    {{-- KPI Grid --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="kpi-card">
            <div class="kpi-label">Customers</div>
            <div class="kpi-value">{{ number_format($stats['totalCustomers']) }}</div>
            <div class="kpi-sub">Total registered</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Active Agents</div>
            <div class="kpi-value">{{ number_format($stats['activeAgents']) }}</div>
            <div class="kpi-sub">Float operators</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Active Merchants</div>
            <div class="kpi-value">{{ number_format($stats['activeMerchants']) }}</div>
            <div class="kpi-sub">Accepting payments</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Transactions Today</div>
            <div class="kpi-value">{{ number_format($stats['transactionsToday']) }}</div>
            <div class="kpi-sub"><x-amount :value="$stats['volumeToday']" /></div>
        </div>
    </div>

    {{-- Two-column: Tx Volume + Recent Transactions --}}
    <div class="mt-5 grid grid-cols-[1fr_1.6fr] items-start gap-4">

        {{-- Transaction Volume Breakdown --}}
        <div class="card">
            <div class="card-header">
                <x-icon name="chart" size="14" class="text-[var(--text-secondary)]" />
                <span class="card-title">Today's Volume by Type</span>
            </div>
            <div class="card-body">
                @foreach ($stats['txByType'] as $row)
                    @php
                        $pct = $stats['volumeToday'] > 0 ? round(($row['amount'] / $stats['volumeToday']) * 100) : 0;
                        $colors = [
                            'CASH_IN' => 'bg-[var(--green)]',
                            'CASH_OUT' => 'bg-[var(--amber)]',
                            'PAYMENT' => 'bg-[var(--blue)]',
                            'P2P_TRANSFER' => 'bg-[var(--purple)]',
                            'SERVICE_PAYMENT' => 'bg-[var(--teal)]',
                        ];
                        $colorClass = $colors[$row['type']] ?? 'bg-[var(--accent)]';
                    @endphp
                    <div class="mb-3.5">
                        <div class="mb-[5px] flex items-center justify-between">
                            <span
                                class="text-xs font-medium text-[var(--text-secondary)]">{{ $this->enumLabel($row['type']) }}</span>
                            <div class="flex gap-3">
                                <span class="text-mono text-xs">{{ number_format($row['count']) }} txns</span>
                                <x-amount :value="$row['amount']" size="12" />
                            </div>
                        </div>
                        <div class="h-1 overflow-hidden rounded bg-[var(--border-color)]">
                            <div
                                class="h-full rounded {{ $colorClass }}"
                                style="width:{{ $pct }}%;">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Recent Transactions --}}
        <div class="card">
            <div class="card-header justify-between">
                <div class="flex items-center gap-2.5">
                    <x-icon name="arrows" size="14" class="text-[var(--text-secondary)]" />
                    <span class="card-title">Recent Transactions</span>
                </div>
                <a href="{{ route('transactions') }}" class="btn btn-ghost btn-sm">View all →</a>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stats['recentTransactions'] as $tx)
                            <tr>
                                <td>
                                    <span class="text-xs font-medium">{{ $this->enumLabel($tx['type']) }}</span>
                                </td>
                                <td><x-amount :value="$tx['requestedAmount']" size="12" /></td>
                                <td><x-badge :status="$tx['status']" /></td>
                                <td class="text-mono text-xs">{{ \Carbon\Carbon::parse($tx['createdAt'])->format('H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
