<?php

namespace Tests\Unit\Partitioning;

use Tests\TestCase;
use InvalidArgumentException;
use Cego\RequestInsurance\Partitioning\PartitionGranularity;

class PartitionGranularityTest extends TestCase
{
    public function test_all_returns_known_granularities(): void
    {
        $this->assertSame(['daily', 'weekly', 'monthly'], PartitionGranularity::all());
    }

    public function test_assert_valid_passes_for_known(): void
    {
        PartitionGranularity::assertValid('daily');
        $this->expectNotToPerformAssertions();
    }

    public function test_assert_valid_throws_for_unknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PartitionGranularity::assertValid('hourly');
    }
}
