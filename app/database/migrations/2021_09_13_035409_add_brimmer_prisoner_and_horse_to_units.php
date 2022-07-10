<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBrimmerPrisonerAndHorseToUnits extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedInteger('cost_brimmer')->default(0)->after('cost_spy_strength');
            $table->unsignedInteger('cost_prisoner')->default(0)->after('cost_brimmer');
            $table->unsignedInteger('cost_horse')->default(0)->after('cost_prisoner');
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
            $table->dropColumn([
                'cost_brimmer',
                'cost_prisoner',
                'cost_horse'
            ]);
        });
    }
}
