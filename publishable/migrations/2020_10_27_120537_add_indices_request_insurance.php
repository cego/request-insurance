<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndicesRequestInsurance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('request_insurance_logs', function (Blueprint $table) {
            $table->index("request_insurance_id");
            $table->index("created_at");
        });

        Schema::table('request_insurances', function (Blueprint $table) {
            $table->index("created_at");
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
