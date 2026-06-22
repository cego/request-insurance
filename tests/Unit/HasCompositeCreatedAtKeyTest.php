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
            'created_at' => Carbon::parse('2026-06-22 10:00:00', 'UTC'),
        ]);

        $bindings = null;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$bindings) {
            if (str_starts_with(strtolower($query->sql), 'update')) {
                $bindings = $query->bindings;
            }
        });

        $ri->state = State::COMPLETED;
        $ri->save();

        // created_at must appear among the WHERE bindings of the UPDATE.
        // Normalize: Carbon objects and raw datetime strings both truncate to second precision.
        $this->assertContains('2026-06-22 10:00:00', array_map(
            function ($b) {
                if ($b instanceof \DateTimeInterface) {
                    return $b->format('Y-m-d H:i:s');
                }
                $s = (string) $b;
                // Trim microseconds suffix if present (e.g. "2026-06-22 10:00:00.000000" -> "2026-06-22 10:00:00")
                return preg_replace('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\.\d+$/', '$1', $s);
            },
            $bindings
        ));
    }
}
