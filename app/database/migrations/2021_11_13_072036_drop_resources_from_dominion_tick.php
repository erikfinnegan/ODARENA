<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropResourcesFromDominionTick extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dominion_tick', function (Blueprint $table) {
          $table->dropColumn([
              'resource_gold',
              'resource_food',
              'resource_food_production',
              'resource_food_consumption',
              'resource_food_decay',
              'resource_lumber',
              'resource_lumber_production',
              'resource_lumber_rot',
              'resource_mana',
              'resource_mana_production',
              'resource_mana_drain',
              'resource_ore',
              'resource_gems',
              'resource_tech',
              'resource_champion',
              'resource_soul',
              'resource_blood',
              'resource_food_contribution',
              'resource_mana_contribution',
              'resource_mana_contributed',
              'resource_food_contributed',
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
        Schema::table('dominion_tick', function (Blueprint $table) {
          $table->integer('resource_gold')->unsigned()->default(0);
          $table->integer('resource_food')->unsigned()->default(0);
          $table->integer('resource_food_production')->unsigned()->default(0);
          $table->integer('resource_food_consumption')->unsigned()->default(0);
          $table->integer('resource_lumber')->unsigned()->default(0);
          $table->integer('resource_lumber_production')->unsigned()->default(0);
          $table->integer('resource_lumber_rot')->unsigned()->default(0);
          $table->integer('resource_mana')->unsigned()->default(0);
          $table->integer('resource_mana_production')->unsigned()->default(0);
          $table->integer('resource_mana_drain')->unsigned()->default(0);
          $table->integer('resource_ore')->unsigned()->default(0);
          $table->integer('resource_gems')->unsigned()->default(0);
          $table->integer('resource_tech')->unsigned()->default(0);
          $table->integer('resource_champion')->unsigned()->default(0);
          $table->integer('resource_soul')->unsigned()->default(0);
          $table->integer('resource_blood')->unsigned()->default(0);
          $table->integer('resource_food_contribution')->unsigned()->default(0);
          $table->integer('resource_mana_contribution')->unsigned()->default(0);
          $table->integer('resource_mana_contributed')->unsigned()->default(0);
          $table->integer('resource_food_contributed')->unsigned()->default(0);
        });
    }
}
