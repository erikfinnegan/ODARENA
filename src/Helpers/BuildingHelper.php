<?php

namespace OpenDominion\Helpers;
use Illuminate\Support\Collection;
use OpenDominion\Models\Race;
use OpenDominion\Models\Dominion;

use OpenDominion\Models\Building;

class BuildingHelper
{

    public function getBuildingKeys(): Collection
    {
        return Building::where('enabled',1)->pluck('key');
    }

    public function getBuildingLandType(Building $building, Race $race): string
    {
        if($building->land_type === 'home')
        {
            return $race->home_land_type;
        }

        return $building->land_type;
    }

    public function getBuildingTypes(Dominion $dominion = null): array
    {

      $buildings = [
          'home',
          'alchemy',
          'farm',
          'smithy',
          'masonry',
          'ore_mine',
          'gryphon_nest',
          'tower',
          'wizard_guild',
          'temple',
          'gem_mine',
          #'school',
          'lumberyard',
          'forest_haven',
          'factory',
          'guard_tower',
          'shrine',
          'barracks',
          'dock',
        ];

        if($dominion !== null)
        {
            // Ugly, but works.
            if($dominion->race->name == 'Dragon')
            {
              #$forbiddenBuildings = ['alchemy', 'smithy', 'masonry', 'ore_mine', 'gryphon_nest', 'wizard_guild', 'temple', 'school', 'forest_haven', 'factory', 'guard_tower', 'shrine', 'barracks', 'dock'];
              $buildings = ['home','farm','tower','gem_mine','lumberyard', 'ore_mine','guard_tower'];
            }
            if($dominion->race->name == 'Merfolk')
            {
              $buildings = ['home','farm','tower','gem_mine','temple','shrine'];
            }
            if($dominion->race->name == 'Void')
            {
              $buildings = ['ziggurat'];
            }
            if($dominion->race->name == 'Growth')
            {
              $buildings = ['tissue'];
            }
            if($dominion->race->name == 'Myconid')
            {
              $buildings = ['mycelia'];
            }
        }

      return $buildings;

    }

    public function getBuildingTypesByRace(Dominion $dominion = null): array
    {

      $buildings = [
          'plain' => [
              'alchemy',
              'farm',
              'smithy',
              'masonry',
          ],
          'mountain' => [
              'ore_mine',
              'gryphon_nest',
              'gem_mine',
          ],
          'swamp' => [
              'tower',
              'wizard_guild',
              'temple',
          ],/*
          'cavern' => [
              'gem_mine',
              'school',
          ],*/
          'forest' => [
              'lumberyard',
              'forest_haven',
              'shrine',
          ],
          'hill' => [
              'factory',
              'guard_tower',
              'barracks',
          ],
          'water' => [
              'dock',
          ],
      ];

        if($dominion !== null)
        {
          if($dominion->race->name == 'Dragon')
          {
            $buildings = [
                'plain' => [],
                'mountain' => [
                    'tower',
                    'farm',
                    'ore_mine',
                    'gem_mine',
                ],
                'swamp' => [],
                'forest' => [
                    'lumberyard',
                ],
                'hill' => [
                  'guard_tower',
                ],
                'water' => [],
            ];
          }
          elseif($dominion->race->name == 'Merfolk')
          {
            $buildings = [
                'plain' => [],
                'mountain' => [],
                'swamp' => [],
                'forest' => [],
                'hill' => [],
                'water' => [
                  'farm',
                  'tower',
                  'temple',
                  'gem_mine',
                  'shrine',
                ],
            ];
          }
          elseif($dominion->race->name == 'Void')
          {
            $buildings = [
                'plain' => [],
                'mountain' => ['ziggurat'],
                'swamp' => [],
                'forest' => [],
                'hill' => [],
                'water' => [],
            ];
          }
          elseif($dominion->race->name == 'Growth')
          {
            $buildings = [
                'plain' => [],
                'mountain' => [],
                'swamp' => ['tissue'],
                'forest' => [],
                'hill' => [],
                'water' => [],
            ];
          }
          elseif($dominion->race->name == 'Myconid')
          {
          $buildings = [
              'plain' => [],
              'mountain' => [],
              'swamp' => [],
              'forest' => ['mycelia'],
              'hill' => [],
              'water' => [],
          ];
          }
          /*
          elseif($dominion->race->name == 'Swarm')
          {
          $buildings = [
              'plain' => ['tunnels'],
              'mountain' => ['tunnels'],
              'swamp' => ['tunnels'],
              'forest' => ['tunnels'],
              'hill' => ['tunnels'],
              'water' => ['tunnels'],
          ];
          }
          */

          if(!$dominion->race->getPerkValue('cannot_build_homes'))
          {
              array_unshift($buildings[$dominion->race->home_land_type], 'home');
          }

          if($dominion->race->getPerkValue('cannot_build_barracks'))
          {
              $buildings['hill'] = array_diff($buildings['hill'], array('barracks'));
          }

          if($dominion->race->getPerkValue('cannot_build_wizard_guilds'))
          {
              $buildings['swamp'] = array_diff($buildings['swamp'], array('wizard_guild'));
          }

          if($dominion->race->getPerkValue('cannot_build_forest_havens'))
          {
              $buildings['forest'] = array_diff($buildings['forest'], array('forest_haven'));
          }

        }

        #array_unshift($buildings[$dominion->race->home_land_type], 'home');

        return $buildings;
    }

    public function getBuildingHelpString(string $buildingType): string
    {
        $helpStrings = [
            'alchemy' => ['Produces 45 gold/tick.'],
            'barracks' => ['Houses 36 trained or training military units, including units in training.','Not affected by population bonuses.'],
            'gem_mine' => ['Produces 15 gems/tick.'],
            'dock' => ['Produces 1 boat every 20 ticks.','Produces 35 food/tick.','Protects 2.5 boats from being sunk.'],
            'farm' => ['Produces 80 food/tick.'],
            'factory' => ['Construction costs reduced by 4% per 1% owned (maximum of 80% at 20% owned).','Rezoning costs reduced by 3% per 1% owned (maximum of 60% at 20% owned).'],
            'forest_haven' => ['Houses 40 spies and units that count as spies, including units in training. Not affected by population bonuses. Increased by Hideouts improvements.','Failed spy ops losses reduced by 3% per 1% owned, up to a maximum of 30% at 10% owned.','Fireball damage and gold theft reduced by 8% per 1% owned.'],
            'gryphon_nest' => ['Offensive power increased by +1.8% per 1% owned.', 'Maximum +36% OP at 20% owned.'],
            'guard_tower' => ['Defensive power increased by +1.8% per 1% owned.', 'Maximum +36% DP at 20% owned.'],
            'home' => ['Houses 30 people.'],
            'lumberyard' => ['Produces 50 lumber/tick.'],
            'masonry' => ['Improvements increased by 2.75% per 1% owned.','Lightning bolt damage reduced by 0.80% per 1% owned.'],
            'mycelia' => ['Houses 30 people or units.','Produces 4 food/tick.'],
            'ore_mine' => ['Produces 60 ore/tick.'],
            'shrine' => ['Reduces offensive casualties reduced by 5% per 1% owned, up to a maximum of -75% at 15% owned.','Reduces defensive casualties by 1% per 1% owned, up to a maximum of 15%.'],
            'smithy' => ['Reduces unit gold and ore training costs by 2% per 1% owned.','Maximum 40% at 20% owned.','Does not affect Gnome or Imperial Gnome ore costs.'],
            'temple' => ['Population growth increased by 6% per 1% owned.','Defensive bonuses reduced by 1.8% per 1% owned (maximum of 36% at 20% owned).'],
            'tissue' => ['Houses 160 cells, amoeba, or units.','Produces 4 food/tick.'],
            'tower' => ['Produces 25 mana/tick.'],
            'wizard_guild' => ['Houses 40 wizards, Arch Mages, and units that count as wizards, including units in training. Not affected by population bonuses. Increased by Spires improvements.', 'Wizard Strength refresh rate increased by 0.1% per 1% owned (max +2% at 20% owned).','Wizard and ArchMage training and spell costs reduced by 2% per 1% owned (maximum 40% at 20% owned).'],
            'ziggurat' => ['Produces 30 mana/tick','Provides 4 raw defensive power.'],
        ];

        $string = '<ul>';
        foreach($helpStrings[$buildingType] as $item)
        {
            $string .= '<li>' . $item . '</li>';
        }

        $string .= '<ul>';

        return $string;
    }


    public function getBuildingDescription(Building $building): ?string
    {

        $helpStrings[$building->name] = '';

        $perkTypeStrings = [
            # Housing
            'housing' => 'Houses %s people.',
            'military_housing' => 'Houses %s military units.',
            'wizard_housing' => 'Houses %1$s wizards and units that count as wizards.',
            'spy_housing' => 'Houses %1$s spies and units that count as spies.',
            'draftee_housing' => 'Houses %s draftees.',
            'peasant_housing' => 'Houses %s peasants.',

            'jobs' => 'Provides %s jobs.',

            # Production
            'gold_production' => 'Produces %s gold per tick.',
            'food_production' => 'Produces %s food per tick.',
            'lumber_production' => 'Produces %s lumber per tick.',
            'ore_production' => 'Produces %s ore per tick.',
            'gem_production' => 'Produces %s gems per tick.',
            'mana_production' => 'Produces %s mana per tick.',
            'boat_production' => 'Produces %s boats per tick.',

            # Military
            'offensive_casualties' => 'Offensive casualties decreased by %2$s%% for every %1$s%% (max %3$s%% reduction).',
            'defensive_casualties' => 'Defensive casualties decreased by %2$s%% for every %1$s%% (max %3$s%% reduction).',

            'boat_protection' => 'Protects %s boats from sabotage.',

            'unit_gold_costs' => 'Unit gold costs %2$s%% for every %1$s%% (max %3$s%% reduction).',
            'unit_ore_costs' => 'Unit ore costs %2$s%% for every %1$s%% (max %3$s%% reduction).',
            'unit_lumber_costs' => 'Unit lumber costs %2$s%% for every %1$s%% (max %3$s%% reduction).',
            'unit_mana_costs' => 'Unit mana costs %2$s%% for every %1$s%% (max %3$s%% reduction).',
            'unit_blood_costs' => 'Unit blood costs %2$s%% for every %1$s%% (max %3$s%% reduction).',

            'morale_gains' => 'Morale gains increased by %2$s%% for every %1$s%% (max +%3$s%%)',
            'base_morale' => 'Base morale increased by %2$s%% for every %1$s%% (max +%3$s%%)',
            'prestige_gains' => 'Prestige gains increased by %2$s%% for every %1$s%% (max +%3$s%%)',

            # OP/DP
            'raw_defense' => 'Provides %s raw defensive power.',
            'offensive_power' => 'Offenive power increased by %2$s%% for every %1$s%% (max +%3$s%% OP)',
            'defensive_power' => 'Defensive power increased by %2$s%% for every %1$s%% (max +%3$s%% DP).',
            'defensive_modifier_reduction' => 'Reduces target\'s defensive modifiers by by %2$s%% for every %1$s%% (max %3$s%% reduction or 0%% defensive modifiers).',

            # Improvements
            'improvements' => 'Improvements increased by %2$s%% for every %1$s%%.',

            # Construction and Rezoning
            'construction_cost' => 'Construction costs decreased by %2$s%% for every %1$s%% (max %3$s%% reduction).',
            'rezone_cost' => 'Rezoning costs decreased by %2$s%% for every %1$s%% (max %3$s%% reduction).',

            # Espionage and Wizardry
            'spy_losses' => 'Spy losses decreased by %2$s%% for every %1$s%% (max %3$s%% reduction).',
            'fireball_damage' => 'Damage from fireballs reduced by %2$s%% for every %1$s%%.',
            'lightning_bolt_damage' => 'Damage from lightning bolts reduced by %2$s%% for every %1$s%%.',
            'gold_theft_reduction' => 'Gold stolen from you reduced by %2$s%% for every %1$s%%.',
            'wizard_cost' => 'Wizard and arch mage training costs decreased by %2$s%% for every %1$s%% (max %3$s%% reduction).',
            'spell_cost' => 'Spell mana costs decreased by %2$s%% for every %1$s%% (max %3$s%% reduction).',

        ];

        foreach ($building->perks as $perk)
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
                        $helpStrings[$building->name] .= ('<li>' . vsprintf($perkTypeStrings[$perk->key], $nestedValue) . '</li>');
                    }
                }
                else
                {
                    $helpStrings[$building->name] .= ('<li>' . vsprintf($perkTypeStrings[$perk->key], $perkValue) . '</li>');
                }
            }
            else
            {
                $helpStrings[$building->name] .= ('<li>' . sprintf($perkTypeStrings[$perk->key], $perkValue) . '</li>');
            }
        }

        if(strlen($helpStrings[$building->name]) == 0)
        {
          $helpStrings[$building->name] = '<i>No special abilities</i>';
        }
        else
        {
          $helpStrings[$building->name] = '<ul>' . $helpStrings[$building->name] . '</ul>';
        }

        return $helpStrings[$building->name] ?: null;
    }

    /*
    *   Returns buildings available for the race.
    */
    public function getBuildingsByRace(Race $race): Collection
    {
        $buildings = collect(Building::all()->keyBy('key')->sortBy('name')->sortBy('land_type')->where('enabled',1));

        foreach($buildings as $building)
        {
          if(
                (count($building->excluded_races) > 0 and in_array($race->name, $building->excluded_races)) or
                (count($building->exclusive_races) > 0 and !in_array($race->name, $building->exclusive_races))
            )
          {
              $buildings->forget($building->key);
          }
        }

        return $buildings;
    }

    public function getExclusivityString(Building $building): string
    {

        $exclusivityString = '<br><small class="text-muted">';

        if($exclusives = count($building->exclusive_races))
        {
            foreach($building->exclusive_races as $raceName)
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
        elseif($excludes = count($building->excluded_races))
        {
            $exclusivityString .= 'All except ';
            foreach($building->excluded_races as $raceName)
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
