<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRealmResourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('realm_resources', function (Blueprint $table) {
            $table->id();

            $table->integer('realm_id')->unsigned();
            $table->unsignedInteger('resource_id')->unsigned();
            $table->unsignedInteger('amount')->default(0);

            $table->foreign('realm_id')->references('id')->on('realms');
            $table->foreign('resource_id')->references('id')->on('resources');
            $table->unique(['realm_id', 'resource_id']);


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
        Schema::dropIfExists('realm_resources');
    }
}
