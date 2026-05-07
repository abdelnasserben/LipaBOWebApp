@props(['value', 'currency' => 'KMF', 'size' => 13])
@php
$formatted = is_numeric($value) ? number_format($value, 0, ',', ' ') : $value;
$amountSizeClass = match ((int) $size) {
    11 => 'text-[11px]',
    12 => 'text-xs',
    13 => 'text-[13px]',
    14 => 'text-sm',
    15 => 'text-[15px]',
    16 => 'text-base',
    18 => 'text-lg',
    20 => 'text-xl',
    24 => 'text-2xl',
    28 => 'text-[28px]',
    32 => 'text-[32px]',
    default => 'text-[13px]',
};
$currencySizeClass = match ((int) $size) {
    11 => 'text-[10px]',
    12 => 'text-[11px]',
    13 => 'text-xs',
    14 => 'text-[13px]',
    15 => 'text-sm',
    16 => 'text-[15px]',
    18 => 'text-[17px]',
    20 => 'text-[19px]',
    24 => 'text-[23px]',
    28 => 'text-[27px]',
    32 => 'text-[31px]',
    default => 'text-xs',
};
@endphp
<span class="text-mono font-medium" {{ $attributes->class(['amount', $amountSizeClass]) }}>
    {{ $formatted }}
    <span class="{{ $currencySizeClass }} font-normal text-[var(--text-secondary)]">{{ $currency }}</span>
</span>
