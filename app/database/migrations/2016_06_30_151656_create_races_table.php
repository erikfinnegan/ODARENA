<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateRacesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('races', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->enum('alignment', ['good', 'neutral', 'evil', 'npc', 'other', 'independent']);
            $table->enum('home_land_type', ['plain', 'mountain', 'swamp',/* 'cavern', */'forest', 'hill', 'water']);

            # ODA
            #$table->integer('playable');
            $table->integer('attacking');
            $table->integer('exploring');
            $table->integer('converting');

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
        Schema::drop('races');
    }
}
