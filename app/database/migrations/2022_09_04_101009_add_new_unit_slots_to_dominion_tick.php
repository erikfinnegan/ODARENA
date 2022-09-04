<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewUnitSlotsToDominionTick extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dominion_tick', function (Blueprint $table) {
            $table->integer('military_unit5')->unsigned()->default(0)->after('military_unit4');
            $table->integer('military_unit6')->unsigned()->default(0)->after('military_unit5');
            $table->integer('military_unit7')->unsigned()->default(0)->after('military_unit6');
            $table->integer('military_unit8')->unsigned()->default(0)->after('military_unit7');
            $table->integer('military_unit9')->unsigned()->default(0)->after('military_unit8');
            $table->integer('military_unit10')->unsigned()->default(0)->after('military_unit9');

            $table->integer('generated_unit5')->unsigned()->default(0)->after('generated_unit4');
            $table->integer('generated_unit6')->unsigned()->default(0)->after('generated_unit5');
            $table->integer('generated_unit7')->unsigned()->default(0)->after('generated_unit6');
            $table->integer('generated_unit8')->unsigned()->default(0)->after('generated_unit7');
            $table->integer('generated_unit9')->unsigned()->default(0)->after('generated_unit8');
            $table->integer('generated_unit10')->unsigned()->default(0)->after('generated_unit9');

            $table->integer('attrition_unit5')->unsigned()->default(0)->after('attrition_unit4');
            $table->integer('attrition_unit6')->unsigned()->default(0)->after('attrition_unit5');
            $table->integer('attrition_unit7')->unsigned()->default(0)->after('attrition_unit6');
            $table->integer('attrition_unit8')->unsigned()->default(0)->after('attrition_unit7');
            $table->integer('attrition_unit9')->unsigned()->default(0)->after('attrition_unit8');
            $table->integer('attrition_unit10')->unsigned()->default(0)->after('attrition_unit9');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dominion_tick', function (Blueprint $table) {
            $table->dropColumn([
                'military_unit5',
                'military_unit6',
                'military_unit7',
                'military_unit8',
                'military_unit9',
                'military_unit10',
                'generated_unit5',
                'generated_unit6',
                'generated_unit7',
                'generated_unit8',
                'generated_unit9',
                'generated_unit10',
                'attrition_unit5',
                'attrition_unit6',
                'attrition_unit7',
                'attrition_unit8',
                'attrition_unit9',
                'attrition_unit10',
            ]);
        });
    }
}
