<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSpyAndWizardStrengthCostsToUnits extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('units', function (Blueprint $table) {
            $table->decimal('cost_spy_strength')->default(0)->after('cost_morale');
            $table->decimal('cost_wizard_strength')->default(0)->after('cost_spy_strength');
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
            $table->dropColumn([
                'cost_spy_strength',
                'cost_wizard_strength'
            ]);
        });
    }
}
