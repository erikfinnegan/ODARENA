<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveWildYetiAndBoatsFromUnits extends Migration
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
                'cost_boat',
                'need_boat',
                'cost_wild_yeti'
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
            $table->float('cost_boat')->unsigned()->default(0);
            $table->float('need_boat')->unsigned()->default(0);
            $table->float('cost_wild_yeti')->unsigned()->default(0);
        });
    }
}
