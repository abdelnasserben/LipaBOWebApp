@props(['title', 'subtitle' => null])

<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-header-title">{{ $title }}</h1>
        @if($subtitle)
            <p class="page-header-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if(isset($actions))
        <div class="page-header-actions">
            {{ $actions }}
        </div>
    @endif
</div>
