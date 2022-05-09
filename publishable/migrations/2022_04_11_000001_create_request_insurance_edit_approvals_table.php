<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRequestInsuranceEditApprovalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('request_insurance_edit_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_insurance_edit_id');
            $table->text('approver_admin_user');
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
        Schema::dropIfExists('request_insurance_edit_approvals');
    }
}
