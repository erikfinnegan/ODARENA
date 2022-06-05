<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDecreesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('decrees', function (Blueprint $table) {
            $table->increments('id');

            $table->string('name');
            $table->string('description');
            $table->integer('enabled')->default(1);
            $table->integer('deity_id')->nullable()->unsigned();
            $table->text('excluded_races')->nullable();
            $table->text('exclusive_races')->nullable();

            $table->foreign('deity_id')->references('id')->on('deities');

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
        Schema::dropIfExists('decrees');
    }
}
