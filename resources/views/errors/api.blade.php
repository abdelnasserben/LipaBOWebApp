@php
    $pageTitle = 'API request failed';
    $statusLabel = $status ? 'HTTP ' . $status : 'Connection error';
    $backUrl = url()->previous() !== url()->current() ? url()->previous() : route('dashboard');
@endphp

@if (session()->has('bo_user'))
    <x-layouts.app :title="$pageTitle">
        <x-page-header
            title="API request failed"
            subtitle="The Backoffice service rejected the request."
        />

        <div class="alert alert-danger mb-4 max-w-3xl">
            <x-icon name="alert-triangle" size="15" />
            <div>
                <strong>{{ $statusLabel }}</strong>
                <div>{{ $message }}</div>
            </div>
        </div>

        @if ($code || $correlationId || ! empty($details))
            <div class="card max-w-3xl">
                <div class="card-header">
                    <x-icon name="info" size="14" class="text-[var(--text-secondary)]" />
                    <span class="card-title">Error details</span>
                </div>
                <div class="card-body">
                    <div class="grid gap-3 text-sm">
                        @if ($code)
                            <div>
                                <div class="text-mono text-[11px] uppercase text-[var(--text-secondary)]">Code</div>
                                <div class="text-mono text-xs">{{ $code }}</div>
                            </div>
                        @endif

                        @if ($correlationId)
                            <div>
                                <div class="text-mono text-[11px] uppercase text-[var(--text-secondary)]">Correlation ID</div>
                                <div class="text-mono break-all text-xs">{{ $correlationId }}</div>
                            </div>
                        @endif

                        @if (! empty($details))
                            <div>
                                <div class="text-mono text-[11px] uppercase text-[var(--text-secondary)]">Details</div>
                                <ul class="mt-1 list-disc pl-5">
                                    @foreach ($details as $detail)
                                        <li>{{ $detail }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-4 flex gap-2">
            <a href="{{ $backUrl }}" class="btn btn-secondary btn-md">
                <x-icon name="chevron-left" size="14" />
                Back
            </a>
            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-md">
                <x-icon name="grid" size="14" />
                Dashboard
            </a>
        </div>
    </x-layouts.app>
@else
    <x-layouts.auth>
        <div class="auth-card">
            <div class="alert alert-danger mb-4">
                <x-icon name="alert-triangle" size="15" />
                <span>{{ $message }}</span>
            </div>

            <a href="{{ route('login') }}" class="btn btn-primary btn-lg w-full justify-center">
                Sign in
            </a>
        </div>
    </x-layouts.auth>
@endif
