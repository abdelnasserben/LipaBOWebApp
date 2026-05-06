<?php

use Livewire\Component;
use App\Services\Mock\MockDataService;

new class extends Component
{
    public array $stats = [];
    public string $period = 'today';

    public function mount(): void
    {
        $this->stats = MockDataService::dashboardStats();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.dashboard');
    }
};
?>

<div>
    {{-- Pending Approvals Alert --}}
    @if($stats['pendingApprovals'] > 0)
    <div class="alert alert-warning" style="margin-bottom:20px;">
        <x-icon name="alert-triangle" size="15" style="flex-shrink:0;" />
        <div>
            <strong>{{ $stats['pendingApprovals'] }} approval{{ $stats['pendingApprovals'] > 1 ? 's' : '' }} awaiting action</strong>
            — maker-checker requests require your review.
            <a href="{{ route('approvals') }}" style="color:inherit;font-weight:600;margin-left:8px;text-decoration:underline;">Review now →</a>
        </div>
    </div>
    @endif

    {{-- KPI Grid --}}
    <div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
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
        <div class="kpi-card">
            <div class="kpi-label">Pending Approvals</div>
            <div class="kpi-value" style="color:{{ $stats['pendingApprovals'] > 0 ? 'var(--amber)' : 'var(--text-primary)' }};">
                {{ $stats['pendingApprovals'] }}
            </div>
            <div class="kpi-sub">Awaiting checker</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Open Reconciliation</div>
            <div class="kpi-value" style="color:{{ $stats['openReconciliation'] > 0 ? 'var(--red)' : 'var(--text-primary)' }};">
                {{ $stats['openReconciliation'] }}
            </div>
            <div class="kpi-sub">Incidents open</div>
        </div>
    </div>

    {{-- Two-column: Tx Volume + Recent Transactions --}}
    <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:16px;align-items:start;">

        {{-- Transaction Volume Breakdown --}}
        <div class="card">
            <div class="card-header">
                <x-icon name="chart" size="14" style="color:var(--text-secondary);" />
                <span class="card-title">Today's Volume by Type</span>
            </div>
            <div class="card-body">
                @foreach($stats['txByType'] as $row)
                @php
                    $pct = $stats['volumeToday'] > 0
                        ? round(($row['amount'] / $stats['volumeToday']) * 100)
                        : 0;
                    $colors = [
                        'CASH_IN' => 'var(--green)',
                        'CASH_OUT' => 'var(--amber)',
                        'PAYMENT' => 'var(--blue)',
                        'P2P_TRANSFER' => 'var(--purple)',
                        'SERVICE_PAYMENT' => 'var(--teal)',
                    ];
                    $color = $colors[$row['type']] ?? 'var(--accent)';
                @endphp
                <div style="margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                        <span style="font-size:12px;font-weight:500;color:var(--text-secondary);">{{ str_replace('_', ' ', $row['type']) }}</span>
                        <div style="display:flex;gap:12px;">
                            <span class="mono">{{ number_format($row['count']) }} txns</span>
                            <x-amount :value="$row['amount']" size="12" />
                        </div>
                    </div>
                    <div style="height:4px;background:var(--border-color);border-radius:4px;overflow:hidden;">
                        <div style="height:100%;width:{{ $pct }}%;background:{{ $color }};border-radius:4px;"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Recent Transactions --}}
        <div class="card">
            <div class="card-header" style="justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <x-icon name="arrows" size="14" style="color:var(--text-secondary);" />
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
                        @foreach($stats['recentTransactions'] as $tx)
                        <tr>
                            <td>
                                <span style="font-size:12px;font-weight:500;">{{ str_replace('_', ' ', $tx['type']) }}</span>
                            </td>
                            <td><x-amount :value="$tx['requestedAmount']" size="12" /></td>
                            <td><x-badge :status="$tx['status']" /></td>
                            <td class="mono">{{ \Carbon\Carbon::parse($tx['createdAt'])->format('H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
