<?php

namespace OpenDominion\Helpers;
use Illuminate\Support\Collection;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Race;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;

use OpenDominion\Models\Improvement;

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

    public function getImprovementIcon(string $improvement): string
    {
        $icons = [
            'markets' => 'hive-emblem',
            'keep' => 'capitol',
            #'towers' => 'fairy-wand',
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

        return $icons[$improvement];
    }


    # BUILDINGS 2.0

    public function getImprovementDescription(Improvement $improvement): ?string
    {

        $helpStrings[$improvement->name] = '';

        $perkTypeStrings = [
            'gold_production' => 'Gold production (max +%1$s%%)',
            'ore_production' => 'Ore production (max +%1$s%%)',
            'lumber_production' => 'Lumber production (max +%1$s%%)',
            'gem_production' => 'Gem production (max +%1$s%%)',
            'mana_production' => 'Mana production (max +%1$s%%)',
            'food_production' => 'Food production (max +%1$s%%)',
            'tech_production' => 'XP generation (max +%1$s%%)',

            'tech_gains' => 'XP gained (max +%1$s%%)',

            'population' => 'Population (max +%1$s%%)',
            'population_growth' => 'Population growth (max +%1$s%%)',

            'construction_cost' => 'Reduced construction costs (max +%1$s%%)',
            'rezone_cost' => 'Reduced rezoning costs (max +%1$s%%)',

            'explore_gold_cost' => 'Reduced exploration gold costs (max +%1$s%%)',
            'land_discovered' => 'Land discovered (max +%1$s%%)',

            'unit_gold_costs' => 'Reduced unit gold costs (max +%1$s%%)',
            'unit_ore_costs' => 'Reduced unit ore costs (max +%1$s%%)',
            'unit_lumber_costs' => 'Reduced unit lumber costs (max +%1$s%%)',
            'unit_gem_costs' => 'Reduced unit gem costs (max +%1$s%%)',
            'unit_mana_costs' => 'Reduced unit mana costs (max +%1$s%%)',
            'unit_food_costs' => 'Reduced unit food costs (max +%1$s%%)',

            'offensive_power' => 'Offensive power (max +%1$s%%)',
            'defensive_power' => 'Defensive power (max +%1$s%%)',
            'casualties' => 'Reduced casualties (max +%1$s%%)',
            'offensive_casualties' => 'Reduced offensive casualties (max +%1$s%%)',
            'defensive_casualties' => 'Reduced defensive casualties (max +%1$s%%)',

            'spy_strength' => 'Spy strength (max +%1$s%%)',
            'spy_losses' => 'Reduced spy losses (max +%1$s%%)',
            'forest_haven_housing' => 'Forest Haven housing (max +%1$s%%)',

            'wizard_strength' => 'Wizard strength (max +%1$s%%)',
            'wizard_losses' => 'Reduced wizard losses (max +%1$s%%)',
            'spell_damage' => 'Reduced spell damage (max +%1$s%%)',
            'wizard_guild_housing' => 'Wizard Guild housing (max +%1$s%%)',

        ];


        foreach ($improvement->perks as $perk)
        {
            if (!array_key_exists($perk->key, $perkTypeStrings))
            {
                continue;
            }

            $perkValue = $perk->pivot->value;

            $nestedArrays = false;
            if (str_contains($perkValue, ','))
            {
                $perkValue = explode(',', $perkValue);

                foreach ($perkValue as $key => $value)
                {
                    if (!str_contains($value, ';'))
                    {
                        continue;
                    }

                    $nestedArrays = true;
                    $perkValue[$key] = explode(';', $value);
                }
            }

            if (is_array($perkValue))
            {
                if ($nestedArrays)
                {
                    foreach ($perkValue as $nestedKey => $nestedValue)
                    {
                        $helpStrings[$improvement->name] .= ('<li>' . vsprintf($perkTypeStrings[$perk->key], $nestedValue) . '</li>');
                    }
                }
                else
                {
                    $helpStrings[$improvement->name] .= ('<li>' . vsprintf($perkTypeStrings[$perk->key], $perkValue) . '</li>');
                }
            }
            else
            {
                $helpStrings[$improvement->name] .= ('<li>' . sprintf($perkTypeStrings[$perk->key], $perkValue) . '</li>');
            }
        }


        if(strlen($helpStrings[$improvement->name]) == 0)
        {
          $helpStrings[$improvement->name] = '<i>No special abilities</i>';
        }
        else
        {
          $helpStrings[$improvement->name] = '<ul>' . $helpStrings[$improvement->name] . '</ul>';
        }

        return $helpStrings[$improvement->name] ?: null;
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
