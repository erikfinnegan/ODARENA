<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDominionInsightTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dominion_insight', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('dominion_id')->unsigned();
            $table->integer('source_dominion_id')->unsigned()->nullable();
            $table->text('data');
            $table->integer('round_tick')->unsigned()->default(0);

            $table->foreign('dominion_id')->references('id')->on('dominions');
            $table->foreign('source_dominion_id')->references('id')->on('dominions');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dominion_insight');
    }
}
