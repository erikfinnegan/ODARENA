<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSiegeEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('siege_events', function (Blueprint $table) {

            $table->uuid('id');

            $table->unsignedInteger('besieged_dominion_id')->unique();
            $table->unsignedInteger('besieger_dominion_id');
            $table->unsignedInteger('duration')->default(0);
            $table->unsignedInteger('military_unit1')->default(0);
            $table->unsignedInteger('military_unit2')->default(0);
            $table->unsignedInteger('military_unit3')->default(0);
            $table->unsignedInteger('military_unit4')->default(0);

            $table->timestamps();

            $table->foreign('besieger_dominion_id')->references('id')->on('dominions');
            $table->foreign('besieged_dominion_id')->references('id')->on('dominions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('siege_events');
    }
}
