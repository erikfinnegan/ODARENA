<?php

namespace OpenDominion\Helpers;

use OpenDominion\Models\Race;
use OpenDominion\Models\Dominion;

use OpenDominion\Models\Building;

class BuildingHelper
{

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
          'diamond_mine',
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
            $buildings = ['home','farm','tower','diamond_mine','lumberyard', 'ore_mine','barracks','dock'];
          }
          if($dominion->race->name == 'Merfolk')
          {
            $buildings = ['home','farm','tower','diamond_mine','temple','shrine'];
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
          #if($dominion->race->name == 'Swarm')
          #{
          #  $buildings = ['tunnels'];
          #}
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
              'diamond_mine',
          ],
          'swamp' => [
              'tower',
              'wizard_guild',
              'temple',
          ],/*
          'cavern' => [
              'diamond_mine',
              'school',
          ],*/
          'forest' => [
              'lumberyard',
              'forest_haven',
          ],
          'hill' => [
              'factory',
              'guard_tower',
              'shrine',
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
                    'diamond_mine',
                ],
                'swamp' => [],
                'forest' => [
                    'lumberyard',
                ],
                'hill' => [
                  'barracks',
                ],
                'water' => [
                    'dock',
                ],
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
                  'diamond_mine',
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
            'home' => ['Houses 30 people.'],
            'alchemy' => ['Produces 45 platinum/tick.'],
            'farm' => ['Produces 80 food/tick.'],
            'smithy' => ['Reduces unit platinum and ore training costs by 2% per 1% owned','Maximum 40% at 20% owned.','Does not affect Gnome or Imperial Gnome ore costs.'],
            'masonry' => ['Improvements increased by 2.75% per 1% owned.','Lightning bolt damage reduced by 0.75% per 1% owned.'],
            'ore_mine' => ['Produces 60 ore/tick.'],
            'gryphon_nest' => ['Offensive power increased by +2% per 1% owned.', 'Maximum +40% OP at 20% owned.'],
            'tower' => ['Produces 25 mana/tick.'],
            'wizard_guild' => ['Houses 40 wizards, Arch Mages, and units that count as wizards (not affected by population bonus), including units in training.', 'Wizard Strength refresh rate increased by 0.1% per 1% owned (max +2% at 20% owned).','Wizard and ArchMage training and spell costs reduced by 2% per 1% owned (maximum 40% at 20% owned).'],
            'temple' => ['Population growth increased by 6% per 1% owned.','Defensive bonuses reduced by 2% per 1% owned (maximum of 40% at 20% owned).'],
            'diamond_mine' => ['Produces 15 gems/tick.'],
            'lumberyard' => ['Produces 50 lumber/tick.'],
            'forest_haven' => ['Houses 40 spies and units that count as spies (not affected by population bonus), including units in training.','Failed spy ops losses reduced by 3% per 1% owned, up to a maximum of 30% at 10% owned.','Fireball damage and platinum theft reduced by 8% per 1% owned.'],
            'factory' => ['Construction costs reduced by 4% per 1% owned (maximum of 80% at 20% owned).','Rezoning costs reduced by 3% per 1% owned (maximum of 60% at 20% owned).'],
            'guard_tower' => ['Defensive power increased by +2% per 1% owned.', 'Maximum +40% DP at 20% owned.'],
            'shrine' => ['Reduces offensive casualties reduced by 5% per 1% owned, up to a maximum of -75% at 15% owned.','Reduces defensive casualties by 1% per 1% owned, up to a maximum of 15%.'],
            'barracks' => ['Houses 36 trained or training military units, including units in training.','Not affected by population bonuses.'],
            'dock' => ['Produces 1 boat every 20 ticks.','Produces 35 food/tick.','Protects 2.5 docks from being sunk.'],
            'ziggurat' => ['Produces 30 mana/tick','Provides 4 raw defensive power.'],
            'tissue' => ['Houses 160 cells, amoeba, or units.','Produces 4 food/tick.'],
            'mycelia' => ['Houses 30 people or units.','Produces 4 food/tick.']
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

            # Production
            'platinum_production' => 'Produces %s platinum per tick.',
            'food_production' => 'Produces %s food per tick.',
            'lumber_production' => 'Produces %s lumber per tick.',
            'ore_production' => 'Produces %s ore per tick.',
            'gem_production' => 'Produces %s gems per tick.',
            'mana_production' => 'Produces %s mana per tick.',
            'boat_production' => 'Produces %s boats per tick.',

            # Mods
            'improvements' => 'Improvements increased by %2$s%% for every %1$s%%.',
            'offensive_power' => 'Offenive power increased by %2$s%% for every %1$s%% (max +%3$s%% OP)',
            'defensive_power' => 'Defensive power increased by %2$s%% for every %1$s%% (max +%3$s%% DP).',
            'defensive_modifier_reduction' => 'Reduces target\'s defensive modifiers by by %2$s%% for every %1$s%% (max %3$s%% reduction or 0%% defensive modifiers).',
            'offensive_casualties' => 'Offensive casualties decreased by %2$s%% for every %1$s%% (max %3$s%% reduction).',
            'defensive_casualties' => 'Defensive casualties decreased by %2$s%% for every %1$s%% (max %3$s%% reduction).',
            'unit_cost' => 'Unit platinum and ore costs %2$s%% for every %1$s%% (max %3$s%% reduction). No ore cost reduction for Gnome or Imperial Gnome.',
            'construction_cost' => 'Construction costs decreased by %2$s%% for every %1$s%% (max %3$s%% reduction).',
            'rezone_cost' => 'Rezoning costs decreased by %2$s%% for every %1$s%% (max %3$s%% reduction).',

            # Other
            'boat_protection' => 'Protects %s boats from sabotage.',
            'raw_defense' => 'Provides %s raw defensive power.',

            # Espionage and Wizardry
            'spy_losses' => 'Spy losses decreased by %2$s%% for every %1$s%% (max %3$s%% reduction).',
            'fireball_damage' => 'Damage from fireballs reduced by %2$s%% for every %1$s%%.',
            'lightning_bolt_damage' => 'Damage from lightning bolts reduced by %2$s%% for every %1$s%%.',
            'platinum_theft_reduction' => 'Platinum stolen from you reduced by %2$s%% for every %1$s%%.',
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
    public function getBuildingsByRace(Race $race): array
    {
      $allBuildings = Building::all()->keyBy('key');

      $buildings = [];
      foreach($allBuildings as $building)
      {
        if(
              (count($building->excluded_races) > 0 and !in_array($race->name, $building->excluded_races)) or
              (count($building->exclusive_races) > 0 and in_array($race->name, $building->exclusive_races))
          )
        {
          $buildings[] = $building;
        }
      }

      return $buildings;
    }

}
