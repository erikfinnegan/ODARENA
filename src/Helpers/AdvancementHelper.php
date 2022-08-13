<?php

namespace OpenDominion\Helpers;

use Illuminate\Support\Collection;
use OpenDominion\Models\Race;
use OpenDominion\Models\Advancement;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\DominionAdvancement;

class AdvancementHelper
{

    # IMPROVEMENTS 2.0

    public function getAdvancementPerkDescription(string $improvementPerk): string
    {

        $improvementPerkDescriptions = [
            'gold_production_mod' => 'gold production',
            'ore_production_mod' => 'ore production',
            'lumber_production_mod' => 'lumber production',
            'gems_production_mod' => 'gem production',
            'mana_production_mod' => 'mana production',
            'food_production_mod' => 'food production',
            'horse_production_mod' => 'horse taming',
            'blood_production_mod' => 'horse taming',
            'marshling_production_mod' => 'marshling spawning',
            'miasma_production_mod' => 'miasma extraction',
            'sapling_production_mod' => 'sapling growth',
            'thunderstone_production_mod' => 'thunderstone discovery',

            'food_consumption_mod' => 'food consumption',

            'xp_generation_mod' => 'XP generation',

            'exchange_rate' => 'exchange rate',
            'resource_conversion' => 'resource conversion (passive and automatic conversion, not exchanged resources)',

            'xp_gains' => 'XP gained',
            'advancement_costs' => 'advancements costs',

            'population' => 'population',
            'population_growth' => 'population growth',
            'unit_specific_housing' => 'unit specific housing',

            'jobs_per_building' => 'jobs per building',

            'construction_cost' => 'construction costs',
            'rezone_cost' => 'rezoning costs',
            'construction_time' => 'construction time',

            'explore_gold_cost' => 'exploration gold costs',
            'land_discovered' => 'land discovered',

            'unit_gold_costs' => 'unit gold costs',
            'unit_ore_costs' => 'unit ore costs',
            'unit_lumber_costs' => 'unit lumber costs',
            'unit_gem_costs' => 'unit gem costs',
            'unit_mana_costs' => 'unit mana costs',
            'unit_food_costs' => 'unit food costs',

            'training_time_mod' => 'unit training time',
            'chance_of_instant_return' => 'chance of units returning instantly from invasion',

            'offensive_power' => 'offensive power',
            'defensive_power' => 'defensive power',

            'casualties' => 'own casualties',
            'casualties_on_offense' => 'own offensive casualties',
            'casualties_on_defense' => 'own defensive casualties',
            'increases_enemy_casualties' => 'enemy casualties',

            'target_defensive_power_mod' => 'defensive modifiers for target',

            'prestige_gains' => 'prestige gains',
            'base_morale' => 'morale',

            'spy_strength' => 'spy strength',
            'spy_losses' => 'spy losses',
            'spy_housing' => 'spy units housing',

            'wizard_strength' => 'wizard strength',
            'wizard_losses' => 'wizard losses',
            'spell_damage' => 'spell damage',
            'wizard_housing' => 'wizard units housing',

            'gold_theft' => 'gold theft',
            'ore_theft' => 'ore theft',
            'lumber_theft' => 'lumber theft',
            'gems_theft' => 'gem theft',
            'mana_theft' => 'mana theft',
            'food_theft' => 'food theft',
            'horse_theft' => 'horse theft',

            'theft_protection' => 'theft protection (affects buildings that reduce theft, otherwise no effect)',
            'amount_stolen' => 'amount stolen',

            'unit_pairing' => 'unit limit pairing',

            'attrition_protection' => 'attrition protection',

            'improvement_points' => 'improvement points value from investments',

            'title_bonus' => 'ruler title bonus'

        ];

        return isset($improvementPerkDescriptions[$improvementPerk]) ? $improvementPerkDescriptions[$improvementPerk] : 'Missing description';
    }

    public function getAdvancementPerksString(Advancement $advancement, DominionAdvancement $dominionAdvancement = null): array
    {
        $effectStrings = [];

        $advancementEffects = [

            // Production / Resources
            'ore_production_mod' => '%s%% ore production',
            'mana_production_mod' => '%s%% mana production',
            'lumber_production_mod' => '%s%% lumber production',
            'food_production_mod' => '%s%% food production',
            'gems_production_mod' => '%s%% gem production',
            'gold_production_mod' => '%s%% gold production',
            'boat_production_mod' => '%s%% boat production',
            'pearls_production_mod' => '%s%% pearl production',
            'xp_generation_mod' => '%s%% XP generation',

            'no_gold_production' => 'No gold production or revenue',
            'no_ore_production' => 'No ore production',
            'no_lumber_production' => 'No lumber production',
            'no_mana_production' => 'No mana production',
            'no_food_production' => 'No food production',
            'no_boat_production' => 'No boat production',
            'no_gems_production' => 'No gem production',

            'food_consumption_mod' => '%s%% food consumption',

            'exchange_rate' => '%s%% exchange rates',

            'xp_gains' => '%s%% XP per acre gained',

            // Military
            'drafting' => '%s%% drafting',
            'training_time_raw' => '%s ticks training time for military units (does not include Spies, Wizards, or Archmages)',
            'training_costs' => '%s%% military unit training costs',
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

            'cannot_send_expeditions' => 'Cannot send expeditions',

            // Population
            'population_growth' => '%s%% population growth rate',
            'max_population' => '%s%% population',
            'military_housing' => '%s%% military housing',
            'jobs_per_building' => '%s%% jobs per building',

            // Magic
            'damage_from_spells' => '%s%% damage from spells',
            'chance_to_reflect_spells' => '%s%% chance to reflect spells',
            'reveal_ops' => 'Reveals the dominion casting advancements or spying on you',

            'wizard_strength' => '%s%% wizard strength',
            'wizard_strength_recovery' => '%s%% wizard strength recovery',
            'wizard_cost' => '%s%% wizard cost',
            'spell_cost' => '%s%% spell costs',

            // Espionage
            'spy_cost' => '%s%% spy cost',
            'spy_strength' => '%s%% spy strength',
            'spy_losses' => '%s%% spy losses',
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
            'casualties_on_offense' => '%s%% casualties suffered when invading',
            'casualties_on_defense' => '%s%% casualties suffered when defending',

            'increases_enemy_casualties' => '%s%% enemy casualties',
            'increases_enemy_casualties_on_defense' => '%s%% enemy casualties when defending',
            'increases_enemy_casualties_on_offense' => '%s%% enemy casualties when invading',

            // OP/DP
            'offensive_power' => '%s%% offensive power',
            'defensive_power' => '%s%% defensive power',

            'offensive_power_on_retaliation' => '%s%% offensive power if target recently invaded your realm',

            'target_defensive_power_mod' => '%s%% defensive modifier for target',

            // Improvements
            'improvements' => '%s%% improvements',
            'improvement_points' => '%s%% improvement points',
            'improvements_interest' => '%s%% improvements interest',

            'gold_improvement_points' => '%s%% gold improvement points',
            'lumber_improvement_points' => '%s%% lumber improvement points',
            'gems_improvement_points' => '%s%% lumber improvement points',
            'ore_improvement_points' => '%s%% lumber improvement points',

            // Land and Construction
            'land_discovered' => '%s%% land discovered on successful invasions',
            'construction_cost' => '%s%% construction costs',
            'construction_time' => '%s%% construction time',
            'rezone_cost' => '%s%% rezoning costs',
            'cannot_explore' => 'Cannot explore',
        ];

        foreach ($advancement->perks as $perk)
        {
            if($dominionAdvancement)
            {
                $dominion = Dominion::findorfail($dominionAdvancement->dominion_id);

                $perkValue = $dominion->getAdvancementPerkValue($perk->key);
            }
            else
            {
                $perkValue = $perk->pivot->value;
            }

            $perkValue = str_replace('_', ' ',ucwords($perkValue));
            $perkValue = $perkValue > 0 ? '+' . display_number_format($perkValue, 4) : display_number_format($perkValue, 4);

            $effectStrings[] = sprintf($advancementEffects[$perk->key], $perkValue);
        }

        return $effectStrings;
    }

    public function getAdvancementsByRace(Race $race): Collection
    {
        $advancements = collect(Advancement::all()->keyBy('key')->sortBy('name')->where('enabled',1));

        foreach($advancements as $advancement)
        {
          if(
                (count($advancement->excluded_races) > 0 and in_array($race->name, $advancement->excluded_races)) or
                (count($advancement->exclusive_races) > 0 and !in_array($race->name, $advancement->exclusive_races))
            )
          {
              $advancements->forget($advancement->key);
          }
        }

        return $advancements;
    }

    public function getExclusivityString(Advancement $advancement): string
    {

        $exclusivityString = '<br><small class="text-muted">';

        if($exclusives = count($advancement->exclusive_races))
        {
            foreach($advancement->exclusive_races as $raceName)
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
        elseif($excludes = count($advancement->excluded_races))
        {
            $exclusivityString .= 'All except ';
            foreach($advancement->excluded_races as $raceName)
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

    public function hasExclusivity(Advancement $advancement): bool
    {
        return (count($advancement->exclusive_races) or count($advancement->excluded_races));
    }

}
