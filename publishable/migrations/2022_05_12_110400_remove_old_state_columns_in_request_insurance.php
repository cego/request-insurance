<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveOldStateColumnsInRequestInsurance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('request_insurances', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });

        Schema::table('request_insurances', function (Blueprint $table) {
            $table->dropColumn('paused_at');
        });

        Schema::table('request_insurances', function (Blueprint $table) {
            $table->dropColumn('locked_at');
        });

        Schema::table('request_insurances', function (Blueprint $table) {
            $table->dropColumn('abandoned_at');
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
            $table->timestamp('completed_at')->nullable()->default(null);
            $table->timestamp('abandoned_at')->nullable()->default(null);
            $table->timestamp('locked_at')->nullable()->default(null);
            $table->timestamp('paused_at')->nullable()->default(null);

            $table->index(['paused_at', 'abandoned_at', 'completed_at', 'locked_at', 'retry_at', 'priority'], 'covering_index');  // Worker row locking query + Active Rows + Failed Rows
            $table->index(['completed_at', 'created_at']);                                                                        // Interface search - created_at
            $table->index(['abandoned_at', 'created_at']);                                                                        // Interface search - abandoned_at
        });
    }
}
