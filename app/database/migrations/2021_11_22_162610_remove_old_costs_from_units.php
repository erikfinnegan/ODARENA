<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveOldCostsFromUnits extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('units', function (Blueprint $table) {
          $table->dropColumn([
              'cost_gold',
              'cost_ore',
              'cost_food',
              'cost_mana',
              'cost_gems',
              'cost_lumber',
              'cost_prestige',
              'cost_champion',
              'cost_soul',
              'cost_blood',
              'cost_unit1',
              'cost_unit2',
              'cost_unit3',
              'cost_unit4',
              'cost_morale',
              'cost_spy_strength',
              'cost_brimmer',
              'cost_prisoner',
              'cost_horse',
              'cost_wizard_strength',
              'cost_peasant',
              'cost_spy',
              'cost_wizard',
              'cost_archmage',
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
        Schema::table('units', function (Blueprint $table) {
            $table->integer('cost_gold')->unsigned()->default(0);
            $table->integer('cost_ore')->unsigned()->default(0);
            $table->integer('cost_food')->unsigned()->default(0);
            $table->integer('cost_mana')->unsigned()->default(0);
            $table->integer('cost_gems')->unsigned()->default(0);
            $table->integer('cost_lumber')->unsigned()->default(0);
            $table->integer('cost_prestige')->unsigned()->default(0);
            $table->integer('cost_champion')->unsigned()->default(0);
            $table->integer('cost_soul')->unsigned()->default(0);
            $table->integer('cost_blood')->unsigned()->default(0);
            $table->integer('cost_unit1')->unsigned()->default(0);
            $table->integer('cost_unit2')->unsigned()->default(0);
            $table->integer('cost_unit3')->unsigned()->default(0);
            $table->integer('cost_unit4')->unsigned()->default(0);
            $table->integer('cost_morale')->unsigned()->default(0);
            $table->integer('cost_spy_strength')->unsigned()->default(0);
            $table->integer('cost_brimmer')->unsigned()->default(0);
            $table->integer('cost_prisoner')->unsigned()->default(0);
            $table->integer('cost_horse')->unsigned()->default(0);
            $table->integer('cost_wizard_strength')->unsigned()->default(0);
            $table->integer('cost_peasant')->unsigned()->default(0);
            $table->integer('cost_spy')->unsigned()->default(0);
            $table->integer('cost_wizard')->unsigned()->default(0);
            $table->integer('cost_archmage')->unsigned()->default(0);
        });
    }
}
