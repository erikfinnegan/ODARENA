<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPsionicStrengthMinimumRoundsMaxPerRoundToRaces extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('races', function (Blueprint $table) {
            $table->unsignedInteger('max_per_round')->nullable()->after('description');
            $table->unsignedInteger('minimum_rounds')->default(0)->after('max_per_round');
            $table->decimal('psionic_strength')->default(1)->after('minimum_rounds');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('races', function (Blueprint $table) {
            $table->dropColumn([
                'max_per_round',
                'minimum_rounds',
                'psionic_strength',
            ]);
        });
    }
}
