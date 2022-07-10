<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArtefactPerksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('artefact_perks', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('artefact_id')->unsigned();
            $table->integer('artefact_perk_type_id')->unsigned();
            $table->string('value')->nullable();

            $table->foreign('artefact_id')->references('id')->on('artefacts');
            $table->foreign('artefact_perk_type_id')->references('id')->on('artefact_perk_types');

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
        Schema::dropIfExists('artefact_perks');
    }
}
