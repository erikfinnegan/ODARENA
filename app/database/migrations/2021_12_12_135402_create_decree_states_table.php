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
            $table->id();
            $table->integer('decree_id')->unsigned();
            $table->string('key');
            $table->string('name');

            $table->integer('unit_perk_type_id')->unsigned()->nullable();
            $table->string('unit_perk_type_values')->nullable();

            $table->timestamps();

            $table->foreign('race_id')->references('id')->on('races');
            $table->unique(['race_id', 'key']);
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
