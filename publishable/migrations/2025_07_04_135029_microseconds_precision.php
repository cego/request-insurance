<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MicrosecondsPrecision extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('request_insurances', function (Blueprint $table) {
            $table->timestamp('retry_at', 6)->nullable()->change();
            $table->timestamp('state_changed_at', 6)->nullable()->change();
            $table->timestamp('created_at', 6)->nullable()->change();
            $table->timestamp('updated_at', 6)->nullable()->change();
        });

        Schema::table('request_insurance_edits', function (Blueprint $table) {
            $table->timestamp('applied_at', 6)->nullable()->change();
            $table->timestamp('created_at', 6)->nullable()->change();
            $table->timestamp('updated_at', 6)->nullable()->change();
        });

        Schema::table('request_insurance_edit_approvals', function (Blueprint $table) {
            $table->timestamp('created_at', 6)->nullable()->change();
            $table->timestamp('updated_at', 6)->nullable()->change();
        });

        Schema::table('request_insurance_logs', function (Blueprint $table) {
            $table->timestamp('created_at', 6)->nullable()->change();
            $table->timestamp('updated_at', 6)->nullable()->change();
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
            $table->timestamp('retry_at', 0)->nullable()->change();
            $table->timestamp('state_changed_at', 0)->nullable()->change();
            $table->timestamp('created_at', 0)->nullable()->change();
            $table->timestamp('updated_at', 0)->nullable()->change();
        });

        Schema::table('request_insurance_edits', function (Blueprint $table) {
            $table->timestamp('applied_at', 0)->nullable()->change();
            $table->timestamp('created_at', 0)->nullable()->change();
            $table->timestamp('updated_at', 0)->nullable()->change();
        });

        Schema::table('request_insurance_edit_approvals', function (Blueprint $table) {
            $table->timestamp('created_at', 0)->nullable()->change();
            $table->timestamp('updated_at', 0)->nullable()->change();
        });

        Schema::table('request_insurance_logs', function (Blueprint $table) {
            $table->timestamp('created_at', 0)->nullable()->change();
            $table->timestamp('updated_at', 0)->nullable()->change();
        });
    }
};
