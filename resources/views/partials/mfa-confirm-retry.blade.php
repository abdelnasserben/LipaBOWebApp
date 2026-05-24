{{--
    Code-only confirm retry (spec §3.1b, step 2).

    Shown when a confirm code is wrong/expired. The pending secret is unchanged —
    re-rendering the QR would regenerate it (replacing the secret the user already
    scanned), so we only ask for a fresh code here. Posts back to $action.

    Required vars: $action (route URL).
--}}
@if ($errors->any())
    <div class="alert alert-danger mb-4">
        <x-icon name="alert-triangle" size="15" />
        <span>{{ $errors->first() }}</span>
    </div>
@endif

<p class="text-[13px] leading-relaxed text-[var(--text-secondary)]">
    Your authenticator is still set up. Enter a fresh 6-digit code to finish enabling
    two-factor authentication.
</p>

<form id="mfaRetryForm" method="POST" action="{{ $action }}" class="mt-4">
    @csrf

    <div class="flex flex-col gap-3.5">
        <div>
            <label class="form-label" for="code">
                Authentication code <span class="form-required">*</span>
            </label>
            <input id="code" name="code" type="text"
                class="form-input text-mono tracking-[0.4em] {{ $errors->has('code') ? 'has-error' : '' }}"
                inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000"
                autofocus required />
        </div>

        <button id="retryButton" type="submit" class="btn btn-primary btn-lg mt-1 w-full justify-center">
            <span id="retryText">Enable two-factor authentication</span>
            <span id="retryLoadingText" class="hidden">Verifying…</span>
        </button>
    </div>
</form>

@push('scripts')
    <script>
        (function() {
            var form = document.getElementById('mfaRetryForm');
            if (form) {
                form.addEventListener('submit', function() {
                    document.getElementById('retryButton').disabled = true;
                    document.getElementById('retryText').classList.add('hidden');
                    document.getElementById('retryLoadingText').classList.remove('hidden');
                });
            }
        })();
    </script>
@endpush
