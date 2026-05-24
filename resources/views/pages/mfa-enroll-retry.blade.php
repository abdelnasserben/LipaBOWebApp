<x-layouts.auth>
    <div class="auth-card">
        <div class="auth-logo">
            <div class="auth-logo-mark">
                <img src="{{ asset('lipa-mark-cream.svg') }}" alt="Lipa" width="28" height="28" />
            </div>
            <div>
                <div class="text-[18px] font-bold tracking-[-0.02em] text-[var(--text-primary)]">Lipa</div>
                <div class="text-mono text-[11px] uppercase tracking-[0.05em] text-[var(--text-secondary)]">Backoffice
                </div>
            </div>
        </div>

        <h1 class="auth-title">Confirm your code</h1>
        <p class="auth-subtitle">One more step to finish setting up two-factor authentication.</p>

        @include('partials.mfa-confirm-retry', ['action' => route('mfa.enroll.post')])

        <div class="mt-3 flex items-center justify-center gap-3">
            <a href="{{ route('mfa.enroll') }}"
                class="text-mono text-[11px] text-[var(--text-secondary)] underline hover:text-[var(--text-primary)]">
                Start over with a new QR code
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="text-mono text-[11px] text-[var(--text-secondary)] underline hover:text-[var(--text-primary)]">
                    Sign in again
                </button>
            </form>
        </div>
    </div>
</x-layouts.auth>
