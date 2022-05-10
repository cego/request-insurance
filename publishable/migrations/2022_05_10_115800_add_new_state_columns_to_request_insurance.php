<?php

use Illuminate\Support\Facades\Schema;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNewStateColumnsToRequestInsurance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('request_insurances', function (Blueprint $table) {
            $table->enum('state', State::getAll())->default(State::ACTIVE)->after('retry_at');
            $table->timestamp('state_changed_at', State::getAll())->after('state')->useCurrent();

            $table->index(['state', 'created_at']);
            $table->index(['state', 'priority']);
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
