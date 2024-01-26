<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropRedundantRequestInsurancesIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('request_insurances', function (Blueprint $table) {
            $table->dropIndex('request_insurances_abandoned_at_created_at_index');
            $table->dropIndex('request_insurances_completed_at_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
    }
}
