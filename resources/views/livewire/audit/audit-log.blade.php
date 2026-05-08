<?php

use Livewire\Component;
use App\Livewire\Concerns\UsesBackofficeEnums;
use App\Services\Api\UsesBackofficeApi;

new class extends Component
{
    use UsesBackofficeApi;
    use UsesBackofficeEnums;

    public string $eventTypeFilter = '';
    public string $actorIdFilter = '';
    public string $fromFilter = '';
    public string $toFilter = '';
    public string $correlationIdFilter = '';
    public ?array $selected = null;

    public function selectRow(string $id): void
    {
        $events = $this->api()->auditEvents($this->filters());
        $this->selected = collect($events)->firstWhere('id', $id);
    }
    public function closeDrawer(): void { $this->selected = null; }

    public function resetFilters(): void
    {
        $this->eventTypeFilter = '';
        $this->actorIdFilter = '';
        $this->fromFilter = '';
        $this->toFilter = '';
        $this->correlationIdFilter = '';
        $this->selected = null;
    }

    private function eventTypes(array $events): array
    {
        return collect($events)
            ->pluck('eventType')
            ->filter(fn ($eventType): bool => is_string($eventType) && $eventType !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function filters(): array
    {
        return [
            'eventType' => $this->eventTypeFilter ?: null,
            'actorId' => trim($this->actorIdFilter) ?: null,
            'from' => $this->dateFilterToInstant($this->fromFilter, '00:00:00'),
            'to' => $this->dateFilterToInstant($this->toFilter, '23:59:59'),
            'correlationId' => trim($this->correlationIdFilter) ?: null,
        ];
    }

    private function filtersWithoutEventType(array $filters): array
    {
        unset($filters['eventType']);

        return $filters;
    }

    private function dateFilterToInstant(string $value, string $time): ?string
    {
        $value = trim($value);

        return $value === '' ? null : "{$value}T{$time}Z";
    }

    public function render(): \Illuminate\View\View
    {
        $filters = $this->filters();
        $all = $this->api()->auditEvents($filters);
        $eventTypeRows = $this->eventTypeFilter === ''
            ? $all
            : $this->api()->auditEvents($this->filtersWithoutEventType($filters));

        return view('livewire.audit.audit-log', [
            'rows' => $all,
            'total' => count($all),
            'eventTypes' => $this->eventTypes($eventTypeRows),
        ]);
    }
};
?>

<div>
    <x-page-header
        title="Audit Log"
        subtitle="Immutable record of backoffice and system actions"
    />

    <div class="card">
        <div class="filter-bar">
            <select wire:model.live="eventTypeFilter" class="filter-select">
                <option value="">All event types</option>
                @foreach($eventTypes as $eventType)
                    <option value="{{ $eventType }}">{{ $this->enumLabel($eventType) }}</option>
                @endforeach
            </select>
            <input wire:model.live.debounce.300ms="actorIdFilter" type="text" class="filter-select !cursor-text is-mono" placeholder="Actor ID" />
            <input wire:model.live.debounce.500ms="fromFilter" type="date" class="filter-select" aria-label="From date" />
            <input wire:model.live.debounce.500ms="toFilter" type="date" class="filter-select" aria-label="To date" />
            <input wire:model.live.debounce.300ms="correlationIdFilter" type="text" class="filter-select !cursor-text is-mono" placeholder="Correlation ID" />
            <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">
                <x-icon name="x" size="14" /> Clear
            </button>
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
                                {{ $this->enumLabel($row['eventType']) }}
                            </span>
                        </td>
                        <td>
                            <x-mono>{{ isset($row['actorType']) ? $this->enumLabel($row['actorType']) : '—' }}</x-mono><br/>
                            <x-mono>{{ Str::limit($row['actorId'] ?? '—', 16) }}</x-mono>
                        </td>
                        <td>
                            <x-mono>{{ isset($row['targetEntityType']) ? $this->enumLabel($row['targetEntityType']) : '—' }}</x-mono>
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
                {{ $this->enumLabel($selected['eventType']) }}
            </div>
            <div class="drawer-section">
                <div class="drawer-field"><span class="drawer-field-label">Event ID</span><span class="drawer-field-value">{{ $selected['id'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Actor Type</span><span class="drawer-field-value">{{ isset($selected['actorType']) ? $this->enumLabel($selected['actorType']) : '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Actor ID</span><span class="drawer-field-value">{{ $selected['actorId'] ?? '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Target Type</span><span class="drawer-field-value">{{ isset($selected['targetEntityType']) ? $this->enumLabel($selected['targetEntityType']) : '—' }}</span></div>
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
