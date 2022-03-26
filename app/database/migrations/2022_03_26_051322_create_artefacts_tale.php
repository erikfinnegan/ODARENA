<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArtefactsTale extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('artefacts', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description');
            $table->integer('base_power')->unsigned()->default(0);
            $table->integer('enabled')->default(1);
            $table->text('excluded_races')->nullable();
            $table->text('exclusive_races')->nullable();
            $table->integer('deity_id')->nullable()->unsigned();

            $table->foreign('deity_id')->references('id')->on('deity');

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
        Schema::dropIfExists('artefacts');
    }
}
