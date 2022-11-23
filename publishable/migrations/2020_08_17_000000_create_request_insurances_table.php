<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRequestInsurancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('request_insurances', function (Blueprint $table) {
            $table->id();
            $table->integer('priority')->default(9999);
            $table->string('url');
            $table->string('method', 6);
            $table->text('headers');
            $table->longText('payload');
            $table->text('response_headers')->nullable()->default(null);
            $table->longText('response_body')->nullable()->default(null);
            $table->integer('response_code')->nullable()->default(null);
            $table->integer('retry_count')->default(0);
            $table->integer('retry_factor')->default(2);
            $table->integer('retry_cap')->default(3600);
            $table->timestamp('retry_at')->nullable()->default(null)->index();
            $table->timestamp('completed_at')->nullable()->default(null)->index();
            $table->timestamp('abandoned_at')->nullable()->default(null)->index();
            $table->timestamp('locked_at')->nullable()->default(null)->index();
            $table->timestamp('paused_at')->nullable()->default(null)->index();
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
        Schema::dropIfExists('request_insurances');
    }
}
