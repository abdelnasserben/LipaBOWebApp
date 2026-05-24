{{--
    Shared TOTP enrollment form (spec §3.1b, step 1 + 2).
    Renders the QR from `qrUri`, shows `secret` for manual entry, and posts the
    confirmation code to `$action`. Both values are credentials — they live only
    in this single render and are never persisted.

    Required vars: $qrUri, $secret, $action (route URL), $cancel (route URL).
--}}
@if ($errors->any())
    <div class="alert alert-danger mb-4">
        <x-icon name="alert-triangle" size="15" />
        <span>{{ $errors->first() }}</span>
    </div>
@endif

<ol class="mfa-steps">
    <li>
        <span class="mfa-step-label">1. Scan this QR code with your authenticator app</span>
        <div class="mfa-qr" id="mfaQr" data-qr-uri="{{ $qrUri }}" role="img"
            aria-label="TOTP enrollment QR code"></div>
    </li>
    <li>
        <span class="mfa-step-label">2. Or enter this key manually</span>
        <div class="mfa-secret">
            <code class="text-mono" id="mfaSecret">{{ $secret }}</code>
            <button type="button" class="btn btn-ghost btn-sm" id="copySecret" aria-label="Copy key">
                <x-icon name="copy" size="14" />
                <span>Copy</span>
            </button>
        </div>
    </li>
</ol>

<form id="mfaConfirmForm" method="POST" action="{{ $action }}">
    @csrf

    <div class="flex flex-col gap-3.5">
        <div>
            <label class="form-label" for="code">
                3. Enter the 6-digit code from the app <span class="form-required">*</span>
            </label>
            <input id="code" name="code" type="text"
                class="form-input text-mono tracking-[0.4em] {{ $errors->has('code') ? 'has-error' : '' }}"
                inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000"
                required />
        </div>

        <button id="confirmButton" type="submit" class="btn btn-primary btn-lg mt-1 w-full justify-center">
            <span id="confirmText">Enable two-factor authentication</span>
            <span id="loadingText" class="hidden">Verifying…</span>
        </button>
    </div>
</form>

@once
    @push('scripts')
        <script src="{{ asset('js/qrcode.min.js') }}"></script>
        <script>
            (function() {
                var el = document.getElementById('mfaQr');
                if (el && window.qrcode) {
                    // typeNumber 0 = auto-size; 'M' error correction is plenty for an otpauth URI.
                    var qr = qrcode(0, 'M');
                    qr.addData(el.dataset.qrUri);
                    qr.make();
                    el.innerHTML = qr.createSvgTag({
                        scalable: true,
                        margin: 0
                    });
                }

                var copyBtn = document.getElementById('copySecret');
                if (copyBtn && navigator.clipboard) {
                    copyBtn.addEventListener('click', function() {
                        navigator.clipboard.writeText(document.getElementById('mfaSecret').textContent.trim())
                            .then(function() {
                                var span = copyBtn.querySelector('span');
                                var prev = span.textContent;
                                span.textContent = 'Copied';
                                setTimeout(function() {
                                    span.textContent = prev;
                                }, 1500);
                            });
                    });
                }

                var form = document.getElementById('mfaConfirmForm');
                if (form) {
                    form.addEventListener('submit', function() {
                        document.getElementById('confirmButton').disabled = true;
                        document.getElementById('confirmText').classList.add('hidden');
                        document.getElementById('loadingText').classList.remove('hidden');
                    });
                }
            })();
        </script>
    @endpush
@endonce
