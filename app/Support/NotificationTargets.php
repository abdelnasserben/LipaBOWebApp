<?php

namespace App\Support;

use App\Enums\Backoffice\NotificationCategory;

final class NotificationTargets
{
    /**
     * @param array<string, mixed>|null $row
     * @param array<int, string> $permissions
     * @return array{route: string, params: array<string, string>}|null
     */
    public static function forRow(?array $row, array $permissions = []): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        $category = strtoupper(trim((string) ($row['category'] ?? '')));
        $data = self::notificationData($row);
        $type = strtoupper(trim((string) ($data['type'] ?? '')));

        return match ($category) {
            NotificationCategory::BILL_PAYMENT->value => self::billPaymentTarget($data, $type),
            NotificationCategory::APPROVAL->value => self::approvalTarget($data, $type, $permissions),
            NotificationCategory::RECONCILIATION->value => self::reconciliationTarget($data, $type),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $data
     * @return array{route: string, params: array<string, string>}|null
     */
    private static function billPaymentTarget(array $data, string $type): ?array
    {
        if ($type !== 'SERVICE_PAYMENT_QUEUED') {
            return null;
        }

        $billPaymentId = self::stringData($data, 'billPaymentId');

        return $billPaymentId !== null
            ? ['route' => 'bill-payments', 'params' => ['open' => $billPaymentId]]
            : null;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $permissions
     * @return array{route: string, params: array<string, string>}|null
     */
    private static function approvalTarget(array $data, string $type, array $permissions): ?array
    {
        $approvalId = self::stringData($data, 'approvalId');
        if ($approvalId === null) {
            return null;
        }

        $isPendingRequest = $type === 'APPROVAL_REQUESTED';
        $isMakerDecision = in_array($type, ['APPROVAL_APPROVED', 'APPROVAL_REJECTED'], true);
        $canOpenDecision = ApprovalPermissions::canActOn(self::stringData($data, 'approvalType'), $permissions);

        if (! $isPendingRequest && (! $isMakerDecision || ! $canOpenDecision)) {
            return null;
        }

        return ['route' => 'approvals', 'params' => ['open' => $approvalId]];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{route: string, params: array<string, string>}|null
     */
    private static function reconciliationTarget(array $data, string $type): ?array
    {
        if ($type !== 'RECONCILIATION_INCIDENT_OPENED') {
            return null;
        }

        $incidentId = self::stringData($data, 'incidentId');

        return $incidentId !== null
            ? ['route' => 'reconciliation', 'params' => ['tab' => 'incidents', 'open' => $incidentId]]
            : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function notificationData(array $row): array
    {
        $data = $row['data'] ?? null;
        if (is_string($data) && $data !== '') {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : null;
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function stringData(array $data, string $key): ?string
    {
        $value = trim((string) ($data[$key] ?? ''));

        return $value !== '' ? $value : null;
    }
}
