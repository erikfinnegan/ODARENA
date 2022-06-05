<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDominionDecreeStates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dominion_decree_states', function (Blueprint $table) {
            $table->id();
            $table->integer('dominion_id')->unsigned();
            $table->integer('decree_id')->unsigned();
            $table->integer('decree_state_id')->unsigned();
            $table->integer('tick')->unsigned()->default(0);

            $table->foreign('dominion_id')->references('id')->on('dominions');
            $table->foreign('decree_id')->references('id')->on('decrees');
            $table->foreign('decree_state_id')->references('id')->on('decree_states');
            $table->unique(['dominion_id', 'decree_id']);

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
        Schema::dropIfExists('dominion_decree_states');
    }
}
