<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDecreeStatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('decree_states', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('decree_id')->unsigned();
            $table->string('name');

            $table->foreign('decree_id')->references('id')->on('decrees');

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
        Schema::dropIfExists('decree_states');
    }
}
