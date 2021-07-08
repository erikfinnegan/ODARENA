<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDominionResourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dominion_resources', function (Blueprint $table) {
            $table->id();

            $table->integer('dominion_id')->unsigned();
            $table->unsignedInteger('resource_id')->unsigned();
            $table->unsignedInteger('amount')->default(0);

            $table->foreign('dominion_id')->references('id')->on('dominions');
            $table->foreign('resource_id')->references('id')->on('resources');
            $table->unique(['dominion_id', 'resource_id']);


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
        Schema::dropIfExists('dominion_resources');
    }
}
