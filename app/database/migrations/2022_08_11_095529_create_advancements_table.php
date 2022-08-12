<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdvancementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('advancements', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key')->unique();
            $table->string('name');
            $table->integer('enabled')->default(1);
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
        Schema::dropIfExists('advancements');
    }
}
