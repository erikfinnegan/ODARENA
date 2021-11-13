<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropGuardsFromDominions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dominions', function (Blueprint $table) {
          $table->dropColumn([
              'royal_guard_active_at',
              'elite_guard_active_at',
              'barbarian_guard_active_at',
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
        Schema::table('dominions', function (Blueprint $table) {
            $table->timestamp('royal_guard_active_at')->nullable();
            $table->timestamp('elite_guard_active_at')->nullable();
            $table->timestamp('barbarian_guard_active_at')->nullable();
        });
    }
}
