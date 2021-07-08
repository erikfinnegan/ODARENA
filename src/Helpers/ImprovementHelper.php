<?php

namespace OpenDominion\Helpers;
use Illuminate\Support\Collection;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Race;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;

use OpenDominion\Models\Improvement;
use OpenDominion\Models\ImprovementPerk;

class ImprovementHelper
{

    /** @var ImprovementCalculator */
    protected $improvementCalculator;

    public function __construct()
    {
        $this->improvementCalculator = app(ImprovementCalculator::class);
    }

    public function getImprovementTypes(?Dominion $dominion): array
    {

      $improvementTypes = [
          'markets',
          'keep',
          'forges',
          'walls',
          'armory',
          'infirmary',
          'workshops',
          'observatory',
          'cartography',
          #'towers',
          'spires',
          'hideouts',
          'granaries',
          'harbor',
          'forestry',
          'refinery',
        ];

      if($dominion)
      {
          if($dominion->race->getPerkValue('tissue_improvement'))
          {
              return ['tissue'];
          }
          else
          {
              return $improvementTypes;
          }
      }
      else
      {
          $improvementTypes[] = 'tissue';
      }

      return $improvementTypes;

    }
    /*
    public function getImprovementRatingString(string $improvementType): string
    {
        $ratingStrings = [
            'markets' => '+%s%% gold production',
            'keep' => '+%s%% max population',
            #'towers' => '+%1$s%% wizard power, +%1$s%% mana production, -%1$s%% damage from spells, +%1$s%% Wizard Guild wizard housing',
            'spires' => '+%1$s%% wizard power, +%1$s%% mana production, -%1$s%% damage from spells, +%1$s%% Wizard Guild wizard housing',
            'forges' => '+%s%% offensive power',
            'walls' => '+%s%% defensive power',
            'harbor' => '+%s%% food production',
            'armory' => '-%s%% military training gold and ore costs',
            'infirmary' => '-%s%% casualties in battle',
            'workshops' => '-%s%% construction and rezoning costs',
            'observatory' => '+%1$s%% experience points gained through invasions, exploration, and production',
            'cartography' => '+%1$s%% land discovered during invasions, -%1$s%% cost of exploring (max -50%% total reduction)',
            'hideouts' => '+%1$s%% spy power, -%1$s%% spy losses, +%1$s%% Forest Haven spy housing',
            'forestry' => '+%s%% lumber production',
            'refinery' => '+%s%% ore production',
            'granaries' => '-%s%% lumber and food rot',
            'tissue' => '+%s%% housing and food production',
        ];

        return $ratingStrings[$improvementType] ?: null;
    }

    public function getImprovementHelpString(string $improvementType, Dominion $dominion): string
    {
        $helpStrings = [
            'markets' => 'Markets increase your gold production.<br><br>Max +',
            'keep' => 'Keep increases population housing of barren land and all buildings except for Barracks.<br><br>Max +',
            #'towers' => 'Towers increase your wizard power, mana production, reduce damage from offensive spells, and increase Wizard Guild housing.<br><br>Max +',
            'spires' => 'Spires increase your wizard power, mana production, reduce damage from offensive spells, and increase Wizard Guild housing.<br><br>Max +',
            'forges' => 'Forges increase your offensive power.<br><br>Max +',
            'walls' => 'Walls increase your defensive power.<br><br>Max +',
            'harbor' => 'Harbor increases your food and boat production and protects boats from sinking.<br><br>Max +',
            'armory' => 'Armory decreases your unit gold and ore training costs.<br><br>Max ',
            'infirmary' => 'Infirmary reduces casualties suffered in battle (offensive and defensive).<br><br>Max ',
            'workshops' => 'Workshop reduces construction and rezoning costs.<br><br>Max ',
            'observatory' => 'Observatory increases experience points gained through invasions, exploration, and production.<br><br>Max ',
            'cartography' => 'Cartography increases land discovered on attacks and reduces gold cost of exploring.<br><br>Max ',
            'hideouts' => 'Hidehouts increase your spy power, reduce spy losses, and increase Forest Haven housing.<br><br>Max ',
            'forestry' => 'Forestry increases your lumber production.<br><br>Max ',
            'refinery' => 'Refinery increases your ore production.<br><br>Max ',
            'granaries' => 'Granaries reduce food and lumber rot.<br><br>Max ',
            'tissue' => 'Feed the tissue to grow and feed more cells.<br><br>Max ',
        ];

        return ($helpStrings[$improvementType] . $this->improvementCalculator->getImprovementMaximum($improvementType, $dominion) * 100 .'%') ?: null;
    }

    public function getImprovementIcon(string $improvementKey): string
    {
        $icons = [
            'markets' => 'hive-emblem',
            'keep' => 'capitol',
            'spires' => 'fairy-wand',
            'forges' => 'forging',
            'walls' => 'shield',
            'harbor' => 'anchor',
            'armory' => 'helmet',
            'infirmary' => 'health',
            'workshops' => 'nails',
            'observatory' => 'telescope',
            'cartography' => 'compass',
            'hideouts' => 'hood',
            'forestry' => 'pine-tree',
            'refinery' => 'large-hammer',
            'granaries' => 'vase',
            'tissue' => 'thorny-vine',
        ];

        return $icons[$improvementKey] ? $icons[$improvementKey] : 'fairy-wand';

    }
    */

    # IMPROVEMENTS 2.0

    public function getImprovementPerkDescription(string $improvementPerk): string
    {

        $improvementPerkDescriptions = [
            'gold_production' => 'gold production',
            'ore_production' => 'ore production',
            'lumber_production' => 'lumber production',
            'gem_production' => 'gem production',
            'mana_production' => 'mana production',
            'food_production' => 'food production',
            'tech_production' => 'XP generation',

            'exchange_rate' => 'exchange rate',

            'tech_gains' => 'XP gained',
            'tech_costs' => 'advancements costs',

            'population' => 'population',
            'population_growth' => 'population growth',

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

            'training_time' => 'unit training time',
            'chance_of_instant_return' => 'chance of units returning instantly from invasion',

            'offensive_power' => 'offensive power',
            'defensive_power' => 'defensive power',
            'casualties' => 'casualties',
            'offensive_casualties' => 'offensive casualties',
            'defensive_casualties' => 'defensive casualties',
            'defensive_modifier_reduction' => 'reduced defensive modifiers for target',

            'prestige_gains' => 'prestige gains',

            'spy_strength' => 'spy strength',
            'spy_losses' => 'spy losses',
            'forest_haven_housing' => 'Forest Haven housing',

            'wizard_strength' => 'wizard strength',
            'wizard_losses' => 'wizard losses',
            'spell_damage' => 'spell damage',
            'wizard_guild_housing' => 'Wizard Guild housing',

            'unit_pairing' => 'unit pairing',

            'improvement_points' => 'improvement points value from investments',

            'title_bonus' => 'ruler title bonus'

        ];

        return $improvementPerkDescriptions[$improvementPerk] ? : 'Missing description';
    }

    /*
    *   Returns buildings available for the race.
    *   If $landType is present, only return buildings for the race for that land type.
    */
    public function getImprovementsByRace(Race $race): Collection
    {
        $improvements = collect(Improvement::all()->keyBy('key')->sortBy('name')->where('enabled',1));

        foreach($improvements as $improvement)
        {
          if(
                (count($improvement->excluded_races) > 0 and in_array($race->name, $improvement->excluded_races)) or
                (count($improvement->exclusive_races) > 0 and !in_array($race->name, $improvement->exclusive_races))
            )
          {
              $improvements->forget($improvement->key);
          }
        }

        return $improvements;
    }

    public function getImprovementKeys(): array
    {
        $improvements = collect(Improvement::all()->keyBy('key')->sortBy('name')->where('enabled',1));
    }

    public function extractImprovementPerkValuesForScribes(string $perkValue)
    {
        if (str_contains($perkValue, ','))
        {
            $perkValues = explode(',', $perkValue);

            foreach($perkValues as $key => $value)
            {
                if (!str_contains($value, ';'))
                {
                    continue;
                }

                $perkValues[$key] = explode(';', $value);
            }
        }

        return $perkValues;
    }

    public function getExclusivityString(Improvement $improvement): string
    {

        $exclusivityString = '<br><small class="text-muted">';

        if($exclusives = count($improvement->exclusive_races))
        {
            foreach($improvement->exclusive_races as $raceName)
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
        elseif($excludes = count($improvement->excluded_races))
        {
            $exclusivityString .= 'All except ';
            foreach($improvement->excluded_races as $raceName)
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
