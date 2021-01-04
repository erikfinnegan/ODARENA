<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameDiamondMineToGemMineInDominionTickStates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dominion_tick_states', function (Blueprint $table) {
            $table->renameColumn('building_diamond_mine', 'building_gem_mine');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dominion_tick_states', function (Blueprint $table) {
            $table->renameColumn('building_gem_mine', 'building_diamond_mine');
        });
    }
}
