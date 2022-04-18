<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserXp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_xp', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('dominion_id')->nullable();
            $table->unsignedInteger('realm_id')->nullable();
            $table->unsignedInteger('round_id')->nullable();
            $table->uuid('game_event_id')->nullable();
            $table->integer('tick')->unsigned()->nullable();
            $table->text('context')->nullable();
            $table->integer('xp')->unsigned()->default(0);

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('dominion_id')->references('id')->on('dominions');
            $table->foreign('realm_id')->references('id')->on('realms');
            $table->foreign('round_id')->references('id')->on('rounds');
            $table->foreign('game_event_id')->references('id')->on('game_events');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_xp');
    }
}
