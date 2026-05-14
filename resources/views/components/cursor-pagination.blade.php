@props(['paginator'])

<div class="pagination">
    <span class="pagination-info">
        Page {{ $paginator['page'] ?? 1 }} &middot; showing {{ $paginator['shown'] ?? 0 }} {{ $paginator['label'] ?? 'items' }}
    </span>
    <div class="pagination-controls">
        <button
            class="pagination-btn"
            wire:click="previousCursorPage('{{ $paginator['key'] }}')"
            @disabled(!($paginator['hasPrevious'] ?? false))
        >&lsaquo;</button>
        <button class="pagination-btn active">{{ $paginator['page'] ?? 1 }}</button>
        <button
            class="pagination-btn"
            wire:click="nextCursorPage('{{ $paginator['key'] }}')"
            @disabled(!($paginator['hasMore'] ?? false))
        >&rsaquo;</button>
    </div>
</div>
