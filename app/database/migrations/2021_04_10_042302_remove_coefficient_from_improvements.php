<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveCoefficientFromImprovements extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('improvements', function (Blueprint $table) {
            $table->dropColumn('coefficient');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('improvements', function (Blueprint $table) {
            $table->integer('coefficient')->default(4000);
        });
    }
}
