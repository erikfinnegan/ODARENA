<?php

namespace OpenDominion\Helpers;

use LogicException;
use OpenDominion\Models\Title;
use OpenDominion\Models\TitlePerkType;

use OpenDominion\Models\Dominion;

class TitleHelper
{
    public function getPerkDescriptionHtmlWithValue(TitlePerkType $perkType): ?array
    {
        $valueType = '%';
        $booleanValue = false;
        switch($perkType->key) {
            case 'military_cost':
                $negativeBenefit = true;
                $description = 'Training costs:';
                $valueType = '% platinum, ore, lumber, food, and mana costs';
                break;
            case 'spell_cost':
                $negativeBenefit = true;
                $description = 'Spell costs:';
                break;
            case 'construction_cost':
                $negativeBenefit = true;
                $description = 'Construction costs:';
                break;
            case 'rezone_cost':
                $negativeBenefit = true;
                $description = 'Rezoning costs:';
                break;
            case 'improvements':
                $negativeBenefit = false;
                $description = 'Improvement points:';
                break;
            case 'spy_strength':
                $negativeBenefit = false;
                $description = 'Spy strength:';
                break;
            case 'explore_cost':
                $negativeBenefit = true;
                $description = 'Exploration platinum costs:';
                break;
            case 'ore_production':
                $negativeBenefit = false;
                $description = 'Ore production:';
                break;
            case 'gem_production':
                $negativeBenefit = false;
                $description = 'Gem production:';
                break;
            case 'tech_production':
                $negativeBenefit = false;
                $description = 'XP production:';
                break;
            case 'casualties':
                $negativeBenefit = true;
                $description = 'Casualties:';
                break;
            case 'conversions':
                $negativeBenefit = false;
                $description = 'Units converted (not applicable to Vampires):';
                break;
            case 'lumber_production':
                $negativeBenefit = false;
                $description = 'Lumber production:';
                break;
            case 'exchange_rate':
                $negativeBenefit = false;
                $description = 'Better exchange rates:';
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

    public function getRulerTitlePerksForDominion(Dominion $dominion): string
    {
        $rulerTitlePerks = [];

        foreach($dominion->title->perks as $perk)
        {
            $perkDescription = $this->getPerkDescriptionHtmlWithValue($perk);
            $perkDescription = $perkDescription['description'];
            $value = $dominion->title->getPerkMultiplier($perk->key);

            $rulerTitlePerks[$perkDescription] = round(($value * $dominion->title->getPerkBonus($dominion))*100,2);
        }

        $rulerTitlePerksString = '<ul>';
        foreach($rulerTitlePerks as $perk => $value)
        {
            $rulerTitlePerksString .= '<li>' . $perk . '&nbsp;' . $value . '%</li>';
        }
        $rulerTitlePerksString .= '<ul>';

        return $rulerTitlePerksString;

    }

}
