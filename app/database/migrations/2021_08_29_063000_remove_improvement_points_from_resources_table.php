<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveImprovementPointsFromResourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('resources', function (Blueprint $table) {
          $table->dropColumn([
              'improvement_points',
              'buy_value',
              'sell_value',
          ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->integer('improvement_points')->unsigned()->default(0);
            $table->integer('buy_value')->unsigned()->default(0);
            $table->integer('sell_value')->unsigned()->default(0);
        });
    }
}
