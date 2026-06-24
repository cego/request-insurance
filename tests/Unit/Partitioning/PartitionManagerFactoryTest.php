<?php

namespace Tests\Unit\Partitioning;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Partitioning\PartitionManagerFactory;
use Cego\RequestInsurance\Partitioning\UnsupportedPartitionManager;

class PartitionManagerFactoryTest extends TestCase
{
    public function test_sqlite_resolves_to_unsupported_manager(): void
    {
        $manager = PartitionManagerFactory::for(DB::connection());

        $this->assertInstanceOf(UnsupportedPartitionManager::class, $manager);
        $this->assertFalse($manager->isSupported());
    }
}
