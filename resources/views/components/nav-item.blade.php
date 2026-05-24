@props(['route', 'current', 'icon', 'badge' => 0])

@php
$isActive = $current === $route;
@endphp

<a href="{{ route($route) }}" wire:navigate class="nav-item {{ $isActive ? 'active' : '' }}">
    <span class="nav-icon">
        <x-icon :name="$icon" :size="16" />
    </span>
    <span>{{ $slot }}</span>
    @if($badge > 0)
        <span class="nav-badge">{{ $badge }}</span>
    @endif
</a>
