<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RedoIndicesInRequestInsurance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('request_insurances', function (Blueprint $table) {
            // First we delete all the unnecessary single indexes
            $table->dropIndex('retry_at');
            $table->dropIndex('completed_at');
            $table->dropIndex('retry_at');
            $table->dropIndex('abandoned_at');
            $table->dropIndex('paused_at');

            // Our most used package queries are the following:
            //
            // Worker row locking:  ['paused_at', 'abandoned_at', 'completed_at', 'locked_at', 'retry_at', 'priority']
            // Active Rows:         ['paused_at', 'abandoned_at', 'completed_at']
            // Failed Rows:         ['paused_at', 'abandoned_at']
            //
            // Our most used manual queries are the following
            //
            // Date range:          ['created_at']
            // Url and date range:  ['url', 'created_at']
            // Failed Rows:         ['paused_at', 'abandoned_at']
            //
            // Our most used queries for the interface are the following
            //
            // Date range:          ['created_at']
            // Active Rows:         ['paused_at', 'abandoned_at', 'completed_at']
            // Failed Rows:         ['paused_at', 'abandoned_at']
            // Completed:           ['completed_at', 'created_at']
            // Abandoned:           ['abandoned_at', 'created_at']
            $table->index(['completed_at', 'abandoned_at', 'paused_at', 'locked_at', 'retry_at', 'priority']);  // Worker row locking query + Active Rows + Failed Rows
            $table->index(['url', 'created_at']);                                                               // Url and date range
            $table->index(['completed_at', 'created_at']);                                                      // Interface search - created_at
            $table->index(['abandoned_at', 'created_at']);                                                      // Interface search - abandoned_at
            // An index on created_at already exists
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('request_insurance_logs', function (Blueprint $table) {
            $table->dropIndex("request_insurance_logs_request_insurance_id_index");
            $table->dropIndex("request_insurance_logs_created_at_index");
        });

        Schema::table('request_insurances', function (Blueprint $table) {
            $table->dropIndex("request_insurances_created_at_index");
        });
    }
}
