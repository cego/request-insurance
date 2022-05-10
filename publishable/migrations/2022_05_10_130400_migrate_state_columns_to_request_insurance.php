<?php

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
            $baseQuery = RequestInsurance::query()->where('state', State::ACTIVE);

            $baseQuery->clone()->whereNotNull('completed_at')->update(['state' => State::COMPLETED]);
            $baseQuery->clone()->whereNotNull('abandoned_at')->update(['state' => State::ABANDONED]);
            $baseQuery->clone()->whereNotNull('paused_at')->update(['state' => State::FAILED]);
            $baseQuery->clone()->whereNotNull('locked_at')->update(['state' => State::PENDING]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('request_insurances');
    }
}
