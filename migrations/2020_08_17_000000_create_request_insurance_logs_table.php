<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRequestInsuranceLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('request_insurance_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_insurance_id');
            $table->text('response_headers');
            $table->longText('response_body');
            $table->integer('response_code');
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
        Schema::dropIfExists('request_insurance_logs');
    }
}
