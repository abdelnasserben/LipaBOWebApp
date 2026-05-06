<x-layouts.auth>
    <div class="auth-card">
        <div class="auth-logo">
            <div class="sidebar-logo-mark !h-10 !w-10">
                <svg width="22" height="22" viewBox="0 0 18 18" fill="none">
                    <rect x="2" y="2" width="6" height="6" rx="1.5" fill="white" opacity="0.9"/>
                    <rect x="10" y="2" width="6" height="6" rx="1.5" fill="white" opacity="0.6"/>
                    <rect x="2" y="10" width="6" height="6" rx="1.5" fill="white" opacity="0.6"/>
                    <rect x="10" y="10" width="6" height="6" rx="1.5" fill="white" opacity="0.9"/>
                </svg>
            </div>
            <div>
                <div class="text-[18px] font-bold tracking-[-0.02em] text-[var(--text-primary)]">Lipa</div>
                <div class="text-mono text-[11px] uppercase tracking-[0.05em] text-[var(--text-secondary)]">Backoffice</div>
            </div>
        </div>

        <h1 class="auth-title">Sign in</h1>
        <p class="auth-subtitle">Access restricted to authorised personnel.</p>

        @if($errors->any())
            <div class="alert alert-danger mb-4">
                <x-icon name="alert-triangle" size="15" />
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf

            <div class="flex flex-col gap-3.5">
                <div>
                    <label class="form-label" for="email">
                        Email address <span class="form-required">*</span>
                    </label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        class="form-input {{ $errors->has('email') ? 'has-error' : '' }}"
                        value="{{ old('email') }}"
                        placeholder="admin@komopay.km"
                        autocomplete="email"
                        required
                    />
                </div>

                <div>
                    <label class="form-label" for="password">
                        Password <span class="form-required">*</span>
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        class="form-input {{ $errors->has('password') ? 'has-error' : '' }}"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required
                    />
                </div>

                <button type="submit" class="btn btn-primary btn-lg mt-1 w-full justify-center">
                    Sign in
                </button>
            </div>
        </form>

        <p class="text-mono mt-3 text-center text-[11px] text-[var(--text-secondary)]">
            For access issues, contact your system administrator.
        </p>
    </div>
</x-layouts.auth>
