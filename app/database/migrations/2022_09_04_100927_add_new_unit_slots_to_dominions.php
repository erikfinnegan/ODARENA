<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewUnitSlotsToDominions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dominions', function (Blueprint $table) {
            $table->integer('military_unit5')->unsigned()->default(0)->after('military_unit4');
            $table->integer('military_unit6')->unsigned()->default(0)->after('military_unit5');
            $table->integer('military_unit7')->unsigned()->default(0)->after('military_unit6');
            $table->integer('military_unit8')->unsigned()->default(0)->after('military_unit7');
            $table->integer('military_unit9')->unsigned()->default(0)->after('military_unit8');
            $table->integer('military_unit10')->unsigned()->default(0)->after('military_unit9');
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
            $table->dropColumn([
                'military_unit5',
                'military_unit6',
                'military_unit7',
                'military_unit8',
                'military_unit9',
                'military_unit10',
            ]);
        });
    }
}
