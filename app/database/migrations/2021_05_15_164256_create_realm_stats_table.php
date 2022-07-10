<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRealmStatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('realm_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('realm_id')->unsigned();
            $table->unsignedInteger('stat_id')->unsigned();
            $table->unsignedInteger('value')->default(0);
            $table->timestamps();

            $table->foreign('realm_id')->references('id')->on('realms');
            $table->foreign('stat_id')->references('id')->on('stats');
            $table->unique(['realm_id', 'stat_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('realm_stats');
    }
}
