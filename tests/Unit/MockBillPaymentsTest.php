<?php

namespace Tests\Unit;

use App\Services\Mock\MockDataService;
use PHPUnit\Framework\TestCase;

class MockBillPaymentsTest extends TestCase
{
    public function test_bill_payments_are_listed_newest_first(): void
    {
        $rows = MockDataService::billPayments();

        $createdAt = array_column($rows, 'createdAt');
        $sorted = $createdAt;
        rsort($sorted, SORT_STRING);

        $this->assertSame($sorted, $createdAt);
    }
}
