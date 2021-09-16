<?php

namespace OpenDominion\Helpers;

use OpenDominion\Models\Race;

class LandImprovementHelper
{
    public function getPerkDescription(string $perkKey, float $perkValue, bool $showSecondHalf = true)
    {
        $perks =
        [
            'offensive_power_mod' => ['%s%% offensive power', ' for every 1%% of this land type.'],
            'defensive_power_mod' => ['%s%% defensive power', ' for every 1%% of this land type.'],

            'gold_production_raw' => ['%s gold/tick', ' per acre.'],
            'food_production_raw' => ['%s food/tick', ' per acre.'],
            'ore_production_raw' => ['%s ore/tick', ' per acre.'],
            'lumber_production_raw' => ['%s lumber/tick', ' per acre.'],
            'mana_production_raw' => ['%s mana/tick', ' per acre.'],
            'gems_production_raw' => ['%s gems/tick', ' per acre.'],
            'horse_production_raw' => ['%s horses/tick', ' per acre.'],

            'gold_production_mod' => ['%s%% gold production', ' for every 1%% of this land type.'],
            'food_production_mod' => ['%s%% food production', ' for every 1%% of this land type.'],
            'ore_production_mod' => ['%s%% ore production', ' for every 1%% of this land type.'],
            'lumber_production_mod' => ['%s%% lumber production', ' for every 1%% of this land type.'],
            'mana_production_mod' => ['%s%% mana production', ' for every 1%% of this land type.'],
            'gems_production_mod' => ['%s%% gems production', ' for every 1%% of this land type.'],
            'horse_production_mod' => ['%s%% horse taming', ' for every 1%% of this land type.'],

            'xp_generation_mod' => ['%s%% XP generation', ' for every 1%% of this land type.'],

            'max_population' => ['%s%% population', ' for every 1%% of this land type.'],
        ];

        $string = $perks[$perkKey][0];

        if($showSecondHalf)
        {
            $string .= $perks[$perkKey][1];
        }

        if($perkValue > 0 and $this->getPerkType($perkKey) == 'mod')
        {
            $perkValue = '+'.number_format($perkValue,2);
        }
        elseif($perkValue)
        {
            $perkValue = number_format($perkValue, 2);
        }

        return sprintf($string, $perkValue);
    }

    public function getPerkType(string $perkKey): string
    {
        if(strpos($perkKey, '_mod') or $perkKey == 'max_population')
        {
            return 'mod';
        }

        return 'raw';
    }
}
