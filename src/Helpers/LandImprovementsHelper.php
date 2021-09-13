<?php

namespace OpenDominion\Helpers;

use OpenDominion\Models\Race;

class LandImprovementsHelper
{
    public function getPerkDescription(string $perkKey, float $perkValue)
    {
        $perks =
        [
            'offensive_power_mod' => 'Offensive power increased by %s%% for every 1%% of this land type.',
            'defensive_power_mod' => 'Offensive power increased by %s%% for every 1%% of this land type.',

            'gold_production_raw' => 'Each acre produces %s gold per tick.',
            'food_production_raw' => 'Each acre produces %s food per tick.',
            'ore_production_raw' => 'Each acre produces %s ore per tick.',
            'lumber_production_raw' => 'Each acre produces %s lumber per tick.',
            'mana_production_raw' => 'Each acre produces %s mana per tick.',
            'gems_production_raw' => 'Each acre produces %s gems per tick.',
            'horse_production_raw' => 'Each acre produces %s horses per tick.',

            'gold_production_mod' => 'Gold production increased by %s%% for every 1%% of this land type.',
            'food_production_mod' => 'Food production increased by %s%% for every 1%% of this land type.',
            'ore_production_mod' => 'Ore production increased by %s%% for every 1%% of this land type.',
            'lumber_production_mod' => 'Lumber production increased by %s%% for every 1%% of this land type.',
            'mana_production_mod' => 'Mana production increased by %s%% for every 1%% of this land type.',
            'gems_production_mod' => 'Gem production increased by %s%% for every 1%% of this land type.',
            'horse_production_mod' => 'Horse production increased by %s%% for every 1%% of this land type.',

            'xp_generation_mod' => 'XP generation increased by %s%% for every 1%% of this land type.',

            'max_population' => 'Population increased by %s%% for every 1%% of this land type.',
        ];

        return sprintf($perks[$perkKey], $perkValue);
    }
}
