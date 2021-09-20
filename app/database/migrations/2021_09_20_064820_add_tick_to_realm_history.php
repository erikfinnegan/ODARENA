<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTickToRealmHistory extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('realm_history', function (Blueprint $table) {
            $table->unsignedInteger('tick')->default(0)->after('delta');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('realm_history', function (Blueprint $table) {
            $table->dropColumn([
                'tick',
            ]);
        });
    }
}
