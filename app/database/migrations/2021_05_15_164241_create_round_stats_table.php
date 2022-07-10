<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoundStatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('round_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('round_id')->unsigned();
            $table->unsignedInteger('stat_id')->unsigned();
            $table->unsignedInteger('value')->default(0);
            $table->timestamps();

            $table->foreign('round_id')->references('id')->on('rounds');
            $table->foreign('stat_id')->references('id')->on('stats');
            $table->unique(['round_id', 'stat_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('round_stats');
    }
}
