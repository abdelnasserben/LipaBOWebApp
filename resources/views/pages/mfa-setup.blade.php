<x-layouts.app title="Set up two-factor authentication">
    <x-page-header title="Set up two-factor authentication"
        subtitle="Scan the QR code with your authenticator app and confirm a code." />

    <div class="card" style="max-width: 480px;">
        <div class="card-body">
            @include('partials.mfa-setup-form', [
                'action' => route('mfa.setup.post'),
                'cancel' => route('security'),
            ])

            <div class="mt-3 text-center">
                <a href="{{ route('security') }}"
                    class="text-mono text-[11px] text-[var(--text-secondary)] underline hover:text-[var(--text-primary)]">
                    Cancel
                </a>
            </div>
        </div>
    </div>
</x-layouts.app>
