<x-layouts.app title="Confirm your code">
    <x-page-header title="Confirm your code"
        subtitle="One more step to finish setting up two-factor authentication." />

    <div class="card" style="max-width: 480px;">
        <div class="card-body">
            @include('partials.mfa-confirm-retry', ['action' => route('mfa.setup.post')])

            <div class="mt-3 flex items-center gap-3">
                <a href="{{ route('mfa.setup') }}"
                    class="text-mono text-[11px] text-[var(--text-secondary)] underline hover:text-[var(--text-primary)]">
                    Start over with a new QR code
                </a>
                <a href="{{ route('security') }}"
                    class="text-mono text-[11px] text-[var(--text-secondary)] underline hover:text-[var(--text-primary)]">
                    Cancel
                </a>
            </div>
        </div>
    </div>
</x-layouts.app>
