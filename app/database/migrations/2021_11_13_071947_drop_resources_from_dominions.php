<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropResourcesFromDominions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dominions', function (Blueprint $table) {
          $table->dropColumn([
              'resource_gold',
              'resource_food',
              'resource_lumber',
              'resource_mana',
              'resource_ore',
              'resource_gems',
              'resource_tech',
              'resource_champion',
              'resource_soul',
              'resource_blood',
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
        Schema::table('dominions', function (Blueprint $table) {
            $table->integer('resource_gold')->unsigned()->default(0);
            $table->integer('resource_food')->unsigned()->default(0);
            $table->integer('resource_lumber')->unsigned()->default(0);
            $table->integer('resource_mana')->unsigned()->default(0);
            $table->integer('resource_ore')->unsigned()->default(0);
            $table->integer('resource_gems')->unsigned()->default(0);
            $table->integer('resource_tech')->unsigned()->default(0);
            $table->integer('resource_champion')->unsigned()->default(0);
            $table->integer('resource_soul')->unsigned()->default(0);
            $table->integer('resource_blood')->unsigned()->default(0);
        });
    }
}
