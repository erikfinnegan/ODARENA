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
          if($dominion->race->name == 'Swarm')
          {
            $buildings = ['tunnels'];
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

          if(!$dominion->race->getPerkValue('cannot_build_homes'))
          {
              array_unshift($buildings[$dominion->race->home_land_type], 'home');
          }

          if($dominion->race->getPerkValue('cannot_build_barracks'))
          {
              $buildings['hill'] = array_diff($buildings['hill'], array('barracks'));
          }

        }

        #array_unshift($buildings[$dominion->race->home_land_type], 'home');

        return $buildings;
    }

    // temp
    public function getBuildingImplementedString(string $buildingType): ?string
    {
        // 0 = nyi
        // 1 = partial implemented
        // 2 = implemented

        $buildingTypes = [
            'home' => 2,
            'alchemy' => 2,
            'farm' => 2,
            'smithy' => 2,
            'masonry' => 2,
            'ore_mine' => 2,
            'gryphon_nest' => 2,
            'tower' => 2,
            'wizard_guild' => 2,
            'temple' => 2,
            'diamond_mine' => 2,
            #'school' => 2,
            'lumberyard' => 2,
            'forest_haven' => 2,
            'factory' => 2,
            'guard_tower' => 2,
            'shrine' => 2,
            'barracks' => 2,
            'dock' => 2,

            # ODA
            'ziggurat' => 2,
            'tissue' => 2,
            'mycelia' => 2,
            'tunnels' => 2,
        ];

        switch ($buildingTypes[$buildingType]) {
            case 0:
                return '<abbr title="Not yet implemented" class="label label-danger">NYI</abbr>';
                break;

            case 1:
                return '<abbr title="Partially implemented" class="label label-warning">PI</abbr>';
                break;

//            case 2:
//                break;
        }

        return null;
    }

    public function getBuildingHelpString(string $buildingType): ?string
    {
        $helpStrings = [
            'home' => 'Houses 30 people.',
            'alchemy' => 'Produces 45 platinum per tick.',
            'farm' => 'Produces 80 bushels of food per tick.<br><br>Each person eats 0.25 of a bushel of food per tick.',
            'smithy' => 'Reduces military unit training platinum and ore costs.<br><br>Training cost reduced by 2% per 1% owned, up to a maximum of 40% at 20% owned. Does not affect Gnome or Imperial Gnome ore costs.',
            'masonry' => 'Increases castle bonuses and reduces damage done to castle.<br><br>Bonuses increased by 2.75% per 1% owned.<br>Damage reduced by 0.75% per 1% owned.',
            'ore_mine' => 'Produces 60 ore per tick.',
            'gryphon_nest' => 'Increases offensive power.<br><br>Power increased by +2% per 1% owned, up to a maximum of +40% at 20% owned.',
            'tower' => 'Produces 25 mana per tick.',
            'wizard_guild' => 'Increases Wizard Strength refresh rate, reduces Wizard and ArchMages training cost and reduces spell costs.<br><br>Wizard Strength refresh rate increased by 0.1% per 1% owned, up to a maximum of 2% at 20% owned.<br>Wizard and ArchMage training and spell costs reduced by 2% per 1% owned, up to a maximum of 40% at 20% owned.',
            'temple' => 'Increases population growth and reduces defensive bonuses of dominions you invade.<br><br>Population growth increased by 6% per 1% owned.<br>Defensive bonuses reduced by 2% per 1% owned, up to a maximum of 40% at 20% owned.',
            'diamond_mine' => 'Produces 15 gems per tick.',
            #'school' => 'Produces 1 experience point per tick.',
            'lumberyard' => 'Produces 50 lumber per tick.',
            'forest_haven' => 'Increases peasant defense, reduces losses on failed spy ops, reduces incoming Fireball damage and reduces platinum theft.<br><br>Each Forest Haven gives 20 peasants 0.75 defense each.<br>Failed spy ops losses reduced by 3% per 1% owned, up to a maximum of 30% at 10% owned.<br>Fireball damage and platinum theft reduced by 8% per 1% owned.',
            'factory' => 'Reduces construction and land rezoning costs.<br><br>Construction costs reduced by 4% per 1% owned, up to a maximum of 75% at 18.75% owned.<br>Rezoning costs reduced by 3% per 1% owned, up to a maximum of 75% at 25% owned.',
            'guard_tower' => 'Increases defensive power.<br><br>Power increased by +2% per 1% owned, up to a maximum of +40% at 20% owned.',
            'shrine' => 'Reduces offensive casualties reduced by 5% per 1% owned, up to a maximum of -75% at 15% owned.<br><br>Reduces defensive casualties by 1% per 1% owned, up to a maximum of 15%.', // todo: hero level gain and hero bonuses
            'barracks' => 'Houses 36 trained or training military units.<br><br>Does not increase in capacity for population bonuses.',
            'dock' => 'Produces 1 boat every 20 ticks on average, produces 35 bushels of food per tick and each dock prevents 2.5 of your boats from being sunk.',
            'ziggurat' => 'Produces 60 mana/tick.',
            'tissue' => 'Houses 160 cells, amoeba, or units. Produces 4 food/tick.',
            'mycelia' => 'House 10 people or units. Produces 4 food/tick.',
            'tunnels' => 'Dig, dig, dig.'
        ];

        return $helpStrings[$buildingType] ?: null;
    }

    public function getBuildingDescription(Building $building): ?string
    {
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
            'rezone_cost' => 'Construction costs decreased by %2$s%% for every %1$s%% (max %3$s%% reduction).',

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

        $perks = [];
        foreach ($building->perks as $perk)
        {
            if (!array_key_exists($perk->key, $perkTypeStrings))
            {
                //\Debugbar::warning("Missing perk help text for unit perk '{$perk->key}'' on unit '{$unit->name}''.");
                continue;
            }

            $perkValue = $perk->pivot->value;

            // Handle array-based perks
            $nestedArrays = false;
            // todo: refactor all of this
            // partially copied from Race::getUnitPerkValueForUnitSlot
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

            $buildingPerkString = '<ul>';
            if (is_array($perkValue))
            {
                if ($nestedArrays)
                {
                    foreach ($perkValue as $nestedKey => $nestedValue)
                    {
                        #$helpStrings[$unitType] .= ('<li>' . vsprintf($perkTypeStrings[$perk->key], $nestedValue) . '</li>');
                        $buildingPerkString .= ('<li>' . vsprintf($perkTypeStrings[$perk->key], $nestedValue) . '</li>');
                    }
                }
                else
                {
                    #$helpStrings[$unitType] .= ('<li>' . vsprintf($perkTypeStrings[$perk->key], $perkValue) . '</li>');
                    $buildingPerkString .= ('<li>' . vsprintf($perkTypeStrings[$perk->key], $perkValue) . '</li>');
                }
            }
            else
            {
                #$helpStrings[$unitType] .= ('<li>' . sprintf($perkTypeStrings[$perk->key], $perkValue) . '</li>');
                $buildingPerkString .= ('<li>' . sprintf($perkTypeStrings[$perk->key], $perkValue) . '</li>');
            }
        }

        $buildingPerkString .= '</ul>';

        return $buildingPerkString ?: null;
    }

    public function getBuildings(?Race $race)
    {
      # ???

    }

}
