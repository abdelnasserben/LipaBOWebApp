@props(['size' => 12, 'muted' => true])
@php
$sizeClass = match ((int) $size) {
    9 => 'text-[9px]',
    10 => 'text-[10px]',
    11 => 'text-[11px]',
    12 => 'text-xs',
    13 => 'text-[13px]',
    14 => 'text-sm',
    15 => 'text-[15px]',
    16 => 'text-base',
    default => 'text-xs',
};
@endphp

<span {{ $attributes->class([
    'text-mono',
    $sizeClass,
    'text-[var(--text-secondary)]' => $muted,
]) }}>{{ $slot }}</span>
