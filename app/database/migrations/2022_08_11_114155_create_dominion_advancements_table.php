<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDominionAdvancementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dominion_advancements', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('dominion_id')->unsigned();
            $table->integer('advancement_id')->unsigned();
            $table->integer('level')->default(0);

            $table->foreign('dominion_id')->references('id')->on('dominions');
            $table->foreign('advancement_id')->references('id')->on('advancements');
            $table->unique(['dominion_id', 'advancement_id']);

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
        Schema::dropIfExists('dominion_advancements');
    }
}
