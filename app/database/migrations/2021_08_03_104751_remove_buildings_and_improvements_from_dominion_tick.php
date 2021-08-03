<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveBuildingsAndImprovementsFromDominionTick extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dominion_tick', function (Blueprint $table) {
            $table->dropColumn('building_home');
            $table->dropColumn('building_alchemy');
            $table->dropColumn('building_farm');
            $table->dropColumn('building_smithy');
            $table->dropColumn('building_masonry');
            $table->dropColumn('building_ore_mine');
            $table->dropColumn('building_gryphon_nest');
            $table->dropColumn('building_tower');
            $table->dropColumn('building_wizard_guild');
            $table->dropColumn('building_temple');
            $table->dropColumn('building_gem_mine');
            $table->dropColumn('building_school');
            $table->dropColumn('building_lumberyard');
            $table->dropColumn('building_forest_haven');
            $table->dropColumn('building_factory');
            $table->dropColumn('building_guard_tower');
            $table->dropColumn('building_shrine');
            $table->dropColumn('building_barracks');
            $table->dropColumn('building_dock');
            $table->dropColumn('building_ziggurat');
            $table->dropColumn('building_mycelia');
            $table->dropColumn('building_tissue');



            $table->dropColumn('improvement_markets');
            $table->dropColumn('improvement_keep');
            $table->dropColumn('improvement_forges');
            $table->dropColumn('improvement_walls');
            $table->dropColumn('improvement_armory');
            $table->dropColumn('improvement_infirmary');
            $table->dropColumn('improvement_workshops');
            $table->dropColumn('improvement_observatory');
            $table->dropColumn('improvement_cartography');
            $table->dropColumn('improvement_towers');
            $table->dropColumn('improvement_spires');
            $table->dropColumn('improvement_hideouts');
            $table->dropColumn('improvement_granaries');
            $table->dropColumn('improvement_harbor');
            $table->dropColumn('improvement_forestry');
            $table->dropColumn('improvement_refinery');
            $table->dropColumn('improvement_tissue');
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
            $table->unsignedInteger('building_home')->default(0);
            $table->unsignedInteger('building_alchemy')->default(0);
            $table->unsignedInteger('building_farm')->default(0);
            $table->unsignedInteger('building_smithy')->default(0);
            $table->unsignedInteger('building_masonry')->default(0);
            $table->unsignedInteger('building_ore_mine')->default(0);
            $table->unsignedInteger('building_gryphon_nest')->default(0);
            $table->unsignedInteger('building_tower')->default(0);
            $table->unsignedInteger('building_wizard_guild')->default(0);
            $table->unsignedInteger('building_temple')->default(0);
            $table->unsignedInteger('building_gem_mine')->default(0);
            $table->unsignedInteger('building_school')->default(0);
            $table->unsignedInteger('building_lumberyard')->default(0);
            $table->unsignedInteger('building_forest_haven')->default(0);
            $table->unsignedInteger('building_factory')->default(0);
            $table->unsignedInteger('building_guard_tower')->default(0);
            $table->unsignedInteger('building_shrine')->default(0);
            $table->unsignedInteger('building_barracks')->default(0);
            $table->unsignedInteger('building_dock')->default(0);
            $table->unsignedInteger('building_ziggurat')->default(0);
            $table->unsignedInteger('building_mycelia')->default(0);
            $table->unsignedInteger('building_tissue')->default(0);


            $table->unsignedInteger('improvement_markets')->default(0);
            $table->unsignedInteger('improvement_keep')->default(0);
            $table->unsignedInteger('improvement_forges')->default(0);
            $table->unsignedInteger('improvement_walls')->default(0);
            $table->unsignedInteger('improvement_armory')->default(0);
            $table->unsignedInteger('improvement_infirmary')->default(0);
            $table->unsignedInteger('improvement_workshops')->default(0);
            $table->unsignedInteger('improvement_observatory')->default(0);
            $table->unsignedInteger('improvement_cartography')->default(0);
            $table->unsignedInteger('improvement_towers')->default(0);
            $table->unsignedInteger('improvement_spires')->default(0);
            $table->unsignedInteger('improvement_hideouts')->default(0);
            $table->unsignedInteger('improvement_granaries')->default(0);
            $table->unsignedInteger('improvement_harbor')->default(0);
            $table->unsignedInteger('improvement_forestry')->default(0);
            $table->unsignedInteger('improvement_refinery')->default(0);
            $table->unsignedInteger('improvement_tissue')->default(0);
        });
    }
}
