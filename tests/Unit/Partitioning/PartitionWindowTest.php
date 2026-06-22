<?php

namespace Tests\Unit\Partitioning;

use Tests\TestCase;
use Carbon\CarbonImmutable;
use Cego\RequestInsurance\Partitioning\PartitionWindow;
use Cego\RequestInsurance\Partitioning\PartitionGranularity;

class PartitionWindowTest extends TestCase
{
    public function test_daily_window_name_and_bounds(): void
    {
        $w = PartitionWindow::forDate(CarbonImmutable::parse('2026-06-22 13:45:00', 'UTC'), PartitionGranularity::DAILY);
        $this->assertSame('p20260622', $w->name());
        $this->assertSame('2026-06-22 00:00:00', $w->start()->toDateTimeString());
        $this->assertSame('2026-06-23 00:00:00', $w->end()->toDateTimeString());
    }

    public function test_weekly_window_uses_iso_week(): void
    {
        $w = PartitionWindow::forDate(CarbonImmutable::parse('2026-06-22', 'UTC'), PartitionGranularity::WEEKLY);
        $this->assertSame('p2026w26', $w->name());
        $this->assertSame('2026-06-22 00:00:00', $w->start()->toDateTimeString()); // Monday
        $this->assertSame('2026-06-29 00:00:00', $w->end()->toDateTimeString());
    }

    public function test_monthly_window(): void
    {
        $w = PartitionWindow::forDate(CarbonImmutable::parse('2026-06-22', 'UTC'), PartitionGranularity::MONTHLY);
        $this->assertSame('p202606', $w->name());
        $this->assertSame('2026-06-01 00:00:00', $w->start()->toDateTimeString());
        $this->assertSame('2026-07-01 00:00:00', $w->end()->toDateTimeString());
    }

    public function test_range_is_distinct_and_ascending(): void
    {
        $windows = PartitionWindow::range(
            CarbonImmutable::parse('2026-06-22', 'UTC'),
            CarbonImmutable::parse('2026-06-24', 'UTC'),
            PartitionGranularity::DAILY
        );
        $names = array_map(fn (PartitionWindow $w) => $w->name(), $windows);
        $this->assertSame(['p20260622', 'p20260623', 'p20260624'], $names);
    }
}
