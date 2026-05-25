<x-layouts.app title="Account Security">
    <x-page-header title="Account security"
        subtitle="Review your account and manage two-factor authentication." />

    @if (session('status'))
        <div class="alert alert-success mb-4">
            <x-icon name="check" size="15" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @php
        $fullName = trim((string) ($boUser['fullName'] ?? '')) ?: '—';
        $email = (string) ($boUser['email'] ?? '');
        $role = (string) ($boUser['role'] ?? '');
        $roleLabel = $role !== '' ? ucwords(strtolower(str_replace('_', ' ', $role))) : '—';
        $roleClass = $role !== '' ? 'badge-' . strtolower(str_replace('_', '-', $role)) : '';
        $initials = collect(explode(' ', $fullName))
            ->filter()
            ->take(2)
            ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
            ->implode('') ?: 'U';
    @endphp

    {{-- Account overview --}}
    <div class="card mb-4" style="max-width: 640px;">
        <div class="card-body">
            <div class="identity">
                <div class="identity-avatar">{{ $initials }}</div>
                <div class="identity-info">
                    <div class="identity-name truncate">{{ $fullName }}</div>
                    @if ($email !== '')
                        <div class="identity-email truncate">{{ $email }}</div>
                    @endif
                    <div class="identity-badges">
                        @if ($role !== '')
                            <span class="badge {{ $roleClass }}">{{ $roleLabel }}</span>
                        @endif
                        @if ($mfaEnabled)
                            <span class="badge badge-active">2FA enabled</span>
                        @else
                            <span class="badge badge-pending">2FA off</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <dl class="info-grid">
                <dt>Full name</dt>
                <dd>{{ $fullName }}</dd>
                <dt>Email</dt>
                <dd>{{ $email !== '' ? $email : '—' }}</dd>
                <dt>Role</dt>
                <dd>{{ $roleLabel }}</dd>
            </dl>
        </div>
    </div>

    {{-- Two-factor authentication --}}
    <div class="card" style="max-width: 640px;">
        <div class="card-header">
            <div class="card-title">
                <x-icon name="shield" size="16" />
                <span>Two-factor authentication (TOTP)</span>
            </div>
        </div>
        <div class="card-body">
            <p class="text-[13px] leading-relaxed text-[var(--text-secondary)]">
                Protect your account with a time-based one-time code from an authenticator app
                (Google Authenticator, Authy, or any compatible app). Once enabled, you will be
                asked for a 6-digit code each time you sign in.
            </p>

            @if ($mfaMandatory)
                <div class="alert alert-info mt-4">
                    <x-icon name="info" size="15" />
                    <span>Two-factor authentication is mandatory for your role and cannot be disabled.</span>
                </div>

                <div class="mt-4">
                    <a href="{{ route('mfa.setup') }}" class="btn btn-secondary btn-md">Re-configure authenticator</a>
                </div>
            @elseif ($mfaEnabled)
                {{-- Enrolled, optional role: reconfigure or disable. --}}
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('mfa.setup') }}" class="btn btn-secondary btn-md">Re-configure authenticator</a>
                    <button type="button" class="btn btn-danger btn-md" id="openRevoke">Disable two-factor</button>
                </div>

                {{-- Disable requires the current code as a step-up (spec §3.1b). --}}
                <div id="revokePanel" class="mt-4 hidden">
                    <div class="divider"></div>
                    <p class="mt-3 text-[13px] text-[var(--text-secondary)]">
                        Enter a current code from your authenticator app to turn off two-factor
                        authentication.
                    </p>

                    @if ($errors->any())
                        <div class="alert alert-danger mt-3">
                            <x-icon name="alert-triangle" size="15" />
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('mfa.revoke') }}" class="mt-3 flex flex-col gap-3">
                        @csrf
                        @method('DELETE')
                        <div style="max-width: 220px;">
                            <label class="form-label" for="code">
                                Authentication code <span class="form-required">*</span>
                            </label>
                            <input id="code" name="code" type="text"
                                class="form-input text-mono tracking-[0.4em] {{ $errors->has('code') ? 'has-error' : '' }}"
                                inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6"
                                placeholder="000000" required />
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-danger btn-md">Disable two-factor</button>
                            <button type="button" class="btn btn-ghost btn-md" id="cancelRevoke">Cancel</button>
                        </div>
                    </form>
                </div>
            @else
                {{-- Not enrolled, optional role: only the enable action is relevant. --}}
                <div class="mt-4">
                    <a href="{{ route('mfa.setup') }}" class="btn btn-primary btn-md">Enable two-factor authentication</a>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            (function() {
                var hadError = @json($errors->any());

                function initRevokePanel() {
                    var open = document.getElementById('openRevoke');
                    var panel = document.getElementById('revokePanel');
                    var cancel = document.getElementById('cancelRevoke');
                    if (!panel || panel.dataset.bound === '1') {
                        if (panel && hadError) panel.classList.remove('hidden');
                        return;
                    }
                    panel.dataset.bound = '1';
                    if (open) {
                        open.addEventListener('click', function() {
                            panel.classList.remove('hidden');
                            var code = document.getElementById('code');
                            if (code) code.focus();
                        });
                    }
                    if (cancel) {
                        cancel.addEventListener('click', function() {
                            panel.classList.add('hidden');
                        });
                    }
                    // If the revoke attempt came back with an error, keep the panel open.
                    if (hadError) panel.classList.remove('hidden');
                }

                initRevokePanel();
                document.addEventListener('livewire:navigated', initRevokePanel);
            })();
        </script>
    @endpush
</x-layouts.app>
