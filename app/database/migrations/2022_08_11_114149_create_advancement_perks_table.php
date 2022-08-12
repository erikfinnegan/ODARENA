<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdvancementPerksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('advancement_perks', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('advancement_id')->unsigned();
            $table->integer('advancement_perk_type_id')->unsigned();
            $table->string('value')->nullable();

            $table->foreign('advancement_id')->references('id')->on('advancements');
            $table->foreign('advancement_perk_type_id')->references('id')->on('advancement_perk_types');

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
        Schema::dropIfExists('advancement_perks');
    }
}
