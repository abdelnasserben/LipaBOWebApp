@props(['size' => 12, 'muted' => true])
<span class="text-mono" style="font-size: {{ $size }}px; {{ $muted ? 'color: var(--text-secondary);' : '' }}">{{ $slot }}</span>
