<?php

use Livewire\Component;
use App\Services\Mock\MockDataService;

new class extends Component
{
    public string $eventTypeFilter = '';
    public ?array $selected = null;

    public function selectRow(string $id): void
    {
        $events = MockDataService::auditEvents();
        $this->selected = collect($events)->firstWhere('id', $id);
    }
    public function closeDrawer(): void { $this->selected = null; }

    public function render(): \Illuminate\View\View
    {
        $all = MockDataService::auditEvents([
            'eventType' => $this->eventTypeFilter ?: null,
        ]);
        return view('livewire.audit.audit-log', ['rows' => $all, 'total' => count($all)]);
    }
};
?>

<div>
    <div class="card">
        <div class="filter-bar">
            <select wire:model.live="eventTypeFilter" class="filter-select">
                <option value="">All event types</option>
                <option value="BACKOFFICE_LOGIN">BACKOFFICE LOGIN</option>
                <option value="CUSTOMER_SUSPENDED">CUSTOMER SUSPENDED</option>
                <option value="APPROVAL_CREATED">APPROVAL CREATED</option>
                <option value="APPROVAL_APPROVED">APPROVAL APPROVED</option>
                <option value="KYC_APPROVED">KYC APPROVED</option>
                <option value="FEE_RULE_CREATED">FEE RULE CREATED</option>
                <option value="REGULATORY_REPORT_EXPORTED">REGULATORY REPORT EXPORTED</option>
                <option value="WALLET_FROZEN">WALLET FROZEN</option>
            </select>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Actor</th>
                        <th>Target</th>
                        <th>IP Address</th>
                        <th>Correlation ID</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr class="table-row-link" wire:click="selectRow('{{ $row['id'] }}')">
                        <td>
                            <span class="text-mono text-xs font-semibold text-[var(--text-primary)]">
                                {{ str_replace('_', ' ', $row['eventType']) }}
                            </span>
                        </td>
                        <td>
                            <x-mono>{{ isset($row['actorType']) ? str_replace('_', ' ', $row['actorType']) : '—' }}</x-mono><br/>
                            <x-mono>{{ Str::limit($row['actorId'] ?? '—', 16) }}</x-mono>
                        </td>
                        <td>
                            <x-mono>{{ isset($row['targetEntityType']) ? str_replace('_', ' ', $row['targetEntityType']) : '—' }}</x-mono>
                        </td>
                        <td><x-mono>{{ $row['ipAddress'] ?? '—' }}</x-mono></td>
                        <td><x-mono>{{ $row['correlationId'] ?? '—' }}</x-mono></td>
                        <td><x-mono>{{ \Carbon\Carbon::parse($row['occurredAt'])->format('d M, H:i:s') }}</x-mono></td>
                    </tr>
                    @empty
                    <tr><td colspan="6"><div class="empty-state"><div class="empty-state-title">No audit events found</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination"><span class="pagination-info">{{ $total }} events • immutable audit trail</span></div>
    </div>

    @if($selected)
    <div class="drawer-overlay" wire:click="closeDrawer"></div>
    <div class="drawer">
        <div class="drawer-header">
            <span class="drawer-title">Audit Event</span>
            <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
        </div>
        <div class="drawer-body">
            <div class="text-mono mb-4 rounded-lg bg-[var(--bg)] p-3 text-xs font-bold text-[var(--text-primary)]">
                {{ str_replace('_', ' ', $selected['eventType']) }}
            </div>
            <div class="drawer-section">
                <div class="drawer-field"><span class="drawer-field-label">Event ID</span><span class="drawer-field-value">{{ $selected['id'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Actor Type</span><span class="drawer-field-value">{{ isset($selected['actorType']) ? str_replace('_', ' ', $selected['actorType']) : '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Actor ID</span><span class="drawer-field-value">{{ $selected['actorId'] ?? '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Target Type</span><span class="drawer-field-value">{{ isset($selected['targetEntityType']) ? str_replace('_', ' ', $selected['targetEntityType']) : '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Target ID</span><span class="drawer-field-value">{{ $selected['targetEntityId'] ?? '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">IP Address</span><span class="drawer-field-value">{{ $selected['ipAddress'] ?? '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">User Agent</span><span class="drawer-field-value !text-[11px]">{{ $selected['userAgent'] ?? '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Correlation ID</span><span class="drawer-field-value">{{ $selected['correlationId'] ?? '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Occurred At</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['occurredAt'])->format('d M Y, H:i:s') }}</span></div>
            </div>
            @if(isset($selected['payload']) && $selected['payload'])
            <div class="drawer-section">
                <div class="drawer-section-title">Payload</div>
                <pre class="text-mono overflow-x-auto whitespace-pre-wrap rounded-md bg-[var(--bg)] p-3 text-[11px]">{{ json_encode(json_decode($selected['payload']), JSON_PRETTY_PRINT) }}</pre>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
