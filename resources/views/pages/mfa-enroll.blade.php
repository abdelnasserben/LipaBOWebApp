<x-layouts.auth>
    <div class="auth-card">
        <div class="auth-logo">
            <div class="auth-logo-mark">
                <img src="{{ asset('lipa-icon-white.svg') }}" alt="Lipa" width="28" height="28" />
            </div>
            <div>
                <div class="text-[18px] font-bold tracking-[-0.02em] text-[var(--text-primary)]">Lipa</div>
                <div class="text-mono text-[11px] uppercase tracking-[0.05em] text-[var(--text-secondary)]">Backoffice
                </div>
            </div>
        </div>

        <h1 class="auth-title">Set up two-factor authentication</h1>
        <p class="auth-subtitle">Your role requires an authenticator app before you can sign in.</p>

        @include('partials.mfa-setup-form', [
            'action' => route('mfa.enroll.post'),
            'cancel' => route('login'),
        ])

        <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
            @csrf
            <button type="submit"
                class="text-mono text-[11px] text-[var(--text-secondary)] underline hover:text-[var(--text-primary)]">
                Cancel and sign in again
            </button>
        </form>
    </div>
</x-layouts.auth>
