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

        <h1 class="auth-title">Sign in</h1>
        <p class="auth-subtitle">Access restricted to authorised personnel.</p>

        @if (session('status'))
            <div class="alert alert-success mb-4">
                <x-icon name="check" size="15" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mb-4">
                <x-icon name="alert-triangle" size="15" />
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form id="loginForm" method="POST" action="{{ route('login.post') }}">
            @csrf

            <div class="flex flex-col gap-3.5">
                <div>
                    <label class="form-label" for="email">
                        Email address <span class="form-required">*</span>
                    </label>
                    <input id="email" name="email" type="email"
                        class="form-input {{ $errors->has('email') ? 'has-error' : '' }}" value="{{ old('email') }}"
                        placeholder="admin@komopay.km" autocomplete="email" required />
                </div>

                <div>
                    <label class="form-label" for="password">
                        Password <span class="form-required">*</span>
                    </label>
                    <input id="password" name="password" type="password"
                        class="form-input {{ $errors->has('password') ? 'has-error' : '' }}" placeholder="••••••••"
                        autocomplete="current-password" required />
                </div>

                <button id="loginButton" type="submit" class="btn btn-primary btn-lg mt-1 w-full justify-center">
                    <span id="loginText">Sign in</span>
                    <span id="loadingText" class="hidden">Authenticating…</span>
                </button>
            </div>
        </form>

        <p class="text-mono mt-3 text-center text-[11px] text-[var(--text-secondary)]">
            For access issues, contact your system administrator.
        </p>
    </div>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            document.getElementById('loginButton').disabled = true;
            document.getElementById('loginText').classList.add('hidden');
            document.getElementById('loadingText').classList.remove('hidden');
        });
    </script>
</x-layouts.auth>
