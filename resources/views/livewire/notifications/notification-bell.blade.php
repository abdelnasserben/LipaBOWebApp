<?php

use Livewire\Component;
use App\Exceptions\BackofficeApiException;
use App\Services\Api\UsesBackofficeApi;
use App\Support\NotificationInboxItems;
use App\Support\NotificationTargets;

/**
 * Notification bell — In-App Inbox (spec §5.22).
 *
 * Backoffice users share the same inbox as end-users, scoped server-side to their
 * own principal. BO-facing categories are BILL_PAYMENT, APPROVAL, and
 * RECONCILIATION; TRANSACTION is kept for non-BO actors.
 *
 * Delivery model the frontend must honour:
 *  - Inbox is PULL-ONLY (no WebSocket/SSE/FCM). The unread badge is polled.
 *  - Notifications are written asynchronously (~5 s backend cadence), so we refetch
 *    rather than insert locally.
 *  - Tapping a row marks it read optimistically, then deep-links only when the
 *    category/type pair carries a supported BO target in the row's `data` JSON.
 */
new class extends Component {
    use UsesBackofficeApi;

    public int $unread = 0;
    public bool $open = false;

    /** @var array<int, array<string, mixed>> */
    public array $items = [];
    public bool $loaded = false;

    public function mount(): void
    {
        // Badge on shell mount (spec §5.22 "Poll /unread … on shell mount").
        $this->refreshUnread();
    }

    public function refreshUnread(): void
    {
        try {
            $this->unread = $this->api()->unreadNotificationCount();
        } catch (BackofficeApiException $e) {
            if ($e->status === 401) {
                throw $e; // central session-expiry handling
            }
            // A failed poll must never break the shell — keep the last known count.
        }
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;

        if ($this->open) {
            $this->loadItems();
        }
    }

    public function loadItems(): void
    {
        try {
            $this->items = NotificationInboxItems::unreadFirst(
                $this->api()->notifications(NotificationInboxItems::FETCH_LIMIT),
            );
            $this->loaded = true;
            $this->refreshUnread();
        } catch (BackofficeApiException $e) {
            if ($e->status === 401) {
                throw $e;
            }
            $this->items = [];
            $this->loaded = true;
        }
    }

    /**
     * Tap a row: mark read optimistically, then deep-link if the payload carries
     * a supported BO target. Unknown category/type is forward-compat room.
     */
    public function openItem(string $id)
    {
        $row = collect($this->items)->firstWhere('id', $id);

        // Optimistic local read so the UI updates before the server round-trip.
        $this->items = array_map(function (array $item) use ($id) {
            if (($item['id'] ?? null) === $id && ($item['status'] ?? '') === 'UNREAD') {
                $item['status'] = 'READ';
                $item['readAt'] = now()->toIso8601String();
            }
            return $item;
        }, $this->items);

        try {
            $this->api()->markNotificationRead($id);
        } catch (BackofficeApiException $e) {
            if ($e->status === 401) {
                throw $e;
            }
            // 403 (foreign) / 404 (gone) — refetch to reflect the truth, then stay put.
            $this->loadItems();

            return null;
        }

        $this->refreshUnread();

        $target = NotificationTargets::forRow($row, $this->currentPermissions());
        if ($target !== null) {
            $this->open = false;

            return $this->redirectRoute($target['route'], $target['params'], navigate: true);
        }

        return null;
    }

    public function markAllRead(): void
    {
        try {
            $this->api()->markAllNotificationsRead();
        } catch (BackofficeApiException $e) {
            if ($e->status === 401) {
                throw $e;
            }
        }

        // Refetch the list and badge (spec: poll /unread after read-all).
        $this->loadItems();
    }

    /**
     * @return array<int, string>
     */
    private function currentPermissions(): array
    {
        $permissions = session('bo_user.permissions', []);

        return is_array($permissions) ? $permissions : [];
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.notifications.notification-bell');
    }
};
?>

{{-- Poll the unread badge while idle (pull-only inbox, spec §5.22). 15s keeps the
     badge fresh without hammering — the backend poller itself runs every ~5s. --}}
<div
    class="notif-bell"
    x-data="{ open: @entangle('open') }"
    @keydown.escape.window="open = false"
    wire:poll.15s="refreshUnread"
>
    <button
        type="button"
        class="notif-bell-trigger btn btn-ghost btn-sm"
        wire:click="toggle"
        aria-label="Notifications"
        :aria-expanded="open"
    >
        <x-icon name="bell" size="16" />
        @if($unread > 0)
            <span class="notif-bell-badge">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>

    <div
        class="notif-panel"
        x-show="open"
        x-cloak
        x-transition.opacity
        @click.outside="open = false"
    >
        <div class="notif-panel-header">
            <span class="notif-panel-title">Notifications</span>
            @if($unread > 0)
                <button type="button" class="notif-mark-all" wire:click="markAllRead">
                    <x-icon name="check-double" size="13" /> Tout marquer comme lu
                </button>
            @endif
        </div>

        <div class="notif-panel-body" wire:loading.class="is-loading" wire:target="loadItems,markAllRead">
            @if(! $loaded)
                <div class="notif-empty">Chargement…</div>
            @elseif(empty($items))
                <div class="notif-empty">
                    <x-icon name="bell" size="20" />
                    <div>Aucune notification</div>
                </div>
            @else
                @foreach($items as $item)
                    @php $isUnread = ($item['status'] ?? '') === 'UNREAD'; @endphp
                    <button
                        type="button"
                        class="notif-row {{ $isUnread ? 'is-unread' : '' }}"
                        wire:click="openItem('{{ $item['id'] }}')"
                    >
                        @if($isUnread)<span class="notif-dot" aria-hidden="true"></span>@endif
                        <div class="notif-row-content">
                            <div class="notif-row-title">{{ $item['title'] ?? 'Notification' }}</div>
                            @if(!empty($item['body']))
                                <div class="notif-row-body">{{ $item['body'] }}</div>
                            @endif
                            <div class="notif-row-meta">
                                <span class="notif-row-cat">{{ \App\Support\BackofficeEnums::label($item['category'] ?? null) }}</span>
                                @if(!empty($item['createdAt']))
                                    <span>· {{ \Carbon\Carbon::parse($item['createdAt'])->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>
                    </button>
                @endforeach
            @endif
        </div>
    </div>
</div>
