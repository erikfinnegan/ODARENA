<?php

namespace OpenDominion\Helpers;

use Illuminate\Support\Collection;
use OpenDominion\Models\Race;
use OpenDominion\Models\Artefact;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\RealmArtefact;

class ArtefactHelper
{

    public function getArtefactPerksString(Artefact $artefact, RealmArtefact $realmArtefact = null): array
    {
        $effectStrings = [];

        $artefactEffects = [

            // Production / Resources
            'ore_production_mod' => '%s%% ore production',
            'mana_production_mod' => '%s%% mana production',
            'lumber_production_mod' => '%s%% lumber production',
            'food_production_mod' => '%s%% food production',
            'gems_production_mod' => '%s%% gem production',
            'gold_production_mod' => '%s%% gold production',
            'boat_production_mod' => '%s%% boat production',
            'xp_generation_mod' => '%s%% XP generation',

            'no_gold_production' => 'No gold production or revenue',
            'no_ore_production' => 'No ore production',
            'no_lumber_production' => 'No lumber production',
            'no_mana_production' => 'No mana production',
            'no_food_production' => 'No food production',
            'no_boat_production' => 'No boat production',
            'no_gems_production' => 'No gem production',

            'food_consumption_mod' => '%s%% food consumption',

            'exchange_rate' => '%s%% better exchange rates',

            'xp_per_acre_gained' => '%s%% XP per acre gained',

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

            'prestige_gains' => '%s%% prestige gains.',
            'prestige_gains_on_retaliation' => '%s%% prestige gains on retaliation.',

            'cannot_send_expeditions' => 'Cannot send expeditions.',

            'reduced_conversions' => '%s%% conversions.',

            'crypt_decay' => '%s%% crypt decay.',

            // Population
            'population_growth' => '%s%% population growth rate',
            'max_population' => '%s%% population',

            // Magic
            'damage_from_spells' => '%s%% damage from spells',
            'chance_to_reflect_spells' => '%s%% chance to reflect spells',
            'reveal_ops' => 'Reveals the dominion casting artefacts or spying on you',

            'wizard_strength' => '%s%% wizard strength',
            'wizard_cost' => '%s%% wizard cost',

            'base_wizard_strength' => '%s%% base wizard strength.',

            'sorcery_spell_duration' => '%s%% sorcery spell duration.',

            // Espionage
            'spy_strength' => '%s%% spy strength',
            'immortal_spies' => 'Spies become immortal',
            'spy_strength_recovery' => '%s%% spy strength recovery per tick',

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
            'amount_stolen' => '%s%% resource theft',

            // Casualties
            'casualties' => '%s%% casualties',
            'offensive_casualties' => '%s%% casualties suffered when invading',
            'defensive_casualties' => '%s%% casualties suffered when defending',

            'increases_enemy_casualties' => '%s%% enemy casualties',
            'increases_enemy_casualties_on_defense' => '%s%% enemy casualties when defending',
            'increases_enemy_casualties_on_offense' => '%s%% enemy casualties when invading',


            'increases_enemy_draftee_casualties' => '%s%% enemy draftee casualties',

            // OP/DP
            'offensive_power' => '%s%% offensive power',
            'defensive_power' => '%s%% defensive power',

            'governor_offensive_power' => '%s%% offensive power for realm governor.',

            'offensive_power_on_retaliation' => '%s%% offensive power if target recently invaded your realm',

            'target_defensive_power_mod' => '%s%% defensive modifier for target',

            // Improvements
            'improvements' => '%s%% improvements',
            'improvement_points' => '%s%% improvement points',

            'lumber_improvement_points' => '%s%% lumber improvement points',
            'gems_improvement_points' => '%s%% lumber improvement points',
            'ore_improvement_points' => '%s%% lumber improvement points',

            // Land and Construction
            'land_discovered' => '%s%% land discovered on successful invasions',
            'construction_cost' => '%s%% construction costs',
            'rezone_cost' => '%s%% rezoning costs',
            'cannot_explore' => 'Cannot explore',

            'conquered_land_rezoned_to_water' => '%s%% conquered land rezoned to water',
        ];

        foreach ($artefact->perks as $perk)
        {
            if($realmArtefact)
            {
                $realm = Realm::findorfail($dominionArtefact->realm_id);

                $perkValue = $realm->getArtefactPerkValue($perk->key);
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
            $effectStrings[] = sprintf($artefactEffects[$perk->key], $perkValue);
        }

        return $effectStrings;
    }

    public function getArtefactsByRace(Race $race): Collection
    {
        $artefacts = collect(Artefact::all()->keyBy('key')->sortBy('name')->where('enabled',1));

        foreach($artefacts as $artefact)
        {
          if(
                (count($artefact->excluded_races) > 0 and in_array($race->name, $artefact->excluded_races)) or
                (count($artefact->exclusive_races) > 0 and !in_array($race->name, $artefact->exclusive_races))
            )
          {
              $artefacts->forget($artefact->key);
          }
        }

        return $artefacts;
    }

    public function getExclusivityString(Artefact $artefact): string
    {

        $exclusivityString = '<br><small class="text-muted">';

        if($exclusives = count($artefact->exclusive_races))
        {
            foreach($artefact->exclusive_races as $raceName)
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
        elseif($excludes = count($artefact->excluded_races))
        {
            $exclusivityString .= 'All except ';
            foreach($artefact->excluded_races as $raceName)
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
