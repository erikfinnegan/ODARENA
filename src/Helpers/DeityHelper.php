<?php

namespace OpenDominion\Helpers;

use Illuminate\Support\Collection;
use OpenDominion\Models\Race;
use OpenDominion\Models\Deity;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\DominionDeity;

class DeityHelper
{

    public function getDeityPerksString(Deity $deity, DominionDeity $dominionDeity = null): array
    {
        $effectStrings = [];

        $deityEffects = [

            // Production
            'ore_production' => '%s%% ore production',
            'mana_production' => '%s%% mana production',
            'lumber_production' => '%s%% lumber production',
            'food_production' => '%s%% food production',
            'gem_production' => '%s%% gem production',
            'gold_production' => '%s%% gold production',
            'boat_production' => '%s%% boat production',
            'tech_production' => '%s%% XP generation',

            'no_gold_production' => 'No gold production or revenue',
            'no_ore_production' => 'No ore production',
            'no_lumber_production' => 'No lumber production',
            'no_mana_production' => 'No mana production',
            'no_food_production' => 'No food production',
            'no_boat_production' => 'No boat production',
            'no_gem_production' => 'No gem production',

            // Military
            'drafting' => '+%s%% drafting',
            'training_time' => '%s ticks training time for military units (does not include Spies, Wizards, or Archmages)',
            'training_costs' => '+%s%% military unit training costs',
            'unit_gold_costs' => '%s%% military unit gold costs',
            'unit_ore_costs' => '%s%% military unit ore costs',
            'unit_lumber_costs' => '%s%% military unit lumber costs',

            'faster_return' => 'Units return %s ticks faster from invasions',

            'can_kill_immortal' => 'Can kill some immortal units.',

            'unit_gold_costs' => '%s%% unit gold costs.',
            'unit_ore_costs' => '%s%% unit ore costs.',
            'unit_lumber_costs' => '%s%% unit lumber costs.',
            'unit_mana_costs' => '%s%% unit mana costs.',
            'unit_blood_costs' => '%s%% unit blood costs.',
            'unit_food_costs' => '%s%% unit food costs.',

            // Population
            'population_growth' => '%s%% population growth rate',

            // Magic
            'damage_from_spells' => '%s%% damage from spells',
            'chance_to_reflect_spells' => '%s%% chance to reflect spells',
            'reveal_ops' => 'Reveals the dominion casting deitys or spying on you',

            'wizard_strength' => '%s%% wizard strength',
            'wizard_cost' => '%s%% wizard cost',

            // Espionage
            'spy_strength' => '%s%% spy strength',
            'immortal_spies' => 'Spies become immortal',
            'spy_strength_recovery' => '%s%% spy strength reocver per tick',

            'gold_theft' => '%s%% gold lost to theft.',
            'mana_theft' => '%s%% mana lost to theft.',
            'lumber_theft' => '%s%% lumber lost to theft.',
            'ore_theft' => '%s%% ore lost to theft.',
            'gems_theft' => '%s%% gems lost to theft.',
            'all_theft' => '%s%% resources lost to theft',

            'gold_stolen' => '%s%% gold theft.',
            'mana_stolen' => '%s%% mana theft.',
            'lumber_stolen' => '%s%% lumber theft.',
            'ore_stolen' => '%s%% ore  theft.',
            'gems_stolen' => '%s%% gem theft.',
            'all_stolen' => '%s%% resource theft',

            // Casualties
            'increases_casualties' => '%s%% enemy casualties',
            'increases_enemy_draftee_casualties' => '%s%% enemy draftee casualties',
            'increases_casualties_on_offense' => '%s%% enemy casualties when invading',
            'increases_casualties_on_defense' => '%s%% enemy casualties when defending',

            'casualties' => '%s%% casualties',
            'offensive_casualties' => '%s%% casualties suffered when invading',
            'defensive_casualties' => '%s%% casualties suffered when defending',

            // OP/DP
            'offensive_power' => '%s%% offensive power',
            'defensive_power' => '%s%% defensive power',

            'offensive_power_on_retaliation' => '%s%% offensive power if target recently invaded your realm',

            'defensive_modifier_reduction' => 'Reduces target\'s defensive modifiers by %s%%.',

            // Improvements
            'improvements' => '%s%% improvement points value',

            // Land and Construction
            'land_discovered' => '%s%% land discovered on successful invasions',
            'construction_cost' => '%s%% construction costs',
            'rezone_cost' => '%s%% rezoning costs',
            'cannot_explore' => 'Cannot explore',
        ];

        foreach ($deity->perks as $perk)
        {
            if($dominionDeity)
            {
                $dominion = Dominion::findorfail($dominionDeity->dominion_id);

                $perkValue = $dominion->getDeityPerkValue($perk->key);
            }
            else
            {
                $perkValue = $perk->pivot->value;
            }

            if($perkValue > 0)
            {
                $perkValue = '+' . $perkValue;
            }

            $perkValue = str_replace('_', ' ',ucwords($perkValue));
            $effectStrings[] = sprintf($deityEffects[$perk->key], $perkValue);
        }

        return $effectStrings;
    }

    public function getDeitiesByRace(Race $race): Collection
    {
        $deities = collect(Deity::all()->keyBy('key')->sortBy('name')->where('enabled',1));

        foreach($deities as $deity)
        {
          if(
                (count($deity->excluded_races) > 0 and in_array($race->name, $deity->excluded_races)) or
                (count($deity->exclusive_races) > 0 and !in_array($race->name, $deity->exclusive_races))
            )
          {
              $deities->forget($deity->key);
          }
        }

        return $deities;
    }

    public function getExclusivityString(Deity $deity): string
    {

        $exclusivityString = '<br><small class="text-muted">';

        if($exclusives = count($deity->exclusive_races))
        {
            foreach($deity->exclusive_races as $raceName)
            {
                $exclusivityString .= $raceName;
                if($exclusives > 1)
                {
                    $exclusivityString .= ', ';
                }
                $exclusives--;
            }

            $exclusivityString .= ' only';
        }
        elseif($excludes = count($deity->excluded_races))
        {
            $exclusivityString .= 'All except ';
            foreach($deity->excluded_races as $raceName)
            {
                $exclusivityString .= $raceName;
                if($excludes > 1)
                {
                    $exclusivityString .= ', ';
                }
                $excludes--;
            }
        }

        $exclusivityString .= '</small>';

        return $exclusivityString;

    }

}
