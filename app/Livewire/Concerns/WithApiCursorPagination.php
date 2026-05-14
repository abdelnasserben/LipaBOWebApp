<?php

namespace App\Livewire\Concerns;

trait WithApiCursorPagination
{
    public array $cursorPaginators = [];

    protected function cursorPageQuery(string $key, int $limit = 20): array
    {
        $state = $this->cursorPaginators[$key] ?? [];

        return [
            'cursor' => $state['cursor'] ?? null,
            'limit' => $limit,
        ];
    }

    protected function cursorPaginator(string $key, array $page, int $shownCount, string $label): array
    {
        $state = $this->cursorPaginators[$key] ?? [];
        $pagination = is_array($page['pagination'] ?? null) ? $page['pagination'] : [];

        $state['page'] = max(1, (int) ($state['page'] ?? 1));
        $state['previousCursors'] = is_array($state['previousCursors'] ?? null) ? $state['previousCursors'] : [];
        $state['nextCursor'] = is_string($pagination['nextCursor'] ?? null) ? $pagination['nextCursor'] : null;
        $state['hasMore'] = (bool) ($pagination['hasMore'] ?? false);

        $this->cursorPaginators[$key] = $state;

        return [
            'key' => $key,
            'label' => $label,
            'shown' => $shownCount,
            'page' => $state['page'],
            'hasPrevious' => count($state['previousCursors']) > 0,
            'hasMore' => $state['hasMore'] && $state['nextCursor'] !== null,
        ];
    }

    public function nextCursorPage(string $key): void
    {
        $state = $this->cursorPaginators[$key] ?? [];

        if (! ($state['hasMore'] ?? false) || ! is_string($state['nextCursor'] ?? null)) {
            return;
        }

        $history = is_array($state['previousCursors'] ?? null) ? $state['previousCursors'] : [];
        $history[] = $state['cursor'] ?? null;

        $this->cursorPaginators[$key] = [
            'cursor' => $state['nextCursor'],
            'previousCursors' => $history,
            'page' => max(1, (int) ($state['page'] ?? 1)) + 1,
        ];
    }

    public function previousCursorPage(string $key): void
    {
        $state = $this->cursorPaginators[$key] ?? [];
        $history = is_array($state['previousCursors'] ?? null) ? $state['previousCursors'] : [];

        if ($history === []) {
            return;
        }

        $previousCursor = array_pop($history);

        $this->cursorPaginators[$key] = [
            'cursor' => $previousCursor,
            'previousCursors' => $history,
            'page' => max(1, (int) ($state['page'] ?? 1) - 1),
        ];
    }

    public function resetCursorPage(?string $key = null): void
    {
        if ($key === null) {
            $this->cursorPaginators = [];

            return;
        }

        unset($this->cursorPaginators[$key]);
    }

    protected function resetCursorPages(array $keys): void
    {
        foreach ($keys as $key) {
            $this->resetCursorPage($key);
        }
    }
}
