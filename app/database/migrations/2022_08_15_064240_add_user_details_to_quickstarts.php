<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserDetailsToQuickstarts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quickstarts', function (Blueprint $table) {
            $table->integer('user_id')->unsigned()->nullable()->after('name');
            $table->integer('round_id')->unsigned()->nullable()->after('user_id');
            $table->integer('is_public')->unsigned()->after('round_id')->default(0);

            $table->foreign('round_id')->references('id')->on('rounds');
            $table->foreign('user_id')->references('id')->on('users');
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
                'round_id',
                'user_id',
                'is_public',
            ]);
        });
    }
}
