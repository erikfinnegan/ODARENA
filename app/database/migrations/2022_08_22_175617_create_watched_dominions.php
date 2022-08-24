<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWatchedDominions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('watched_dominions', function (Blueprint $table) {
            $table->id();
            $table->integer('watcher_id')->unsigned();
            $table->integer('dominion_id')->unsigned();
            $table->timestamps();

            $table->foreign('watcher_id')->references('id')->on('dominions');
            $table->foreign('dominion_id')->references('id')->on('dominions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('watched_dominions');
    }
}
