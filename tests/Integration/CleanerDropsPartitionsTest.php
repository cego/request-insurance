<?php

namespace Tests\Integration;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\RequestInsuranceCleaner;

class CleanerDropsPartitionsTest extends IntegrationTestCase
{
    public function test_cleanup_drops_aged_partitions_instead_of_deleting_rows(): void
    {
        $this->assertStartsUnpartitioned();

        // Create 3 terminal rows that are 40 days old — well outside the 14-day retention window.
        RequestInsurance::factory(3)->create(['state' => State::COMPLETED, 'created_at' => Carbon::now('UTC')->subDays(40)]);

        $this->runPartitionMigration();

        // Capture any row-level DELETE statements fired against the parent table
        // during cleanUp. On a partitioned driver cleanUp should DROP PARTITION,
        // not issue individual DELETEs.
        $deletes = [];
        DB::listen(static function ($query) use (&$deletes) {
            $sql = strtolower($query->sql);

            if (str_contains($sql, 'delete') && str_contains($sql, 'request_insurances')) {
                $deletes[] = $query->sql;
            }
        });

        RequestInsuranceCleaner::cleanUp();

        // 1) No row-level DELETE was issued against request_insurances.
        $this->assertEmpty($deletes, 'Expected DROP PARTITION, not row-level DELETE — got: ' . implode('; ', $deletes));

        // 2) The 40-day-old rows are gone (partition was dropped entirely).
        $this->assertSame(0, DB::table('request_insurances')->where('created_at', '<', Carbon::now('UTC')->subDays(30))->count());

        // 3) Structural assertion: the aged partition no longer exists in the
        //    database catalog (confirms DROP PARTITION, not row deletion).
        if ($this->driverName() === 'mysql') {
            $agedMonth = Carbon::now('UTC')->subDays(40)->format('Y-m');
            $remaining = DB::select(
                "SELECT partition_name FROM information_schema.partitions WHERE table_schema = DATABASE() AND table_name = 'request_insurances' AND partition_name IS NOT NULL AND partition_description LIKE ?",
                ["%{$agedMonth}%"]
            );
            $this->assertEmpty($remaining, 'Aged MySQL partition must be dropped from information_schema after cleanUp');
        } else {
            // pgsql: child partition tables for the aged day must no longer exist
            $agedDay = Carbon::now('UTC')->subDays(40)->format('Ymd');
            $childName = "request_insurances_p{$agedDay}";
            $row = DB::selectOne('SELECT 1 FROM pg_class WHERE relname = ?', [$childName]);
            $this->assertNull($row, "Aged Postgres child partition {$childName} must be dropped from pg_class after cleanUp");
        }
    }
}
