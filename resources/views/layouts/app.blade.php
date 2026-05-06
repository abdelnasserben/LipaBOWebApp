<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title ?? 'Backoffice' }} — Lipa</title>
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
        <div class="sidebar-logo">
            <div class="sidebar-logo-mark">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <rect x="2" y="2" width="6" height="6" rx="1.5" fill="white" opacity="0.9"/>
                    <rect x="10" y="2" width="6" height="6" rx="1.5" fill="white" opacity="0.6"/>
                    <rect x="2" y="10" width="6" height="6" rx="1.5" fill="white" opacity="0.6"/>
                    <rect x="10" y="10" width="6" height="6" rx="1.5" fill="white" opacity="0.9"/>
                </svg>
            </div>
            <div>
                <div class="sidebar-logo-text">Lipa</div>
                <div class="sidebar-logo-sub">Backoffice</div>
            </div>
        </div>

        @php $current = request()->route()->getName(); @endphp

        <nav class="sidebar-nav" aria-label="Sidebar navigation">
            {{-- Operations --}}
            <div class="nav-group">
                <div class="nav-group-label">Operations</div>
                <x-nav-item route="dashboard" :current="$current" icon="grid">Dashboard</x-nav-item>
                <x-nav-item route="customers" :current="$current" icon="users">Customers</x-nav-item>
                <x-nav-item route="agents" :current="$current" icon="briefcase">Agents</x-nav-item>
                <x-nav-item route="merchants" :current="$current" icon="store">Merchants</x-nav-item>
                <x-nav-item route="transactions" :current="$current" icon="arrows">Transactions</x-nav-item>
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
            </div>
        </nav>

        {{-- Current user --}}
        @php $boUser = session('bo_user'); @endphp
        <div class="sidebar-user">
            <div class="sidebar-avatar">
                {{ strtoupper(substr($boUser['fullName'] ?? 'U', 0, 2)) }}
            </div>
            <div class="min-w-0">
                <div class="sidebar-user-name truncate">{{ $boUser['fullName'] ?? '' }}</div>
                <div class="sidebar-user-role">{{ $boUser['role'] ?? '' }}</div>
            </div>
        </div>
    </aside>

    {{-- Main Content --}}
    <div class="main-content">

        {{-- Top Bar --}}
        <header class="topbar">
            <span class="topbar-title">{{ $title ?? 'Dashboard' }}</span>
            <div class="topbar-actions">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm">
                        <x-icon name="logout" size="14" />
                        Sign out
                    </button>
                </form>
            </div>
        </header>

        {{-- Page Content --}}
        <main class="page-content">
            {{ $slot }}
        </main>

    </div>

</div>

@livewireScripts
</body>
</html>
