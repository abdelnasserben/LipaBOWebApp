@props([
    'title',
    'message' => '',
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'actionStyle' => 'primary',
    'confirmAction',
    'cancelAction',
    'entityLabel' => null,
    'entityName' => null,
    'entityId' => null,
    'error' => null,
    'loadingTarget' => null,
])

@php
    $buttonClass = match ($actionStyle) {
        'danger' => 'btn-danger',
        'warning' => 'btn-warning',
        'secondary' => 'btn-secondary',
        default => 'btn-primary',
    };

    $loadingTarget = $loadingTarget ?: $confirmAction;
@endphp

<div class="modal-overlay">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="confirmation-modal-title">
        <div class="modal-header">
            <span id="confirmation-modal-title" class="modal-title">{{ $title }}</span>
            <button
                type="button"
                class="modal-close"
                wire:click="{{ $cancelAction }}"
                wire:loading.attr="disabled"
                wire:target="{{ $loadingTarget }}"
                aria-label="Cancel"
            >
                <x-icon name="x" size="18" />
            </button>
        </div>

        <div class="modal-body">
            @if($error)
                <div class="alert alert-danger mb-4">
                    <x-icon name="alert-triangle" size="15" />
                    <span>{{ $error }}</span>
                </div>
            @endif

            @if($message)
                <p class="text-sm text-[var(--text-primary)]">{{ $message }}</p>
            @endif

            @if($entityLabel || $entityName || $entityId)
                <div class="mt-4 rounded-md border border-[var(--border-color)] bg-[var(--bg)] p-3">
                    @if($entityLabel)
                        <div class="mb-1 text-[11px] font-semibold uppercase text-[var(--text-secondary)]">{{ $entityLabel }}</div>
                    @endif
                    @if($entityName)
                        <div class="text-sm font-medium text-[var(--text-primary)]">{{ $entityName }}</div>
                    @endif
                    @if($entityId)
                        <x-mono size="11" class="break-all">{{ $entityId }}</x-mono>
                    @endif
                </div>
            @endif
        </div>

        <div class="modal-footer">
            <button
                type="button"
                class="btn btn-secondary btn-md"
                wire:click="{{ $cancelAction }}"
                wire:loading.attr="disabled"
                wire:target="{{ $loadingTarget }}"
            >
                {{ $cancelLabel }}
            </button>
            <button
                type="button"
                class="btn {{ $buttonClass }} btn-md"
                wire:click="{{ $confirmAction }}"
                wire:loading.attr="disabled"
                wire:target="{{ $loadingTarget }}"
            >
                <span wire:loading.remove wire:target="{{ $loadingTarget }}">{{ $confirmLabel }}</span>
                <span wire:loading wire:target="{{ $loadingTarget }}">Processing...</span>
            </button>
        </div>
    </div>
</div>
