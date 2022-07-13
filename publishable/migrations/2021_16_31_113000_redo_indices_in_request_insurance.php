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
            $table->dropIndex(['retry_at']);
            $table->dropIndex(['completed_at']);
            $table->dropIndex(['abandoned_at']);
            $table->dropIndex(['locked_at']);
            $table->dropIndex(['paused_at']);

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
            // Status:              ['response_code', 'created_at']
            //
            // Our most used interface queries are the following
            //
            // Date range:          ['created_at']
            // Active Rows:         ['paused_at', 'abandoned_at', 'completed_at']
            // Failed Rows:         ['paused_at', 'abandoned_at']
            // Completed:           ['completed_at', 'created_at']
            // Abandoned:           ['abandoned_at', 'created_at']
            $table->index(['paused_at', 'abandoned_at', 'completed_at', 'locked_at', 'retry_at', 'priority'], 'covering_index');  // Worker row locking query + Active Rows + Failed Rows
            $table->index(['url', 'created_at']);                                                                                 // Manual Url and date range
            $table->index(['response_code', 'created_at']);                                                                       // Manual - response_code
            $table->index(['completed_at', 'created_at']);                                                                        // Interface search - created_at
            $table->index(['abandoned_at', 'created_at']);                                                                        // Interface search - abandoned_at
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
        Schema::table('request_insurances', function (Blueprint $table) {
            $table->dropIndex('covering_index');
            $table->dropIndex(['url', 'created_at']);
            $table->dropIndex(['response_code', 'created_at']);
            $table->dropIndex(['completed_at', 'created_at']);
            $table->dropIndex(['abandoned_at', 'created_at']);

            $table->index(['retry_at']);
            $table->index(['completed_at']);
            $table->index(['abandoned_at']);
            $table->index(['locked_at']);
            $table->index(['paused_at']);
        });
    }
}
