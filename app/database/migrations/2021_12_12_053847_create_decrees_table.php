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
          $table->string('key')->unique();
          $table->integer('enabled')->default(1);
          $table->string('name');
          $table->text('states')->nullable();
          $table->text('perks')->nullable();
          $table->text('default')->nullable();
          $table->integer('cooldown')->default(96);
          $table->text('excluded_races')->nullable();
          $table->text('exclusive_races')->nullable();
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
