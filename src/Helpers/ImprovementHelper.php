<?php

namespace OpenDominion\Helpers;

# ODA
use OpenDominion\Models\Dominion;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;

class ImprovementHelper
{

    /** @var ImprovementCalculator */
    protected $improvementCalculator;

    public function __construct(
        ImprovementCalculator $improvementCalculator)
    {
        $this->improvementCalculator = $improvementCalculator;
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
          'towers',
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
            #'science' => '+%s%% platinum production',
            'markets' => '+%s%% platinum production',
            'keep' => '+%s%% max population',
            'towers' => '+%1$s%% wizard power, +%1$s%% mana production, -%1$s%% damage from spells',
            'forges' => '+%s%% offensive power',
            'walls' => '+%s%% defensive power',
            'harbor' => '+%s%% food production, boat production & protection',
            'armory' => '-%s%% military training platinum and ore costs',
            'infirmary' => '-%s%% casualties in battle',
            'workshops' => '-%s%% construction and rezoning costs',
            'observatory' => '+%1$s%% experience points gained through invasions, exploration, and production',
            'cartography' => '+%1$s%% land discovered during invasions, -%1$s%% cost of exploring (max -50%% total reduction)',
            'hideouts' => '+%1$s%% spy power, -%1$s%% spy losses',
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
            #'science' => 'Improvements to science increase your platinum production.<br><br>Max +20% platinum production.',
            'markets' => 'Markets increase your platinum production.<br><br>Max +',
            'keep' => 'Keep increases population housing of barren land and all buildings except for Barracks.<br><br>Max +',
            'towers' => 'Towers increase your wizard power, mana production, and reduce damage from offensive spells.<br><br>Max +',
            'forges' => 'Forges increase your offensive power.<br><br>Max +',
            'walls' => 'Walls increase your defensive power.<br><br>Max +',
            'harbor' => 'Harbor increases your food and boat production and protects boats from sinking.<br><br>Max +',
            'armory' => 'Armory decreases your unit platinum and ore training costs.<br><br>Max ',
            'infirmary' => 'Infirmary reduces casualties suffered in battle (offensive and defensive).<br><br>Max ',
            'workshops' => 'Workshop reduces construction and rezoning costs.<br><br>Max ',
            'observatory' => 'Observatory increases experience points gained through invasions, exploration, and production.<br><br>Max ',
            'cartography' => 'Cartography increases land discovered on attacks and reduces platinum cost of exploring.<br><br>Max ',
            'hideouts' => 'Hidehouts increase your spy power and reduces spy losses.<br><br>Max ',
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
            'towers' => 'fairy-wand',
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

}
