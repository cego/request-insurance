<?php

namespace Tests\Unit;

use Tests\TestCase;
use Carbon\Carbon;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;

class HasCompositeCreatedAtKeyTest extends TestCase
{
    public function test_update_query_includes_created_at_predicate(): void
    {
        $ri = RequestInsurance::factory()->create([
            'state'      => State::READY,
            'created_at' => Carbon::parse('2026-06-22 10:00:00.123456', 'UTC'),
        ]);

        $bindings = null;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$bindings) {
            if (str_starts_with(strtolower($query->sql), 'update')) {
                $bindings = $query->bindings;
            }
        });

        $ri->state = State::COMPLETED;
        $ri->save();

        // created_at must appear among the WHERE bindings of the UPDATE with full microsecond precision.
        // Normalize: Carbon objects to formatted string, raw strings as-is, preserving microseconds.
        $normalizedBindings = array_map(
            function ($b) {
                if ($b instanceof \DateTimeInterface) {
                    return $b->format('Y-m-d H:i:s.u');
                }
                return (string) $b;
            },
            $bindings
        );

        $this->assertContains('2026-06-22 10:00:00.123456', $normalizedBindings,
            'created_at with full microsecond precision must be present in UPDATE WHERE clause');
    }
}
