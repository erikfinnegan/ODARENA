<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeityPerksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('deity_perks', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('deity_id')->unsigned();
            $table->integer('deity_perk_type_id')->unsigned();
            $table->string('value')->nullable();

            $table->foreign('deity_id')->references('id')->on('deities');
            $table->foreign('deity_perk_type_id')->references('id')->on('deity_perk_types');

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
        Schema::dropIfExists('deity_perks');
    }
}
