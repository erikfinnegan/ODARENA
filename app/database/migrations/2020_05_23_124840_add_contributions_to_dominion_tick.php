<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddContributionsToDominionTick extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dominion_tick', function (Blueprint $table) {
        $table->unsignedInteger('food_contribution')->after('resource_mana_drain')->default(0);
        $table->unsignedInteger('lumber_contribution')->after('food_contribution')->default(0);
        $table->unsignedInteger('ore_contribution')->after('lumber_contribution')->default(0);


        $table->unsignedInteger('food_contributed')->after('ore_contribution')->default(0);
        $table->unsignedInteger('lumber_contributed')->after('food_contributed')->default(0);
        $table->unsignedInteger('ore_contributed')->after('lumber_contributed')->default(0);
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
            $table->dropColumn([
              'food_contribution',
              'lumber_contribution',
              'ore_contribution',
              'food_contributed',
              'lumber_contributed',
              'ore_contributed',
            ]);
        });
    }
}
