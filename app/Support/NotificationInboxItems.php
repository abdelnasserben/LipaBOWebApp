<?php

namespace App\Support;

final class NotificationInboxItems
{
    /**
     * Shared notifications endpoint max from spec §5.22.
     */
    public const FETCH_LIMIT = 100;

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public static function unreadFirst(array $items): array
    {
        usort($items, function (array $left, array $right): int {
            $leftUnread = self::isUnread($left);
            $rightUnread = self::isUnread($right);

            if ($leftUnread !== $rightUnread) {
                return $leftUnread ? -1 : 1;
            }

            return self::timestamp($right) <=> self::timestamp($left);
        });

        return array_values($items);
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function isUnread(array $item): bool
    {
        return strtoupper(trim((string) ($item['status'] ?? ''))) === 'UNREAD';
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function timestamp(array $item): int
    {
        $timestamp = strtotime((string) ($item['createdAt'] ?? ''));

        return $timestamp === false ? 0 : $timestamp;
    }
}
