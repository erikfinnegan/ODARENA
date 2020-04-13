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

            'defense_from_pairing' => 'Defense increased by %2$s when paired with %3$s %1$s at home.',
            'offense_from_pairing' => 'Offense increased by %2$s when paired with %3$s %1$s on attack.',

            'defense_from_prestige' => 'Defense increased by 1 for every %1$s prestige (max +%2$s).',
            'offense_from_prestige' => 'Offense increased by 1 for every %1$s prestige (max +%2$s).',

            'defense_vs_prestige' => 'Defense increased by 1 against every %1$s prestige of attacker (max +%2$s).',
            'offense_vs_prestige' => 'Offense increased by 1 against every %1$s prestige of target (max +%2$s).',

            'defense_vs_building' => 'Defense increased by 1 against every %2$s%% %1$ss of attacker (max +%3$s).',
            'offense_vs_building' => 'Offense increased by 1 against every %2$s%% %1$ss of target (max +%3$s).',

            'defense_vs_goblin' => 'Defense increased by %s against goblins.',
            'offense_vs_goblin' => 'Offense increased by %s against goblins.',
            'defense_vs_kobold' => 'Defense increased by %s against kobolds.',
            'offense_vs_kobold' => 'Offense increased by %s against kobolds.',
            'defense_vs_wood_elf' => 'Defense increased by %s against wood elves.',
            'offense_vs_wood_elf' => 'Offense increased by %s against wood elves.',

            'offense_staggered_land_range' => 'Offense increased by %2$s against dominions %1$s%%+ of your size.',

            'offense_raw_wizard_ratio' => 'Offense increased by %1$s * Raw Wizard Ratio (max +%2$s).',
            'offense_wizard_ratio' => 'Offense increased by %1$s * Wizard Ratio (max +%2$s).',

            'offense_raw_spy_ratio' => 'Offense increased by %1$s * Raw Spy Ratio (max +%2$s).',
            'offense_spy_ratio' => 'Offense increased by %1$s * Spy Ratio (max +%2$s).',

            // Spy related
            'counts_as_spy_defense' => 'Each unit counts as %s of a spy on defense.',
            'counts_as_spy_offense' => 'Each unit counts as %s of a spy on offense.',

            // Wizard related
            'counts_as_wizard_defense' => 'Each unit counts as %s of a wizard on defense.',
            'counts_as_wizard_offense' => 'Each unit counts as %s of a wizard on offense.',

            // Casualties related
            'fewer_casualties' => '%s%% fewer casualties.',
            'fewer_casualties_defense' => '%s%% fewer casualties on defense.',
            'fewer_casualties_offense' => '%s%% fewer casualties on offense.',
            'fixed_casualties' => 'ALWAYS suffers %s%% casualties.',

            'immortal' => 'Almost never dies.',
            'immortal_except_vs' => 'Almost never dies, except vs %s.',
            'immortal_vs_land_range' => 'Almost never dies when attacking dominions %s%%+ of your size.',

            'reduce_combat_losses' => 'Reduces combat losses.',

            'fewer_casualties_defense_from_land' => 'Casualties on defense reduced by 1%% for every %2$s%% %1$ss (max %3$s%% reduction).',
            'fewer_casualties_offense_from_land' => 'Casualties on offense reduced by 1% for every %2$s%% %1$ss (max %3$s%% reduction).',

            'fewer_casualties_defense_vs_land' => 'Casualties on defense reduced by 1%% against every %2$s%% %1$ss of attacker (max %3$s%% reduction).',
            'fewer_casualties_offense_vs_land' => 'Casualties on offense reduced by 1%% against every %2$s%% %1$ss of target (max %3$s%% reduction).',

            // Resource related
            'ore_production' => 'Each unit produces %s units of ore per tick.',
            'plunders_resources_on_attack' => 'Plunders resources on attack.',
            'sink_boats_defense' => 'Sinks boats when defending.',
            'sink_boats_offense' => 'Sinks boats when attacking.',

            // Misc
            'faster_return' => 'Returns %s ticks faster from battle.',

            // ODA
            'mana_production' => 'Each unit generates %s mana per tick.',
            'lumber_production' => 'Each unit collects %s lumber per tick.',
            'food_production' => 'Each unit produces %s food per tick.',
            'gem_production' => 'Each unit mines %s gems per tick.',
            'tech_production' => 'Each unit produces %s experience points per tick.',
            'decay_protection' => 'Each units protects %1$s %2$s per tick from decay.',

            'mana_drain' => 'Each unit drains %s mana per tick.',
            'platinum_upkeep' => 'Costs %s platinum per tick.',
            'food_consumption' => 'Eats %s bushels of food extra.',

            'cannot_be_trained' => 'Unit cannot be trained.',
            'instant_training' => 'Summoned immediately.',
            'faster_training' => 'Trains %s ticks faster (minimum two ticks).',

            'true_immortal' => 'Immortal. Only dies when overwhelmed.',
            'afterlife_norse' => 'Upon honourable death (successful invasions over 75%%), becomes a legendary champion and can be recalled into services as an Einherjar.',
            'does_not_kill' => 'Does not kill other units.',
            'no_draftee' => 'No draftee required to train.',

            'offense_vs_land' => 'Offense increased by 1 against every %2$s%% %1$ss of target (max +%3$s).',
            'pairing_limit' => 'You can at most have %2$s of this unit per %1$s.',
            'land_limit' => 'You can at most have 1 of this unit per %2$s acres of %1$s.',
            'amount_limit' => 'You can at most have %1$s of this unit.',

            'does_not_count_as_population' => 'Does not count towards population. No housing required.',

            'immortal_wizard' => 'Immortal wizard (cannot be killed when casting spells).',
            'immortal_spy' => 'Immortal spy (cannot be killed when conducting espionage).',

            'offense_if_recently_invaded' => 'Offense increased by %1$s if recenty invaded (in the last six hours).',
            'defense_if_recently_invaded' => 'Defense increased by %1$s if recenty invaded (in the last six hours).',

            'offense_per_hour' => 'Offense increased by %1$s for every hour of the round (max +%2$s).',
            'defense_per_hour' => 'Defense increased by %1$s for every hour of the round (max +%2$s).',

            'burns_peasants_on_attack' => 'Burns %s peasants on successful invasion.',
            'damages_improvements_on_attack' => 'Damages target\'s castle: %s improvement points, spread proportionally across all invested improvements.',

            'land_per_tick' => 'Explores %1$s acres of home land per tick.',

            'offense_vs_barren_land' => 'Offense increased by 1 against every %1$s%% barren land of target (max +%2$s). Unfinished buildings count as barren land. Does not count against Barbarians.',

            'offense_vs_resource' => 'Offense increased by 1 for every %2$s %1$s target has (max +%3$s).',
            'defense_vs_resource' => 'Defense increased by 1 for every %2$s %1$s attacker has (max +%3$s).',

            'offense_from_resource' => 'Offense increased by 1 for every %2$s %1$s (no max).',
            'defense_from_resource' => 'Defense increased by 1 for every %2$s %1$s (max +%3$s).',

            'offense_from_military_percentage' => 'Gains +1x(Military / Total Population) OP, max +1 at 100%% military.',
            'offense_from_victories' => 'Offense increased by %1$s for every victory (max +%2$s). Only attacks over 75%% count as victories.',

            'kills_peasants' => 'Eats %s peasants per tick.',
            'sacrifices_peasants' => 'Sacrifices %s peasants per tick for one soul each.',

            'only_dies_vs_raw_power' => 'Only dies against units with %s or more raw military power.',
            'sendable_with_zero_op' => 'Equippable (can be sent on invasion despite unit having 0 offensive power).',

            'dies_into' => 'Upon death, returns as %s.',# On defense, the change is instant. On offense, the new unit returns from battle with the other units.',
            'wins_into' => 'Upon victory, returns as %s.',

            'eats_peasants_on_attack' => 'Eats %s peasants on successful invasion.',
            'eats_draftees_on_attack' => 'Eats %s draftees on successful invasion.',

            'defense_mob' => 'Defense increased by +%1$s if your troops at home (including units with no defensive power) outnumber the invading units.',
            'offense_mob' => 'Offense increased by +%1$s if the troops you send outnumber the target\'s entire military at home (including units with no defensive power).',

            'minimum_wpa_to_train' => 'Must have at least %s Wizard Ratio (on offense) to train.',

            # TBD
            'unit_production' => 'Produces %2$s %1$s per tick.',
            'decreases_info_ops_accuracy' => 'Decreases accuracy of spells and espionage ops performed on the dominion. The more of this unit, the less accurate.',

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
                elseif
                ($perk->key === 'staggered_conversion')
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

                if (strtolower($proficiency) == 'specialist')
                {
                    $colorClass = 'text-green';
                }
                elseif (strtolower($proficiency) == 'elite')
                {
                    $colorClass = 'text-light-blue';
                }
            }
        }

        return "<i class=\"$iconClass $colorClass\"></i>";
    }

    public function getConvertedUnitsString(array $convertedUnits, Race $race): string
    {
        $result = 'In addition, your army converts some of the enemy casualties into ';
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
    public function getSoulCollectionString(int $souls): string
    {
      if ($souls > 0)
      {
        $result = 'By slaying enemy soldiers, you collect ' . number_format($souls) . ' souls.';
      }
      else
      {
        $result = 'You do not collect any souls.';
      }

        return $result;
    }


}
