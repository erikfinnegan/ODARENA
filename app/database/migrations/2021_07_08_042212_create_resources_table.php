<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('resources', function (Blueprint $table) {
          $table->increments('id');
          $table->string('key');
          $table->string('name');
          $table->integer('enabled')->default(1);
          $table->decimal('exchange_value', 16, 2)->default(0);
          $table->decimal('improvement_points', 16, 2)->default(0);
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
        Schema::dropIfExists('resources');
    }
}
