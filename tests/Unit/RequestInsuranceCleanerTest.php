<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\RequestInsuranceCleaner;

class RequestInsuranceCleanerTest extends TestCase
{
    /** @test */
    public function it_can_chunk_clean_up(): void
    {
        // Arrange
        Config::set('requestInsurance.cleanChunkSize', 10); // Reduce chunk size, so many chunks are made

        // Create 555 requests that are a month old
        RequestInsurance::factory(555, ['completed_at' => Carbon::now()->subMonthNoOverflow()->toDateTimeString()])->create();

        // Create 111 requests that are from today
        RequestInsurance::factory(111, ['completed_at' => Carbon::now()->toDateTimeString()])->create();

        // Assert that they were all created
        $this->assertDatabaseCount(RequestInsurance::class, 666);

        // Act
        RequestInsuranceCleaner::cleanUp();

        // Assert
        $this->assertDatabaseCount(RequestInsurance::class, 111); // Only those from today should remain
    }
}
