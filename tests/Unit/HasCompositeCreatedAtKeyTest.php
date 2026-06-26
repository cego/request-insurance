<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;

class HasCompositeCreatedAtKeyTest extends TestCase
{
    /**
     * The trait adds created_at to the save predicate (so writes prune to a single
     * partition) using getRawOriginal — the full microsecond value. This guards
     * both halves: the predicate is present in the UPDATE, and using the raw
     * original means it matches the stored row (getOriginal would truncate the
     * microseconds and match zero rows).
     */
    public function test_update_includes_microsecond_created_at_predicate_and_persists(): void
    {
        $ri = RequestInsurance::factory()->create([
            'state'      => State::READY,
            'created_at' => Carbon::parse('2026-06-22 10:00:00.123456', 'UTC'),
        ]);

        $bindings = null;
        DB::listen(function ($query) use (&$bindings) {
            if (str_starts_with(strtolower($query->sql), 'update')) {
                $bindings = $query->bindings;
            }
        });

        $ri->state = State::COMPLETED;
        $ri->save();

        $normalized = array_map(
            fn ($b) => $b instanceof \DateTimeInterface ? $b->format('Y-m-d H:i:s.u') : (string) $b,
            $bindings
        );

        $this->assertContains('2026-06-22 10:00:00.123456', $normalized, 'created_at must be in the UPDATE WHERE clause with full microseconds');
        $this->assertSame(State::COMPLETED, RequestInsurance::find($ri->id)->state, 'the UPDATE must match the row and persist');
    }
}
