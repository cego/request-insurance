<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Cego\RequestInsurance\Models\RequestInsurance;

class MigrateStateColumnsToRequestInsurance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        DB::transaction(function () {
            $baseQuery = RequestInsurance::query()->where('state', State::READY);

            $now = CarbonImmutable::now();

            $baseQuery->clone()->whereNotNull('completed_at')->update(['state' => State::COMPLETED, 'state_changed_at' => $now]);
            $baseQuery->clone()->whereNotNull('abandoned_at')->update(['state' => State::ABANDONED, 'state_changed_at' => $now]);
            $baseQuery->clone()->whereNotNull('paused_at')->update(['state' => State::FAILED, 'state_changed_at' => $now]);
            $baseQuery->clone()->whereNotNull('locked_at')->update(['state' => State::PENDING, 'state_changed_at' => $now]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        RequestInsurance::query()->update(['state' => State::READY]);
    }
}
