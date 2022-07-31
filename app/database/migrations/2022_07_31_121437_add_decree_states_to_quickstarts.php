<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDecreeStatesToQuickstarts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quickstarts', function (Blueprint $table) {
            $table->text('decree_states')->nullable()->after('techs');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quickstarts', function (Blueprint $table) {
            $table->dropColumn([
                'decree_states',
            ]);
        });
    }
}
