<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRequestInsuranceEditsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('request_insurance_edits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_insurance_id');
            $table->integer('required_number_of_approvals')->default(1);
            $table->integer('old_priority');
            $table->integer('new_priority');
            $table->text('old_url');
            $table->text('new_url');
            $table->text('old_method');
            $table->text('new_method');
            $table->text('old_headers')->nullable()->default(null);
            $table->text('new_headers')->nullable()->default(null);
            $table->longText('old_payload')->nullable()->default(null);
            $table->longText('new_payload')->nullable()->default(null);
            $table->text('old_encrypted_fields')->nullable()->default(null);
            $table->text('new_encrypted_fields')->nullable()->default(null);
            $table->text('admin_user');
            $table->timestamp('applied_at')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('request_insurance_edits');
    }
}
