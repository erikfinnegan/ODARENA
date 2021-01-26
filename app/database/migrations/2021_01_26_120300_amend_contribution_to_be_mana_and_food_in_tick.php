<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AmendContributionToBeManaAndFoodInTick extends Migration
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
                'resource_lumber_contribution',
                'resource_ore_contribution',
                'resource_lumber_contributed',
                'resource_ore_contributed',
            ]);

            $table->integer('resource_mana_contributed')->default(1)->after('resource_food_contribution');
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
            $table->unsignedInteger('resource_food_contribution')->after('resource_mana_drain')->default(0);
            $table->unsignedInteger('resource_lumber_contribution')->after('resource_food_contribution')->default(0);
            $table->unsignedInteger('resource_ore_contribution')->after('resource_lumber_contribution')->default(0);

            $table->dropColumn([
                'resource_mana_contributed',
            ]);
        });
    }
}
