<?php

namespace Tests\Unit;

use App\Services\Mock\MockDataService;
use PHPUnit\Framework\TestCase;

class MockAuditFiltersTest extends TestCase
{
    public function test_audit_events_support_spec_filters(): void
    {
        $rows = MockDataService::auditEvents([
            'eventType' => 'WALLET_FROZEN',
            'actorId' => '11111111-0000-0000-0000-000000000001',
            'from' => '2026-05-04',
            'to' => '2026-05-04',
            'correlationId' => 'c8-vwx',
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('ev08', $rows[0]['id']);
    }
}
