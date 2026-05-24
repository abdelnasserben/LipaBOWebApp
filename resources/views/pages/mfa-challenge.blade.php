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

        <h1 class="auth-title">Two-factor authentication</h1>
        <p class="auth-subtitle">Enter the 6-digit code from your authenticator app.</p>

        @if ($errors->any())
            <div class="alert alert-danger mb-4">
                <x-icon name="alert-triangle" size="15" />
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form id="mfaChallengeForm" method="POST" action="{{ route('mfa.challenge.post') }}">
            @csrf

            <div class="flex flex-col gap-3.5">
                <div>
                    <label class="form-label" for="code">
                        Authentication code <span class="form-required">*</span>
                    </label>
                    <input id="code" name="code" type="text"
                        class="form-input text-mono tracking-[0.4em] {{ $errors->has('code') ? 'has-error' : '' }}"
                        inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6"
                        placeholder="000000" autofocus required />
                    <p class="text-mono mt-1.5 text-[11px] text-[var(--text-secondary)]">Codes refresh every 30 seconds.
                    </p>
                </div>

                <button id="verifyButton" type="submit" class="btn btn-primary btn-lg mt-1 w-full justify-center">
                    <span id="verifyText">Verify</span>
                    <span id="loadingText" class="hidden">Verifying…</span>
                </button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
            @csrf
            <button type="submit"
                class="text-mono text-[11px] text-[var(--text-secondary)] underline hover:text-[var(--text-primary)]">
                Cancel and sign in again
            </button>
        </form>
    </div>
    <script>
        document.getElementById('mfaChallengeForm').addEventListener('submit', function() {
            document.getElementById('verifyButton').disabled = true;
            document.getElementById('verifyText').classList.add('hidden');
            document.getElementById('loadingText').classList.remove('hidden');
        });
    </script>
</x-layouts.auth>
