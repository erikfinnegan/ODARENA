<?php

namespace OpenDominion\Helpers;

class ImprovementHelper
{

    public function getImprovementTypes(string $race): array
    {

      if($race == 'Growth')
      {
        $improvementTypes[] = 'tissue';
      }
      else
      {
        $improvementTypes = array(
            #'science',
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
          );
      }

      // For rules in ImproveActionRequest (???)
      if($race == 'any_race')
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
            'cartography' => '+%1$s%% land discovered during invasions, -%1$s%% cost of exploring (max -50% total reduction)',
            'hideouts' => '+%1$s%% spy power, -%1$s%% spy losses',
            'forestry' => '+%s%% lumber production',
            'refinery' => '+%s%% ore production',
            'granaries' => '-%s%% lumber and food rot',
            'tissue' => '+%s%% housing and food production',
        ];

        return $ratingStrings[$improvementType] ?: null;
    }

    public function getImprovementHelpString(string $improvementType): string
    {
        $helpStrings = [
            #'science' => 'Improvements to science increase your platinum production.<br><br>Max +20% platinum production.',
            'markets' => 'Markets increase your platinum production.<br><br>Max +20%.',
            'keep' => 'Keep increases population housing of barren land and all buildings except for Barracks.<br><br>Max +15%.',
            'towers' => 'Towers increase your wizard power, mana production, and reduce damage from offensive spells.<br><br>Max +40%.',
            'forges' => 'Forges increase your offensive power.<br><br>Max +20%.',
            'walls' => 'Walls increase your defensive power.<br><br>Max +20%.',
            'harbor' => 'Harbor increases your food and boat production and protects boats from sinking.<br><br>Max +40%.',
            'armory' => 'Armory decreases your unit platinum and ore training costs.<br><br>Max 20%.',
            'infirmary' => 'Infirmary reduces casualties suffered in battle (offensive and defensive).<br><br>Max 20%.',
            'workshops' => 'Workshop reduces construction and rezoning costs.<br><br>Max 20%.',
            'observatory' => 'Observatory increases experience points gained through invasions, exploration, and production.<br><br>Max 20%.',
            'cartography' => 'Cartography increases land discovered on attacks and reduces platinum cost of exploring.<br><br>Max 30%.',
            'hideouts' => 'Hidehouts increase your spy power and reduces spy losses.<br><br>Max 40%.',
            'forestry' => 'Forestry increases your lumber production.<br><br>Max 20%.',
            'refinery' => 'Refinery increases your ore production.<br><br>Max 20%.',
            'granaries' => 'Granaries reduce food and lumber rot.<br><br>Max 80%.',
            'tissue' => 'Feed the tissue to grow and feed more cells.<br><br>Max 20%',
        ];

        return $helpStrings[$improvementType] ?: null;
    }

    // temp
    public function getImprovementImplementedString(string $improvementType): ?string
    {
        if ($improvementType === 'towers') {
            return '<abbr title="Partially implemented" class="label label-warning">PI</abbr>';
        }

        return null;
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
            'cartography' => 'scroll-unfurled',
            'hideouts' => 'hood',
            'forestry' => 'pine-tree',
            'refinery' => 'large-hammer',
            'granaries' => 'vase',
            'tissue' => 'thorny-vine',
        ];

        return $icons[$improvement];
    }

}
