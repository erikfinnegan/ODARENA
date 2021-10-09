<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeStatValueFromUnsignedintegerToUnsignedbigintegerInDominionStats extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dominion_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('value')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dominion_stats', function (Blueprint $table) {
            $table->unsignedInteger('value')->change();
        });
    }
}
