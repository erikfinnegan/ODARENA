<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeityToUnits extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('units', function (Blueprint $table) {
            $table->integer('deity_id')->nullable()->unsigned()->after('training_time');

            $table->foreign('deity_id')->references('id')->on('deities');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign('units_deity_id_foreign');
            $table->dropColumn([
                'deity_id',
            ]);
        });
    }
}
