<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRealmSourceToDominionInsight extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dominion_insight', function (Blueprint $table) {
            $table->integer('source_realm_id')->unsigned()->nullable()->after('source_dominion_id');

            $table->foreign('source_realm_id')->references('id')->on('realms');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dominion_insight', function (Blueprint $table) {
            $table->dropColumn([
                'source_realm_id',
            ]);
        });
    }
}
