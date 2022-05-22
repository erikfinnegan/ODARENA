<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuickstarts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quickstarts', function (Blueprint $table) {
            $table->id();
            
            $table->string('name');
            $table->integer('race_id')->unsigned();
            $table->integer('deity_id')->unsigned()->nullable();
            $table->integer('offensive_power')->default(0);
            $table->integer('defensive_power')->default(0);
            $table->integer('enabled');

            $table->integer('draft_rate')->default(50);
            $table->integer('devotion_ticks')->default(0);
            $table->integer('morale')->default(100);
            $table->integer('peasants')->default(0);
            $table->integer('prestige')->default(400);
            $table->integer('spy_strength')->default(100);
            $table->integer('protection_ticks')->default(0);
            $table->integer('wizard_strength')->default(100);
            $table->integer('xp')->default(0);

            $table->text('buildings')->nullable();
            $table->text('cooldown')->nullable();
            $table->text('improvements')->nullable();
            $table->text('land')->nullable();
            $table->text('resources')->nullable();
            $table->text('spells')->nullable();
            $table->text('techs')->nullable();
            $table->text('units')->nullable();

            $table->timestamps();

            $table->foreign('race_id')->references('id')->on('races');
            $table->foreign('deity_id')->references('id')->on('deities');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('quickstarts');
    }
}
