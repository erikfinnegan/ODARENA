<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveWildYetiAndBoatsFromDominionTick extends Migration
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
              'resource_boats',
              'resource_wild_yeti',
              'resource_wild_yeti_production',
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
            $table->integer('resource_boats')->unsigned()->default(0);
            $table->integer('resource_wild_yeti')->unsigned()->default(0);
            $table->integer('resource_wild_yeti_production')->unsigned()->default(0);
        });
    }
}
