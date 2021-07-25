<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLandTargetAndTickCounterToRounds extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->integer('land_target')->default(8000)->after('name');
            $table->integer('ticks')->default(0)->after('land_target');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rounds', function (Blueprint $table) {
          $table->dropColumn([
              'land_target',
              'ticks'
          ]);
        });
    }
}
