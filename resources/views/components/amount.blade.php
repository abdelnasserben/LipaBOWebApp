@props(['value', 'currency' => 'KMF', 'size' => 13])
@php
$formatted = is_numeric($value) ? number_format($value, 0, ',', ' ') : $value;
@endphp
<span class="amount" style="font-size: {{ $size }}px;">
    {{ $formatted }}
    <span style="font-weight:400;color:var(--text-secondary);font-size:{{ $size - 1 }}px;">{{ $currency }}</span>
</span>
