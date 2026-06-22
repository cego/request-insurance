<?php

namespace Tests\Unit;

use Tests\TestCase;
use Carbon\Carbon;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Models\RequestInsurance;

class WorkerPartitionPruningTest extends TestCase
{
    public function test_acquire_lock_update_constrains_created_at(): void
    {
        RequestInsurance::factory(3)->create([
            'state'      => State::READY,
            'created_at' => Carbon::parse('2026-06-22 09:00:00', 'UTC'),
        ]);

        $sawCreatedAtBound = false;
        DB::listen(function ($query) use (&$sawCreatedAtBound) {
            $sql = strtolower($query->sql);
            if (str_starts_with($sql, 'update') && str_contains($sql, 'created_at')) {
                $sawCreatedAtBound = true;
            }
        });

        $worker = $this->getWorker(100);
        $worker->acquireLockOnRowsToProcess();

        $this->assertTrue($sawCreatedAtBound, 'PENDING update must constrain created_at for partition pruning');
    }
}
