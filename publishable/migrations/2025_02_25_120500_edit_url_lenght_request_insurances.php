<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class EditUrlLengthRequestInsurances extends Migration
{    /**
 * Run the migrations.
 *
 * @return void
 */
    public function up(): void
    {
        Schema::table('request_insurances', function (Blueprint $table) {
            $table->dropIndex(['url', 'created_at']);
            $table->text('url')->change();
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
            $table->string('url')->change();
            $table->index(['url', 'created_at']);
        });
    }
};
