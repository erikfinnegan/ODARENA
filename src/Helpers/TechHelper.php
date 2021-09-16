<?php

namespace OpenDominion\Helpers;

use OpenDominion\Models\Tech;

class TechHelper
{
    public function getTechs()
    {
        return Tech::all()->keyBy('key');
    }

    public function getTechDescription(Tech $tech): string
    {
        $perkTypeStrings = [
            // Military related
            'defense' => '%s%% defensive power',
            'offense' => '%s%% offensive power',
            'military_cost' => '%s%% military training gold, ore, and lumber costs',


            'military_cost_food' => '%s%% military training food costs',
            'military_cost_mana' => '%s%% military training mana costs',

            // Casualties related
            'defensive_casualties' => '%s%% casualties on defense',
            'offensive_casualties' => '%s%% casualties on offense',

            // Logistics
            'construction_cost' => '%s%% construction costs',
            'explore_draftee_cost' => '%s draftee per acre explore cost (min 3)',
            'explore_gold_cost' => '%s%% exploring gold cost',
            'max_population' => '%s%% maximum population (multiplicative bonus)',
            'rezone_cost' => '%s%% rezoning costs',

            // Spy related
            'spy_cost' => '%s%% cost of spies',
            'spy_losses' => '%s%% spy losses on failed operations',
            'spy_strength' => '%s%% spy strength',
            'spy_strength_recovery' => '%s spy strength per tick',
            'amount_stolen' => '%s%% amount stolen',

            // Wizard related
            'spell_cost' => '%s%% cost of spells',
            'wizard_cost' => '%s%% cost of wizards',
            'wizard_strength' => '%s%% wizard strength',
            'wizard_strength_recovery' => '%s wizard strength per tick',

            // Resource related
            'food_production_mod' => '%s%% food production',
            'gems_production_mod' => '%s%% gem production',
            'lumber_production_mod' => '%s%% lumber production',
            'mana_production_mod' => '%s%% mana production',
            'ore_production_mod' => '%s%% ore production',
            'gold_production_mod' => '%s%% gold production',

            'food_production_raw' => '%s% food/tick production',
            'gems_production_raw' => '%s% gem/tick production',
            'lumber_production_raw' => '%s% lumber/tick production',
            'mana_production_raw' => '%s% mana/tick production',
            'ore_production_raw' => '%s% ore/tick production',
            'gold_production_raw' => '%s% gold/tick production',

            // ODA
            'prestige_gains' => '%s%% higher prestige gains',
            'improvements' => '%s%% higher improvement bonus',
            'conversions' => '%s%% more conversions (only applicable to Afflicted, Cult, and Sacred Order)',
            'barracks_housing' => '%s%% higher military housing in buildings that provide military housing',
            'gold_interest' => '%s%% interest on your gold stockpile per tick',
            'exchange_rate' => '%s%% better exchange rates',
            'jobs_per_building' => '%s%% more jobs per building',
            'drafting' => '%s%% drafting',

            // Improvements
            'gemcutting' => '%s%% more improvement points per gem',
            'gold_invest_bonus' => '%s%% more improvement points per gold',
            'ore_invest_bonus' => '%s%% more improvement points per ore',
            'gems_invest_bonus' => '%s%% more improvement points per gem',
            'lumber_invest_bonus' => '%s%% more improvement points per lumber',

        ];

        $perkStrings = [];
        foreach ($tech->perks as $perk) {
            if (isset($perkTypeStrings[$perk->key])) {
                $perkValue = (float)$perk->pivot->value;
                if ($perkValue < 0) {
                    $perkStrings[] = vsprintf($perkTypeStrings[$perk->key], $perkValue);
                } else {
                    $perkStrings[] = vsprintf($perkTypeStrings[$perk->key], '+' . $perkValue);
                }
            }
        }

        return implode( ', ', $perkStrings);
    }
}
