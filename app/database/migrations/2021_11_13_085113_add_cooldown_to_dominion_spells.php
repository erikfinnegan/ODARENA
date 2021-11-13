<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCooldownToDominionSpells extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dominion_spells', function (Blueprint $table) {
            $table->integer('cooldown')->default(0)->after('duration');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dominion_spells', function (Blueprint $table) {
            $table->dropColumn([
                'cooldown',
            ]);
        });
    }
}
