<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRealmArtefactsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('realm_artefacts', function (Blueprint $table) {
            $table->id();
            $table->integer('realm_id')->unsigned();
            $table->integer('artefact_id')->unsigned();
            $table->integer('power')->unsigned()->default(0);

            $table->foreign('realm_id')->references('id')->on('realms');
            $table->foreign('artefact_id')->references('id')->on('artefacts');
            $table->unique(['realm_id', 'artefact_id']);
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
        Schema::dropIfExists('realm_artefacts');
    }
}
