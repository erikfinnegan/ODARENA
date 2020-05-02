<?php

namespace OpenDominion\Helpers;

use LogicException;
use OpenDominion\Models\Race;
use OpenDominion\Models\RacePerkType;

class RaceHelper
{
    public function getPerkDescriptionHtmlWithValue(RacePerkType $perkType): ?array
    {
        $valueType = '%';
        $booleanValue = false;
        switch($perkType->key) {
            case 'archmage_cost':
                $negativeBenefit = true;
                $description = 'Archmage cost';
                $valueType = 'p';
                break;
            case 'construction_cost':
                $negativeBenefit = true;
                $description = 'Construction cost';
                break;
            case 'defense':
                $negativeBenefit = false;
                $description = 'Defensive power';
                break;
            case 'extra_barren_max_population':
                $negativeBenefit = false;
                $description = 'Population from barren land';
                $valueType = '';
                break;
            case 'food_consumption':
                $negativeBenefit = true;
                $description = 'Food consumption';
                break;
            case 'food_production':
                $negativeBenefit = false;
                $description = 'Food production';
                break;
            case 'food_decay':
                $negativeBenefit = true;
                $description = 'Food decay';
                break;
            case 'gem_production':
                $negativeBenefit = false;
                $description = 'Gem production';
                break;
            case 'tech_production':
                $negativeBenefit = false;
                $description = 'XP production';
                break;
            case 'immortal_wizards':
                $negativeBenefit = false;
                $description = 'Immortal wizards';
                $booleanValue = true;
                break;
            case 'immortal_spies':
                $negativeBenefit = false;
                $description = 'Immortal spies';
                $booleanValue = true;
                break;
            case 'invest_bonus':
                $negativeBenefit = false;
                $description = 'Improvement points';
                break;
            case 'lumber_production':
                $negativeBenefit = false;
                $description = 'Lumber production';
                break;
            case 'mana_production':
                $negativeBenefit = false;
                $description = 'Mana production';
                break;
            case 'max_population':
                $negativeBenefit = false;
                $description = 'Max population';
                break;
            case 'offense':
                $negativeBenefit = false;
                $description = 'Offensive power';
                break;
            case 'ore_production':
                $negativeBenefit = false;
                $description = 'Ore production';
                break;
            case 'platinum_production':
                $negativeBenefit = false;
                $description = 'Platinum production';
                break;
            case 'spy_strength':
                $negativeBenefit = false;
                $description = 'Spy strength';
                break;
            case 'wizard_strength':
                $negativeBenefit = false;
                $description = 'Wizard strength';
                break;
            case 'cannot_construct':
                $negativeBenefit = true;
                $description = 'Cannot construct buildings';
                $booleanValue = true;
                break;
            case 'boat_capacity':
                $negativeBenefit = false;
                $description = 'Increased boat capacity';
                $valueType = ' units/boat';
                break;
            case 'platinum_production':
                $negativeBenefit = false;
                $description = 'Platinum production';
                $booleanValue = false;
                break;
            case 'can_invest_mana':
                $negativeBenefit = false;
                $description = 'Can use mana for improvements';
                $booleanValue = true;
                break;
            case 'can_invest_soul':
                $negativeBenefit = false;
                $description = 'Can use souls for improvements';
                $booleanValue = true;
                break;
            case 'population_growth':
                $negativeBenefit = false;
                $description = 'Population growth rate';
                break;
          case 'cannot_improve_castle':
                $negativeBenefit = true;
                $description = 'Cannot use improvements';
                $booleanValue = true;
                break;
          case 'cannot_explore':
                $negativeBenefit = true;
                $description = 'Cannot explore';
                $booleanValue = true;
                break;
          case 'cannot_invade':
                $negativeBenefit = true;
                $description = 'Cannot invade';
                $booleanValue = true;
                break;
          case 'cannot_train_spies':
                $negativeBenefit = true;
                $description = 'Cannot train spies';
                $booleanValue = true;
                break;
          case 'cannot_train_wizards':
                $negativeBenefit = true;
                $description = 'Cannot train wizards';
                $booleanValue = true;
                break;
          case 'cannot_train_archmages':
                $negativeBenefit = true;
                $description = 'Cannot train Arch Mages';
                $booleanValue = true;
                break;
          case 'explore_cost':
                $negativeBenefit = true;
                $description = 'Cost of exploration';
                break;
            case 'reduce_conversions':
                $negativeBenefit = false;
                $description = 'Reduced conversions';
                break;
            case 'exchange_bonus':
                $negativeBenefit = false;
                $description = 'Better exchange rates';
                break;
            case 'guard_tax_exemption':
                $negativeBenefit = false;
                $description = 'Exempt from guard platinum tax';
                $booleanValue = true;
                break;
          case 'tissue_improvement':
                $negativeBenefit = false;
                $description = 'Tissue improvements';
                $booleanValue = true;
                break;
          case 'does_not_kill':
                $negativeBenefit = false;
                $description = 'Does not kill units.';
                $booleanValue = true;
                break;
          case 'gryphon_nests_generates_wild_yetis':
                $negativeBenefit = false;
                $description = 'Traps wild yetis';
                $booleanValue = true;
                break;
            case 'prestige_gains':
                $negativeBenefit = false;
                $description = 'Prestige gains';
                break;
            case 'draftee_dp':
                $negativeBenefit = true;
                $description = 'DP per draftee';
                $booleanValue = 'static';
                break;
            case 'increased_construction_speed':
                $negativeBenefit = false;
                $description = 'Increased construction speed';
                $valueType = ' hours';
                break;
            case 'all_units_trained_in_9hrs':
                $negativeBenefit = false;
                $description = 'All units trained in 9 ticks';
                $booleanValue = true;
                break;
            case 'extra_barracks_housing':
                $negativeBenefit = false;
                $description = 'Barracks housing';
                $valueType = ' units';
                break;
            case 'cannot_build_homes':
                $negativeBenefit = true;
                $description = 'Cannot build homes';
                $booleanValue = true;
                break;
            case 'cannot_build_barracks':
                $negativeBenefit = true;
                $description = 'Cannot build barracks';
                $booleanValue = true;
                break;
            case 'improvements_max':
                $negativeBenefit = false;
                $description = 'Improvement bonuses max';
                break;
            case 'improvements_decay':
                $negativeBenefit = true;
                $description = 'Improvements decay';
                $valueType = '% per tick';
                $booleanValue = 'static';
                break;
            case 'tech_costs':
                $negativeBenefit = true;
                $description = 'Cost of technological advancements';
                break;
            case 'experience_points_per_acre':
                $negativeBenefit = false;
                $description = 'Experience points gained per acre on successful invasions';
                break;
            case 'cannot_tech':
                $negativeBenefit = true;
                $description = 'Cannot unlock advancements';
                $booleanValue = true;
                break;
            case 'construction_cost_only_platinum':
                $negativeBenefit = false;
                $description = 'Buildings only cost platinum';
                $booleanValue = true;
                break;
            case 'construction_cost_only_mana':
                $negativeBenefit = false;
                $description = 'Buildings only cost mana';
                $booleanValue = true;
                break;
            case 'construction_cost_only_food':
                $negativeBenefit = false;
                $description = 'Buildings only cost food';
                $booleanValue = true;
                break;
            case 'ore_improvement_points':
                $negativeBenefit = false;
                $description = 'Improvement points from ore';
                break;
            case 'lumber_improvement_points':
                $negativeBenefit = false;
                $description = 'Improvement points from lumber';
                break;
            case 'research_points_per_acre':
                $negativeBenefit = false;
                $description = 'Experience points per acre on invasions';
                break;
            case 'damage_from_lightning_bolts':
                $negativeBenefit = true;
                $description = 'Damage from Lightning Bolts';
                $booleanValue = false;
                break;
            case 'damage_from_fireballs':
                $negativeBenefit = true;
                $description = 'Damage from Fireballs';
                $booleanValue = false;
                break;
            case 'damage_from_insect_swarm':
                $negativeBenefit = true;
                $description = 'Effect from Insect Swarm';
                $booleanValue = false;
                break;
            case 'can_only_build_ziggurat':
                $negativeBenefit = false;
                $description = 'Can only build Ziggurats';
                $booleanValue = true;
                break;
            case 'defense_per_ziggurat':
                $negativeBenefit = false;
                $description = 'Raw DP per Ziggurat';
                $booleanValue = 'static';
                $valueType = '';
                break;
            case 'mana_per_ziggurat':
                $negativeBenefit = false;
                $description = 'Ziggurat mana production';
                $valueType = ' mana/tick';
                $booleanValue = 'static';
                break;
            case 'can_only_build_tissue':
                $negativeBenefit = false;
                $description = 'Can only build Tissue';
                $booleanValue = true;
                break;
            case 'can_only_build_mycelia':
                $negativeBenefit = false;
                $description = 'Can only build Mycelia';
                $booleanValue = true;
                break;
            case 'peasants_produce_food':
                $negativeBenefit = true;
                $description = 'Peasants produce food';
                $valueType = ' food/tick';
                $booleanValue = false;
                break;
            case 'unemployed_peasants_produce_platinum':
                $negativeBenefit = true;
                $description = 'Unemployed peasants produce platinum';
                $valueType = ' platinum/tick';
                $booleanValue = false;
                break;
            case 'draftee_mana_production':
                $negativeBenefit = false;
                $description = 'Draftees produce mana';
                $valueType = ' mana/tick';
                $booleanValue = false;
                break;
            case 'cannot_join_guards':
                $negativeBenefit = true;
                $description = 'Cannot join guards';
                $booleanValue = true;
                break;
            case 'converts_killed_spies_into_souls':
                $negativeBenefit = true;
                $description = 'Converts killed spies into souls';
                $booleanValue = true;
                break;
            case 'mana_drain':
                $negativeBenefit = true;
                $description = 'Mana drain';
                $booleanValue = false;
                break;
            case 'can_sell_food':
                $negativeBenefit = false;
                $description = 'Can exchange food';
                $booleanValue = true;
                break;
            case 'can_sell_mana':
                $negativeBenefit = false;
                $description = 'Can exchange mana';
                $booleanValue = true;
                break;
            case 'draftees_cannot_be_abducted':
                $negativeBenefit = false;
                $description = 'Draftees cannot be abducted';
                $booleanValue = true;
                break;
            case 'peasants_cannot_be_abducted':
                $negativeBenefit = false;
                $description = 'Peasants cannot be abducted';
                $booleanValue = true;
                break;
            case 'population_from_alchemy':
                $negativeBenefit = false;
                $description = 'Extra population per 1% Alchemies (max +20%)';
                $booleanValue = false;
                break;
            case 'defense_from_forest':
                $negativeBenefit = false;
                $description = 'Defensive modifier per 1% Forest';
                $booleanValue = false;
                break;
            case 'forest_construction_cost':
                $negativeBenefit = true;
                $description = 'Forest construction cost';
                break;



            default:
                return null;
        }

        $result = ['description' => $description, 'value' => ''];
        $valueString = "{$perkType->pivot->value}{$valueType}";

        if ($perkType->pivot->value < 0)
        {

            if($booleanValue === true)
            {
                $valueString = 'No';
            }
            else

            if($booleanValue == 'static')
            {
              $valueString = $perkType->pivot->value . $valueType;
            }

            if ($negativeBenefit === true)
            {
                $result['value'] = "<span class=\"text-green\">{$valueString}</span>";
            }
            elseif($booleanValue == 'static')
            {
                $result['value'] = "<span class=\"text-blue\">{$valueString}</span>";
            }
            else
            {
                $result['value'] = "<span class=\"text-red\">{$valueString}</span>";
            }
        }
        else
        {
            $prefix = '+';
            if($booleanValue === true)
            {
                $valueString = 'Yes';
                $prefix = '';
            }
            elseif($booleanValue == 'static')
            {
              $valueString = $perkType->pivot->value . $valueType;
              $prefix = '';
            }

            if ($negativeBenefit === true)
            {
                $result['value'] = "<span class=\"text-red\">{$prefix}{$valueString}</span>";
            }
            elseif($booleanValue == 'static')
            {
                $result['value'] = "<span class=\"text-blue\">{$prefix}{$valueString}</span>";
            }
            else
            {
                $result['value'] = "<span class=\"text-green\">{$prefix}{$valueString}</span>";
            }
        }

        return $result;
    }

}
