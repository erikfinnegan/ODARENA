<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDominionDecreesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dominion_decrees', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('dominion_id')->unsigned();
            $table->integer('decree_id')->unsigned();
            $table->text('state')->nullable();
            $table->integer('cooldown')->unsigned()->default(0);

            $table->foreign('dominion_id')->references('id')->on('dominions');
            $table->foreign('decree_id')->references('id')->on('decrees');
            $table->unique(['dominion_id']);
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
        Schema::dropIfExists('dominion_decrees');
    }
}
