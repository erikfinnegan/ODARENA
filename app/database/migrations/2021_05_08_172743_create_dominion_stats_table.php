<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDominionStatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dominion_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('dominion_id')->unsigned();
            $table->unsignedInteger('stat_id')->unsigned();
            $table->unsignedInteger('value')->default(0);
            $table->timestamps();

            $table->foreign('dominion_id')->references('id')->on('dominions');
            $table->foreign('stat_id')->references('id')->on('stats');
            $table->unique(['dominion_id', 'stat_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dominion_stats');
    }
}
