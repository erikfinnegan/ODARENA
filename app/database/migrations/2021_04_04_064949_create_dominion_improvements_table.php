<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDominionImprovementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dominion_improvements', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('dominion_id')->unsigned();
            $table->integer('improvement_id')->unsigned();
            $table->integer('invested')->unsigned()->default(0);
            $table->timestamps();

            $table->foreign('dominion_id')->references('id')->on('dominions');
            $table->foreign('improvement_id')->references('id')->on('improvements');
            $table->unique(['dominion_id', 'improvement_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dominion_improvements');
    }
}
