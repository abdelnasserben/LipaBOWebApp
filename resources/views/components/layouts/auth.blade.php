<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login — Lipa Backoffice</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}" />
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}" />
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}" />
    <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('favicon-192x192.png') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>

<div class="auth-layout">
    {{-- Brand panel (dark) --}}
    <aside class="auth-brand">
        <div class="auth-brand-grid" aria-hidden="true"></div>

        <div class="auth-brand-top">
            <div class="auth-brand-logo">
                <div class="auth-brand-mark">
                    <img src="{{ asset('lipa-icon-white.svg') }}" alt="Lipa" width="26" height="26" />
                </div>
                <div>
                    <div class="auth-brand-name">Lipa</div>
                    <div class="auth-brand-sub">Backoffice</div>
                </div>
            </div>
        </div>

        <div class="auth-brand-body">
            <h2 class="auth-brand-headline">Operations<br />Command Centre</h2>
            <p class="auth-brand-tagline">Secure access for authorised Lipa team members only.</p>

            <ul class="auth-brand-points">
                <li>
                    <x-icon name="shield" size="16" />
                    <span>Two-factor authentication enforced</span>
                </li>
                <li>
                    <x-icon name="lock" size="16" />
                    <span>Encrypted, role-based access</span>
                </li>
                <li>
                    <x-icon name="check-double" size="16" />
                    <span>Every action is logged and audited</span>
                </li>
            </ul>
        </div>
    </aside>

    {{-- Form panel (light) --}}
    <main class="auth-panel">
        {{ $slot }}
    </main>
</div>

@livewireScripts
@stack('scripts')
</body>
</html>
