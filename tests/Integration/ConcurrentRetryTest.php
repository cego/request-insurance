<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\FailedRequestMover;
use Cego\RequestInsurance\Models\RequestInsurance;

class ConcurrentRetryTest extends IntegrationTestCase
{
    public function test_two_concurrent_retries_restore_exactly_one_active_row(): void
    {
        if ( ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for the concurrency test');
        }

        $requestInsurance = RequestInsurance::factory()->create(['state' => State::FAILED]);
        FailedRequestMover::moveToFailed($requestInsurance);

        // RefreshDatabase wraps the test in a transaction. Commit the fixture so
        // independent child-process connections can contend for the same row.
        DB::connection()->commit();

        $barrier = tempnam(sys_get_temp_dir(), 'ri-retry-barrier-');
        $results = [
            tempnam(sys_get_temp_dir(), 'ri-retry-result-'),
            tempnam(sys_get_temp_dir(), 'ri-retry-result-'),
        ];
        unlink($barrier);
        $children = [];

        try {
            foreach ($results as $result) {
                $pid = pcntl_fork();

                if ($pid === -1) {
                    $this->fail('Could not fork retry test process');
                }

                if ($pid === 0) {
                    while ( ! file_exists($barrier)) {
                        usleep(1000);
                    }

                    try {
                        DB::purge();
                        DB::reconnect();
                        FailedRequestMover::restoreToActive($requestInsurance->id);
                        file_put_contents($result, 'ok');
                        exit(0);
                    } catch (\Throwable $throwable) {
                        file_put_contents($result, $throwable::class . ': ' . $throwable->getMessage());
                        exit(1);
                    }
                }

                $children[] = $pid;
            }

            touch($barrier);

            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
                $this->assertTrue(pcntl_wifexited($status));
                $this->assertSame(0, pcntl_wexitstatus($status));
            }

            DB::purge();
            DB::reconnect();

            $this->assertSame(1, DB::table(FailedRequestMover::mainTable())->where('id', $requestInsurance->id)->count());
            $this->assertSame(0, DB::table(FailedRequestMover::failedTable())->where('id', $requestInsurance->id)->count());
            $this->assertSame(['ok', 'ok'], array_map(fn (string $result) => file_get_contents($result), $results));
        } finally {
            @unlink($barrier);

            foreach ($results as $result) {
                @unlink($result);
            }
        }
    }
}
