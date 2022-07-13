<?php

use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\Schema;
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
            $table->enum('state', State::getAll())->default(State::READY)->after('retry_at');
            $table->timestamp('state_changed_at')->after('state')->nullable()->default(null);

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
        Schema::table('request_insurances', function (Blueprint $table) {
            $table->dropColumn('state');
        });

        Schema::table('request_insurances', function (Blueprint $table) {
            $table->dropColumn('state_changed_at');
        });
    }
}
