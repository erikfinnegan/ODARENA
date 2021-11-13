<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropDominionTickStates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('dominion_tick_states');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::create('dominion_tick_states', function (Blueprint $table) {
          $table->increments('id');
          $table->integer('dominion_id')->unsigned()->nullable();

          $table->timestamp('tick')->unsigned()->default(0);

          $table->integer('peasants')->unsigned()->default(0);
          $table->integer('prestige')->unsigned()->default(0);
          $table->integer('xp')->unsigned()->default(0);
          $table->integer('draft_rate')->unsigned()->default(0);
          $table->integer('morale')->unsigned()->default(0);
          $table->integer('spy_strength')->unsigned()->default(0);
          $table->integer('wizard_strength')->unsigned()->default(0);

          $table->integer('military_draftees')->unsigned()->default(0);
          $table->integer('military_unit1')->unsigned()->default(0);
          $table->integer('military_unit2')->unsigned()->default(0);
          $table->integer('military_unit3')->unsigned()->default(0);
          $table->integer('military_unit4')->unsigned()->default(0);
          $table->integer('military_spies')->unsigned()->default(0);
          $table->integer('military_wizards')->unsigned()->default(0);
          $table->integer('military_archmages')->unsigned()->default(0);

          $table->integer('land_plain')->unsigned()->default(0);
          $table->integer('land_mountain')->unsigned()->default(0);
          $table->integer('land_swamp')->unsigned()->default(0);
          $table->integer('land_forest')->unsigned()->default(0);
          $table->integer('land_hill')->unsigned()->default(0);
          $table->integer('land_water')->unsigned()->default(0);

          $table->integer('protection_ticks')->unsigned()->default(0);
          $table->integer('is_locked')->unsigned()->default(0);

          $table->foreign('dominion_id')->references('id')->on('dominions');

          $table->timestamps();
      });
    }
}
