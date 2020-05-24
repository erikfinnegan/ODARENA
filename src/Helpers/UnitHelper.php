<?php

namespace OpenDominion\Helpers;

use OpenDominion\Models\Race;

class UnitHelper
{
    public function getUnitTypes(bool $hideSpecialUnits = false): array
    {
        $data = [
            'unit1',
            'unit2',
            'unit3',
            'unit4',
        ];

        if (!$hideSpecialUnits) {
            $data = array_merge($data, [
                'spies',
                'wizards',
                'archmages',
            ]);
        }

        return $data;
    }

    public function getUnitName(string $unitType, Race $race): string
    {
        if (in_array($unitType, ['spies', 'wizards', 'archmages'], true)) {
            return ucfirst($unitType);
        }

        $unitSlot = (((int)str_replace('unit', '', $unitType)) - 1);

        return $race->units[$unitSlot]->name;
    }


    public function getUnitHelpString(string $unitType, Race $race): ?string
    {

        $helpStrings = [
            'draftees' => 'Used for exploring and training other units. Provides 1 DP.',
            'unit1' => 'Offensive specialist',
            'unit2' => 'Defensive specialist',
            'unit3' => 'Defensive elite',
            'unit4' => 'Offensive elite',
            'spies' => 'Used for espionage.',
            'wizards' => 'Used for casting offensive spells.',
            'archmages' => 'Used for casting offensive spells. Twice as strong as regular wizards.',
        ];

        $perkTypeStrings = [
            // Conversions
            'conversion' => 'Converts some enemy casualties into %s.',
            'staggered_conversion' => 'Converts some enemy casualties into %2$s against dominions %1$s%%+ of your size.',

            // OP/DP related
            'defense_from_building' => 'Defense increased by 1 for every %2$s%% %1$ss (max +%3$s).',
            'offense_from_building' => 'Offense increased by 1 for every %2$s%% %1$ss (max +%3$s).',

            'defense_from_land' => 'Defense increased by 1 for every %2$s%% %1$ss (max +%3$s).',
            'offense_from_land' => 'Offense increased by 1 for every %2$s%% %1$ss (max +%3$s).',

            'offense_vs_land' => 'Offense increased by 1 against every %2$s%% %1$ss of target (max +%3$s).',
            'defense_vs_land' => 'Defense increased by 1 for every %2$s%% %1$ss of attacker (max +%3$s).',

            'defense_from_pairing' => 'Defense increased by %2$s when paired with %3$s %1$s at home.',
            'offense_from_pairing' => 'Offense increased by %2$s when paired with %3$s %1$s on attack.',

            'defense_from_prestige' => 'Defense increased by 1 for every %1$s prestige (max +%2$s).',
            'offense_from_prestige' => 'Offense increased by 1 for every %1$s prestige (max +%2$s).',

            'defense_vs_prestige' => 'Defense increased by 1 against every %1$s prestige of attacker (max +%2$s).',
            'offense_vs_prestige' => 'Offense increased by 1 against every %1$s prestige of target (max +%2$s).',

            'defense_vs_building' => 'Defense increased by 1 against every %2$s%% %1$ss of attacker (max +%3$s).',
            'offense_vs_building' => 'Offense increased by 1 against every %2$s%% %1$ss of target (max +%3$s).',

            'offense_staggered_land_range' => 'Offense increased by %2$s against dominions %1$s%%+ of your size.',

            'offense_raw_wizard_ratio' => 'Offense increased by %1$s * Raw Wizard Ratio (max +%2$s).',
            'offense_wizard_ratio' => 'Offense increased by %1$s * Wizard Ratio (max +%2$s).',

            'offense_raw_spy_ratio' => 'Offense increased by %1$s * Raw Spy Ratio (max +%2$s).',
            'offense_spy_ratio' => 'Offense increased by %1$s * Spy Ratio (max +%2$s).',

            'offense_if_recently_invaded' => 'Offense increased by %1$s if recenty invaded (in the last six hours, includes non-overwhelmed failed invasions).',
            'defense_if_recently_invaded' => 'Defense increased by %1$s if recenty invaded (in the last six hours, includes non-overwhelmed failed invasions).',

            'offense_per_hour' => 'Offense increased by %1$s for every hour of the round (max +%2$s).',
            'defense_per_hour' => 'Defense increased by %1$s for every hour of the round (max +%2$s).',

            'offense_vs_barren_land' => 'Offense increased by 1 against every %1$s%% barren land of target (max +%2$s). Unfinished buildings count as barren land. Does not count against Barbarians.',

            'offense_vs_resource' => 'Offense increased by 1 for every %2$s %1$s target has (max +%3$s).',
            'defense_vs_resource' => 'Defense increased by 1 for every %2$s %1$s attacker has (max +%3$s).',

            'offense_from_resource' => 'Offense increased by 1 for every %2$s %1$s (no max).',
            'defense_from_resource' => 'Defense increased by 1 for every %2$s %1$s (max +%3$s).',

            'offense_from_military_percentage' => 'Gains +1x(Military / Total Population) OP, max +1 at 100%% military.',
            'offense_from_victories' => 'Offense increased by %1$s for every victory (max +%2$s). Only attacks over 75%% count as victories.',

            'defense_mob' => 'Defense increased by +%1$s if your troops at home (including units with no defensive power) outnumber the invading units.',
            'offense_mob' => 'Offense increased by +%1$s if the troops you send outnumber the target\'s entire military at home (including units with no defensive power).',

            // Spy related
            'counts_as_spy_defense' => 'Each unit counts as %s of a spy on defense.',
            'counts_as_spy_offense' => 'Each unit counts as %s of a spy on offense.',
            'immortal_spy' => 'Immortal spy (cannot be killed when conducting espionage).',
            'minimum_spa_to_train' => 'Must have at least %s Spy Ratio (on offense) to train.',

            // Wizard related
            'counts_as_wizard_defense' => 'Each unit counts as %s of a wizard on defense.',
            'counts_as_wizard_offense' => 'Each unit counts as %s of a wizard on offense.',
            'immortal_wizard' => 'Immortal wizard (cannot be killed when casting spells).',
            'minimum_wpa_to_train' => 'Must have at least %s Wizard Ratio (on offense) to train.',

            // Casualties and death related
            'fewer_casualties' => '%s%% fewer casualties.',
            'fewer_casualties_defense' => '%s%% fewer casualties on defense.',
            'fewer_casualties_offense' => '%s%% fewer casualties on offense.',
            'fixed_casualties' => 'Always suffers %s%% casualties.',

            'immortal' => 'Near immortal. Only dies when overwhelmed on attack.',
            'immortal_except_vs' => 'Near immortal, except vs % or when overwhelmed on attack..',
            'immortal_vs_land_range' => 'Near immortal when attacking dominions %s%%+ of your size, except when overwhelmed on attack.',
            'true_immortal_defense' => 'Near immortal. Only dies when overwhelmed on defense.',
            'true_immortal' => 'Immortal. Only dies when overwhelmed on attack.',

            'reduces_casualties' => 'Reduces combat losses.',
            'increases_casualties_on_offense' => 'Increases enemy casualties on offense (defender suffers more casualties).',
            'increases_casualties_on_defense' => 'Increases enemy casualties on defense (attacker suffers more casualties).',

            'fewer_casualties_defense_from_land' => 'Casualties on defense reduced by 1%% for every %2$s%% %1$ss (max %3$s%% reduction).',
            'fewer_casualties_offense_from_land' => 'Casualties on offense reduced by 1% for every %2$s%% %1$ss (max %3$s%% reduction).',

            'fewer_casualties_defense_vs_land' => 'Casualties on defense reduced by 1%% against every %2$s%% %1$ss of attacker (max %3$s%% reduction).',
            'fewer_casualties_offense_vs_land' => 'Casualties on offense reduced by 1%% against every %2$s%% %1$ss of target (max %3$s%% reduction).',

            'only_dies_vs_raw_power' => 'Only dies against units with %s or more raw military power.',

            'dies_into' => 'Upon death, returns as %s.',# On defense, the change is instant. On offense, the new unit returns from battle with the other units.',
            'wins_into' => 'Upon victory, returns as %s.',

            // Resource related
            'ore_production' => 'Each unit produces %s units of ore per tick.',
            'mana_production' => 'Each unit generates %s mana per tick.',
            'lumber_production' => 'Each unit collects %s lumber per tick.',
            'food_production' => 'Each unit produces %s food per tick.',
            'gem_production' => 'Each unit mines %s gems per tick.',
            'tech_production' => 'Each unit produces %s experience points per tick.',

            'food_consumption' => 'Eats %s bushels of food extra.',

            'decay_protection' => 'Each units protects %1$s %2$s per tick from decay.',

            'plunders' => 'Plunders up to %2$s %1$s on attack.',

            'sink_boats_defense' => 'Sinks boats when defending.',
            'sink_boats_offense' => 'Sinks boats when attacking.',

            'mana_drain' => 'Each unit drains %s mana per tick.',
            'platinum_upkeep' => 'Costs %s platinum per tick.',

            // Misc
            'faster_return' => 'Returns %s ticks faster from battle.',
            'land_per_tick' => 'Explores %1$s acres of home land per tick.',
            'sendable_with_zero_op' => 'Equippable (can be sent on invasion despite unit having 0 offensive power).',

            // Training
            'cannot_be_trained' => 'Unit cannot be trained.',
            'instant_training' => 'Summoned immediately.',
            'faster_training' => 'Trains %s ticks faster (minimum two ticks).',

            'afterlife_norse' => 'Upon honourable death (successful invasions over 75%%), becomes a legendary champion and can be recalled into services as an Einherjar.',
            'does_not_kill' => 'Does not kill other units.',
            'no_draftee' => 'No draftee required to train.',

            // Limits
            'pairing_limit' => 'You can at most have %2$s of this unit per %1$s.',
            'land_limit' => 'You can at most have 1 of this unit per %2$s acres of %1$s.',
            'building_limit' => 'You can at most have %2$s of this unit per %1$s. Increasable by %3$s improvements.',

            // Population
            'amount_limit' => 'You can at most have %1$s of this unit.',
            'does_not_count_as_population' => 'Does not count towards population. No housing required.',
            'population_growth' => 'Increases population growth by 2%% for every 1%% of population.',

            'houses_military_units' => 'Houses %1$s military units.',
            'houses_people' => 'Houses %1$s people.',

            // Other
            'increases_morale' => 'Increases base morale by %s%% for every 1%% of population.',
            'increases_prestige_gains' => 'Increases prestige gains by %s%% for every 1%% of units sent.',

            // Damage
            'burns_peasants_on_attack' => 'Burns %s peasants on successful invasion.',
            'damages_improvements_on_attack' => 'Damages target\'s castle: %s improvement points, spread proportionally across all invested improvements.',
            'eats_peasants_on_attack' => 'Eats %s peasants on successful invasion.',
            'eats_draftees_on_attack' => 'Eats %s draftees on successful invasion.',

            // Demonic
            'kills_peasants' => 'Eats %s peasants per tick.',
            'sacrifices_peasants' => 'Sacrifices %s peasants per tick for one soul, two gallons of blood, and 1/4 bushel of food per peasant.',

            // Myconid
            'unit_production' => 'Produces %2$s %1$s per tick.',
            'decreases_info_ops_accuracy' => 'Decreases accuracy of Clear Sights performed on the dominion by 0.50%% for every 1%% of total population made up of this unit.',

            # TBD
            'is_inanimate' => 'Inanimate.',
        ];

        // Get unit - same logic as military page
        if (in_array($unitType, ['unit1', 'unit2', 'unit3', 'unit4']))
        {
            $unit = $race->units->filter(function ($unit) use ($unitType) {
                return ($unit->slot == (int)str_replace('unit', '', $unitType));
            })->first();

            list($type, $proficiency) = explode(' ', $helpStrings[$unitType]);
            if ($unit->type) {
                list($type, $proficiency) = explode('_', $unit->type);
                $type = ucfirst($type);
            }   $proficiency .= '.';
            #$helpStrings[$unitType] = "$type $proficiency";

            # ODA: Show base OP and DP in unitHelperString
            $helpStrings[$unitType] .= '<li>OP: '. $unit->power_offense . ' / DP: ' . $unit->power_defense . '</li>';

            foreach ($unit->perks as $perk)
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

                // Special case for pairings
                if ($perk->key === 'defense_from_pairing' || $perk->key === 'offense_from_pairing' || $perk->key === 'pairing_limit')
                {
                    $slot = (int)$perkValue[0];
                    $pairedUnit = $race->units->filter(static function ($unit) use ($slot) {
                        return ($unit->slot === $slot);
                    })->first();

                    $perkValue[0] = $pairedUnit->name;
                    if (isset($perkValue[2]) && $perkValue[2] > 0)
                    {
                        $perkValue[0] = str_plural($perkValue[0]);
                    }
                    else
                    {
                        $perkValue[2] = 1;
                    }
                }

                // Special case for conversions
                if ($perk->key === 'conversion')
                {
                    $unitSlotsToConvertTo = array_map('intval', str_split($perkValue));
                    $unitNamesToConvertTo = [];

                    foreach ($unitSlotsToConvertTo as $slot) {
                        $unitToConvertTo = $race->units->filter(static function ($unit) use ($slot) {
                            return ($unit->slot === $slot);
                        })->first();

                        $unitNamesToConvertTo[] = str_plural($unitToConvertTo->name);
                    }

                    $perkValue = generate_sentence_from_array($unitNamesToConvertTo);
                }
                elseif($perk->key === 'staggered_conversion')
                {
                    foreach ($perkValue as $index => $conversion) {
                        [$convertAboveLandRatio, $slots] = $conversion;

                        $unitSlotsToConvertTo = array_map('intval', str_split($slots));
                        $unitNamesToConvertTo = [];

                        foreach ($unitSlotsToConvertTo as $slot) {
                            $unitToConvertTo = $race->units->filter(static function ($unit) use ($slot) {
                                return ($unit->slot === $slot);
                            })->first();

                            $unitNamesToConvertTo[] = str_plural($unitToConvertTo->name);
                        }

                        $perkValue[$index][1] = generate_sentence_from_array($unitNamesToConvertTo);
                    }
                }


                if($perk->key === 'plunders')
                {
                    foreach ($perkValue as $index => $plunder) {
                        [$resource, $amount] = $plunder;

                        $perkValue[$index][1] = generate_sentence_from_array([$amount]);
                    }
                }

                // Special case for dies_into and wins_into ("change_into")
                if ($perk->key === 'dies_into' or $perk->key === 'wins_into')
                {
                    $unitSlotsToConvertTo = array_map('intval', str_split($perkValue));
                    $unitNamesToConvertTo = [];

                    foreach ($unitSlotsToConvertTo as $slot) {
                        $unitToConvertTo = $race->units->filter(static function ($unit) use ($slot) {
                            return ($unit->slot === $slot);
                        })->first();

                        $unitNamesToConvertTo[] = str_plural($unitToConvertTo->name);
                    }

                    $perkValue = generate_sentence_from_array($unitNamesToConvertTo);
                }

                // Special case for dies_into and wins_into ("change_into")
                if ($perk->key === 'unit_productionX')
                {
                    $unitSlotToGenerate = intval($perkValue[0]);
                    $unitsGeneratedPerUnit = $perkValue[1];

                    $unitToProduce = $race->units->filter(static function ($unit) use ($unitSlotToGenerate)
                        {
                            return ($unit->slot === $unitSlotToGenerate);
                        })->first();


                    $unitNamesToProduce[] = $unitsGeneratedPerUnit;
                                        $unitNamesToProduce[] = str_plural($unitToProduce->name);

                    $perkValue = generate_sentence_from_array($unitNamesToProduce);
                }


                if (is_array($perkValue)) {
                    if ($nestedArrays) {
                        foreach ($perkValue as $nestedKey => $nestedValue) {
                            $helpStrings[$unitType] .= ('<li>' . vsprintf($perkTypeStrings[$perk->key], $nestedValue) . '</li>');
                        }
                    } else {
                        $helpStrings[$unitType] .= ('<li>' . vsprintf($perkTypeStrings[$perk->key], $perkValue) . '</li>');
                    }
                } else {
                    #if($unitType == 'unit3')
                    #{
                    #  dd($perkValue);
                    #}
                    $helpStrings[$unitType] .= ('<li>' . sprintf($perkTypeStrings[$perk->key], $perkValue) . '</li>');
                }
            }

            if ($unit->need_boat === false) {
                $helpStrings[$unitType] .= ('<li>No boats needed.</li>');
            }

        }


        if(strlen($helpStrings[$unitType]) == 0)
        {
          $helpStrings[$unitType] = '<i>No special abilities</i>';
        }
        else
        {
          $helpStrings[$unitType] = '<ul>' . $helpStrings[$unitType] . '</ul>';
        }

        return $helpStrings[$unitType] ?: null;
    }

    public function getUnitTypeIconHtml(string $unitType, Race $race = null): string
    {
        switch ($unitType)
        {
            case 'draftees':
                $iconClass = 'fa fa-user';
                $colorClass = 'text-green';
                break;

            case 'unit1':
                $iconClass = 'ra ra-sword';
                $colorClass = 'text-green';
                break;

            case 'unit2':
                $iconClass = 'ra ra-shield';
                $colorClass = 'text-green';
                break;

            case 'unit3':
                $iconClass = 'ra ra-shield';
                $colorClass = 'text-light-blue';
                break;

            case 'unit4':
                $iconClass = 'ra ra-sword';
                $colorClass = 'text-light-blue';
                break;

            case 'spies':
                $iconClass = 'fa fa-user-secret';
                $colorClass = 'text-green';
                break;

            case 'wizards':
                $iconClass = 'ra ra-fairy-wand';
                $colorClass = 'text-green';
                break;

            case 'archmages':
                $iconClass = 'ra ra-fairy-wand';
                $colorClass = 'text-light-blue';
                break;

            default:
                return '';
        }

        if ($race && in_array($unitType, ['unit1', 'unit2', 'unit3', 'unit4']))
        {
            $unit = $race->units->filter(function ($unit) use ($unitType) {
                return ($unit->slot == (int)str_replace('unit', '', $unitType));
            })->first();
            if ($unit->type) {
                list($type, $proficiency) = explode('_', $unit->type);

                if (strtolower($type) == 'offensive')
                {
                    $iconClass = 'ra ra-sword';
                }
                elseif (strtolower($type) == 'defensive')
                {
                    $iconClass = 'ra ra-shield';
                }
                elseif (strtolower($type) == 'hybrid')
                {
                    $iconClass = 'ra ra-crossed-swords';
                }
                elseif (strtolower($type) == 'machinery')
                {
                    $iconClass = 'ra ra-cog';
                }
                elseif (strtolower($type) == 'equipment')
                {
                    $iconClass = 'ra ra-vest';
                }
                elseif (strtolower($type) == 'ammunition')
                {
                    $iconClass = 'ra ra-spikeball';
                }

                elseif (strtolower($type) == 'monsterclaw')
                {
                    $iconClass = 'ra ra-flaming-claw';
                }
                elseif (strtolower($type) == 'monsterhead')
                {
                    $iconClass = 'ra ra-monster-skull';
                }
                elseif (strtolower($type) == 'monsterpart')
                {
                    $iconClass = 'ra ra-meat';
                }


                if (strtolower($proficiency) == 'specialist')
                {
                    $colorClass = 'text-green';
                }
                elseif (strtolower($proficiency) == 'elite')
                {
                    $colorClass = 'text-light-blue';
                }
                elseif (strtolower($proficiency) == 'super')
                {
                    $colorClass = 'text-orange';
                }
            }
        }

        return "<i class=\"$iconClass $colorClass\"></i>";
    }

    public function getConvertedUnitsString(array $convertedUnits, Race $race, string $type): string
    {
        if($type == 'offensive')
        {
            $result = 'In addition, your army converts some of the killed enemy soldiers into ';
        }
        elseif($type == 'defensive')
        {
            $result = 'However, your army converts some of the killed invading soldiers into ';
        }

        $convertedUnitsFiltered = array_filter($convertedUnits, function ($item) {
            return $item > 0;
        });

        $numberOfUnitTypesConverted = count($convertedUnitsFiltered);
        $i = 1;

        // todo: this can probably be refactored to use generate_sentence_from_array() in helpers.php
        foreach ($convertedUnitsFiltered as $slotNumber => $amount) {
            if ($i !== 1) {
                if ($numberOfUnitTypesConverted === $i) {
                    $result .= ' and ';
                } else {
                    $result .= ', ';
                }
            }

            $formattedAmount = number_format($amount);

            $result .= "{$formattedAmount} {$race->units[$slotNumber - 1]->name}s";

            $i++;
        }

        $result .= '!';

        return $result;
    }

    # Norse champions
    public function getChampionsString(int $champions): string
    {
      if ($champions > 0)
      {
        $result = number_format($champions) . ' of your brave fallen soldiers have become legendary champions.';
      }
      else
      {
        $result = 'No legendary champions arose from this battle.';
      }

        return $result;
    }

    # Demon Soul collection
    public function getDemonicCollectionString(array $demonicCollection): string
    {

      if($demonicCollection['souls'] > 0 or $demonicCollection['blood'] > 0 or $demonicCollection['food'] > 0)
      {
          $result = 'Ripping and tearing the dead apart, the Demonic units collect ' . number_format($demonicCollection['souls']) . ' souls and ' . number_format($demonicCollection['blood']) . ' gallons of blood, and use the carcasses to create ' . number_format($demonicCollection['food']) . ' bushels of food.';
      }

        return $result;
    }


    public function getPlunderString(array $plunder): string
    {
        $plunder = array_filter($plunder);
        $count = count($plunder);
        $string = '';

        $i = 0;
        foreach($plunder as $resource => $amount)
        {
            $i++;
            $remainingResources = $count - $i;
            $string .= number_format($amount) . ' ' . $resource;

            if($remainingResources > 1)
            {
                $string .= ', ';
            }
            elseif($remainingResources == 1)
            {
                $string .= ' and ';
            }
        }

        $string .= ' were plundered.';

        return $string;
    }

    public function getSalvageString(array $salvage): string
    {
        $salvage = array_filter($salvage);
        $count = count($salvage);
        $string = 'You salvage ';

        $i = 0;
        foreach($salvage as $resource => $amount)
        {
            $i++;
            $remainingResources = $count - $i;
            $string .= number_format($amount) . ' ' . $resource;

            if($remainingResources > 1)
            {
                $string .= ', ';
            }
            elseif($remainingResources == 1)
            {
                $string .= ' and ';
            }
        }

        $string .= '.';

        return $string;
    }

}
