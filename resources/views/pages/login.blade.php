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

        <h1 class="auth-title">Sign in</h1>
        <p class="auth-subtitle">Use your team credentials to continue.</p>

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
                        placeholder="admin@lipa.km" autocomplete="email" required />
                </div>

                <div>
                    <label class="form-label" for="password">
                        Password <span class="form-required">*</span>
                    </label>
                    <div class="auth-input-wrap">
                        <input id="password" name="password" type="password"
                            class="form-input {{ $errors->has('password') ? 'has-error' : '' }}" placeholder="••••••••"
                            autocomplete="current-password" required />
                        <button type="button" class="auth-pw-toggle" data-pw-toggle="password"
                            aria-label="Show password">
                            <x-icon name="eye" size="18" data-pw-icon-show />
                            <x-icon name="eye-off" size="18" class="hidden" data-pw-icon-hide />
                        </button>
                    </div>
                </div>

                <button id="loginButton" type="submit" class="btn btn-primary btn-lg mt-1 w-full justify-center">
                    <span id="loginText">Sign in</span>
                    <span id="loadingText" class="hidden">Authenticating…</span>
                </button>
            </div>
        </form>

        <p class="text-mono mt-5 text-center text-[11px] leading-relaxed text-[var(--text-secondary)]">
            For access issues, contact your system administrator.<br />
            All access is logged and audited.
        </p>
    </div>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            document.getElementById('loginButton').disabled = true;
            document.getElementById('loginText').classList.add('hidden');
            document.getElementById('loadingText').classList.remove('hidden');
        });

        document.querySelectorAll('[data-pw-toggle]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var input = document.getElementById(btn.getAttribute('data-pw-toggle'));
                if (!input) return;
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                btn.querySelector('[data-pw-icon-show]').classList.toggle('hidden', show);
                btn.querySelector('[data-pw-icon-hide]').classList.toggle('hidden', !show);
            });
        });
    </script>
</x-layouts.auth>
