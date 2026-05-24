<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title ?? 'Backoffice' }} — Lipa</title>
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

<div class="app-shell">

    {{-- Sidebar --}}
    <aside class="sidebar">
        {{-- Logo --}}
        <a href="{{ route('dashboard') }}" wire:navigate class="sidebar-logo">
            <div class="sidebar-logo-mark">
                <img src="{{ asset('lipa-mark-cream.svg') }}" alt="Lipa" width="32" height="32" />
            </div>
            <div>
                <div class="sidebar-logo-text">Lipa</div>
                <div class="sidebar-logo-sub">Backoffice</div>
            </div>
        </a>

        @php
            $current = request()->route()->getName();
            $boPermissions = (array) session('bo_user.permissions', []);
            $canViewBillPayments = in_array('BILL_PAYMENT_PROCESS_VIEW', $boPermissions, true);
        @endphp

        <nav class="sidebar-nav" aria-label="Sidebar navigation">
            {{-- Operations --}}
            <div class="nav-group">
                <div class="nav-group-label">Operations</div>
                <x-nav-item route="dashboard" :current="$current" icon="grid">Dashboard</x-nav-item>
                <x-nav-item route="customers" :current="$current" icon="users">Customers</x-nav-item>
                <x-nav-item route="agents" :current="$current" icon="briefcase">Agents</x-nav-item>
                <x-nav-item route="merchants" :current="$current" icon="store">Merchants</x-nav-item>
                <x-nav-item route="transactions" :current="$current" icon="arrows">Transactions</x-nav-item>
                @if($canViewBillPayments)
                    <x-nav-item route="bill-payments" :current="$current" icon="activity">Bill Payments</x-nav-item>
                @endif
                <x-nav-item route="wallets" :current="$current" icon="wallet">Wallets</x-nav-item>
            </div>

            {{-- Compliance --}}
            <div class="nav-group">
                <div class="nav-group-label">Compliance</div>
                <x-nav-item route="approvals" :current="$current" icon="check-circle" :badge="session('pending_approvals_count', 0)">Approvals</x-nav-item>
                <x-nav-item route="audit" :current="$current" icon="shield">Audit Log</x-nav-item>
                <x-nav-item route="reconciliation" :current="$current" icon="balance">Reconciliation</x-nav-item>
                <x-nav-item route="reports" :current="$current" icon="chart">Reports</x-nav-item>
            </div>

            {{-- Finance --}}
            <div class="nav-group">
                <div class="nav-group-label">Finance</div>
                <x-nav-item route="treasury" :current="$current" icon="bank">Treasury</x-nav-item>
            </div>

            {{-- Configuration --}}
            <div class="nav-group">
                <div class="nav-group-label">Configuration</div>
                <x-nav-item route="rules-limits" :current="$current" icon="sliders">Rules & Limits</x-nav-item>
                <x-nav-item route="service-providers" :current="$current" icon="plug">Service Providers</x-nav-item>
                <x-nav-item route="cards" :current="$current" icon="credit-card">Cards</x-nav-item>
                <x-nav-item route="terminals" :current="$current" icon="monitor">Terminals</x-nav-item>
            </div>

            {{-- Administration --}}
            <div class="nav-group">
                <div class="nav-group-label">Administration</div>
                <x-nav-item route="users" :current="$current" icon="user-cog">BO Users</x-nav-item>
                <x-nav-item route="security"
                    :current="in_array($current, ['security', 'mfa.setup'], true) ? 'security' : $current"
                    icon="shield">Security</x-nav-item>
            </div>
        </nav>

        {{-- Current user --}}
        @php $boUser = session('bo_user'); @endphp
        <div class="sidebar-user">
            <div class="sidebar-avatar">
                {{ strtoupper(substr($boUser['fullName'] ?? 'U', 0, 2)) }}
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name truncate">{{ $boUser['fullName'] ?? '' }}</div>
                <div class="sidebar-user-role">{{ $boUser['role'] ?? '' }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="sidebar-logout-form">
                @csrf
                <button type="submit" class="sidebar-logout-button" aria-label="Sign out" title="Sign out">
                    <x-icon name="logout" size="15" />
                </button>
            </form>
        </div>
    </aside>

    {{-- Main Content --}}
    <div class="main-content">

        {{-- Top Bar --}}
        <header class="topbar">
            <span class="topbar-title">{{ $title ?? 'Dashboard' }}</span>
            <div class="topbar-actions">
                {{-- In-app notification inbox (spec §5.22) --}}
                <livewire:notifications.notification-bell />
            </div>
        </header>

        {{-- Page Content --}}
        <main class="page-content">
            @if (session('api_error') || $errors->any())
                <div class="alert alert-danger mb-4">
                    <x-icon name="alert-triangle" size="15" />
                    <span>{{ session('api_error') ?: $errors->first() }}</span>
                </div>
            @endif

            {{ $slot }}
        </main>

    </div>

</div>

<div id="globalApiAlert" class="alert alert-danger fixed right-4 top-16 z-[120] max-w-md shadow-lg" role="alert" style="display: none;">
    <x-icon name="alert-triangle" size="15" />
    <div class="min-w-0">
        <strong>Request failed</strong>
        <div id="globalApiAlertMessage"></div>
        <div id="globalApiAlertMeta" class="text-mono mt-1 hidden break-all text-[11px] opacity-80"></div>
    </div>
    <button id="globalApiAlertClose" type="button" class="modal-close ml-auto" aria-label="Dismiss">
        <x-icon name="x" size="14" />
    </button>
</div>

@livewireScripts
<script>
    (() => {
        let hideTimer = null;

        const normalizePayload = (payload) => Array.isArray(payload) ? payload[0] : payload;

        const showApiError = (payload) => {
            const alertEl = document.getElementById('globalApiAlert');
            const messageEl = document.getElementById('globalApiAlertMessage');
            const metaEl = document.getElementById('globalApiAlertMeta');
            if (!alertEl || !messageEl || !metaEl) return;

            const detail = normalizePayload(payload) || {};
            const message = detail.message || 'The request could not be completed.';
            const meta = [detail.code, detail.correlationId].filter(Boolean).join(' | ');

            messageEl.textContent = message;
            metaEl.textContent = meta;
            metaEl.classList.toggle('hidden', meta === '');
            alertEl.style.display = 'flex';

            window.clearTimeout(hideTimer);
            hideTimer = window.setTimeout(() => {
                alertEl.style.display = 'none';
            }, 8000);
        };

        // Re-bind the dismiss button after every SPA navigation (and on first load).
        const bindCloseButton = () => {
            const closeEl = document.getElementById('globalApiAlertClose');
            if (!closeEl || closeEl.dataset.bound === '1') return;
            closeEl.dataset.bound = '1';
            closeEl.addEventListener('click', () => {
                const alertEl = document.getElementById('globalApiAlert');
                if (alertEl) alertEl.style.display = 'none';
                window.clearTimeout(hideTimer);
            });
        };
        document.addEventListener('livewire:navigated', bindCloseButton);

        // Livewire event listeners only need to be registered once.
        document.addEventListener('livewire:init', () => {
            Livewire.on('api-error', showApiError);
        });

        window.addEventListener('api-error', (event) => showApiError(event.detail));
    })();
</script>
@stack('scripts')
</body>
</html>
