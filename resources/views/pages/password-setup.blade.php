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

        <h1 class="auth-title">Set your password</h1>
        <p class="auth-subtitle">Choose a new password to finish activating your account.</p>

        @if ($errors->any())
            <div class="alert alert-danger mb-4">
                <x-icon name="alert-triangle" size="15" />
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form id="passwordSetupForm" method="POST" action="{{ route('password-setup.post') }}">
            @csrf

            <div class="flex flex-col gap-3.5">
                <div>
                    <label class="form-label" for="new_password">
                        New password <span class="form-required">*</span>
                    </label>
                    <div class="auth-input-wrap">
                        <input id="new_password" name="new_password" type="password"
                            class="form-input {{ $errors->has('new_password') ? 'has-error' : '' }}"
                            placeholder="••••••••" autocomplete="new-password" minlength="8" maxlength="128" required />
                        <button type="button" class="auth-pw-toggle" data-pw-toggle="new_password"
                            aria-label="Show password">
                            <x-icon name="eye" size="18" data-pw-icon-show />
                            <x-icon name="eye-off" size="18" class="hidden" data-pw-icon-hide />
                        </button>
                    </div>
                    <p class="text-mono mt-1.5 text-[11px] text-[var(--text-secondary)]">Between 8 and 128 characters.</p>
                </div>

                <div>
                    <label class="form-label" for="new_password_confirmation">
                        Confirm password <span class="form-required">*</span>
                    </label>
                    <div class="auth-input-wrap">
                        <input id="new_password_confirmation" name="new_password_confirmation" type="password"
                            class="form-input" placeholder="••••••••" autocomplete="new-password" minlength="8"
                            maxlength="128" required />
                        <button type="button" class="auth-pw-toggle" data-pw-toggle="new_password_confirmation"
                            aria-label="Show password">
                            <x-icon name="eye" size="18" data-pw-icon-show />
                            <x-icon name="eye-off" size="18" class="hidden" data-pw-icon-hide />
                        </button>
                    </div>
                </div>

                <button id="setupButton" type="submit" class="btn btn-primary btn-lg mt-1 w-full justify-center">
                    <span id="setupText">Set password</span>
                    <span id="loadingText" class="hidden">Saving…</span>
                </button>
            </div>
        </form>

        <p class="text-mono mt-3 text-center text-[11px] text-[var(--text-secondary)]">
            After setting your password you will sign in with it.
        </p>
    </div>
    <script>
        document.getElementById('passwordSetupForm').addEventListener('submit', function() {
            document.getElementById('setupButton').disabled = true;
            document.getElementById('setupText').classList.add('hidden');
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
