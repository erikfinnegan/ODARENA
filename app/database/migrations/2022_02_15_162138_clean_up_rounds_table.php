<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CleanUpRoundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rounds', function (Blueprint $table) {
          $table->dropColumn([
              'realm_size',
              'pack_size',
              'players_per_race',
              'mixed_alignment',
          ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->integer('realm_size')->unsigned()->default(0);
            $table->integer('pack_size')->unsigned()->default(0);
            $table->integer('players_per_race')->unsigned()->default(0);
            $table->integer('mixed_alignment')->unsigned()->default(0);
        });
    }
}
