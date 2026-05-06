<x-layouts.auth>
    <div class="auth-card">
        <div class="auth-logo">
            <div class="sidebar-logo-mark" style="width:40px;height:40px;">
                <svg width="22" height="22" viewBox="0 0 18 18" fill="none">
                    <rect x="2" y="2" width="6" height="6" rx="1.5" fill="white" opacity="0.9"/>
                    <rect x="10" y="2" width="6" height="6" rx="1.5" fill="white" opacity="0.6"/>
                    <rect x="2" y="10" width="6" height="6" rx="1.5" fill="white" opacity="0.6"/>
                    <rect x="10" y="10" width="6" height="6" rx="1.5" fill="white" opacity="0.9"/>
                </svg>
            </div>
            <div>
                <div style="font-size:18px;font-weight:700;color:var(--text-primary);letter-spacing:-0.02em;">KomoPay</div>
                <div style="font-size:11px;color:var(--text-secondary);font-family:'DM Mono',monospace;text-transform:uppercase;letter-spacing:0.05em;">Backoffice</div>
            </div>
        </div>

        <h1 class="auth-title">Sign in</h1>
        <p class="auth-subtitle">Access restricted to authorised personnel.</p>

        @if($errors->any())
            <div class="alert alert-danger" style="margin-bottom:16px;">
                <x-icon name="alert-triangle" size="15" />
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf

            <div style="display:flex;flex-direction:column;gap:14px;">
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

                <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:4px;">
                    Sign in
                </button>
            </div>
        </form>

        <p style="margin-top:20px;font-size:11px;color:var(--text-secondary);text-align:center;font-family:'DM Mono',monospace;">
            Demo: admin@komopay.km / password
        </p>
    </div>
</x-layouts.auth>
