<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEnabledFlagToImprovements extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('improvements', function (Blueprint $table) {
            $table->integer('enabled')->default(1)->after('exclusive_races');
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
          $table->dropColumn([
              'enabled'
          ]);
        });
    }
}
