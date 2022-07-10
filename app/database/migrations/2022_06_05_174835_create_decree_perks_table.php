<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDecreePerksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('decree_state_perks', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('decree_state_id')->unsigned();
            $table->integer('decree_state_perk_type_id')->unsigned();
            $table->string('value')->nullable();
            $table->timestamps();

            $table->foreign('decree_state_id')->references('id')->on('decree_states');
            $table->foreign('decree_state_perk_type_id')->references('id')->on('decree_state_perk_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('decree_state_perks');
    }
}

