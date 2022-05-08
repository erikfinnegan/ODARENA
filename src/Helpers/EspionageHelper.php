<?php

namespace OpenDominion\Helpers;

use Illuminate\Support\Collection;
use OpenDominion\Models\Race;
use OpenDominion\Models\Spyop;

class EspionageHelper
{

    public function getSpyopScope(Spyop $spyop)
    {
        $scopes = [
            'self'      => 'Self',
            'friendly'  => 'Friendly',
            'hostile'   => 'Hostile'
        ];

        return $scopes[$$spyop->scope];
    }

    public function getSpyopEffectsString(Spyop $spyop): array
    {

        $effectStrings = [];

        $spyopEffects = [
            'kill_draftees' => 'Assassinate draftees (base %s per spy unit).',
            'kill_peasants' => 'Assassinate peasants (base %s per spy unit).',
            'reduce_wizard_strength' => 'Reduce wizard strength (base damage %s).',

            'sabotage_building' => 'Sabotage %1$s buildings (base damage %2$s per spy unit).',
            'sabotage_construction' => 'Sabotage buildings under construction (base %1$s per spy unit).',
            'sabotage_improvement' => 'Sabotage %1$s improvements (base %2$s%%).',

            'decrease_morale' => 'Reduces target\'s morale (base %1$s%%).',
        ];

        foreach ($spyop->perks as $perk)
        {
            if (!array_key_exists($perk->key, $spyopEffects))
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

            // Special case for conversions
            if ($perk->key === 'conversion' or $perk->key === 'displaced_peasants_conversion' or $perk->key === 'casualties_conversion')
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
            if($perk->key === 'staggered_conversion')
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
            if($perk->key === 'strength_conversion')
            {
                $limit = (float)$perkValue[0];
                $under = (int)$perkValue[1];
                $over = (int)$perkValue[2];

                $underLimitUnit = $race->units->filter(static function ($unit) use ($under)
                    {
                        return ($unit->slot === $under);
                    })->first();

                $overLimitUnit = $race->units->filter(static function ($unit) use ($over)
                    {
                        return ($unit->slot === $over);
                    })->first();

                $perkValue = [$limit, str_plural($underLimitUnit->name), str_plural($overLimitUnit->name)];
            }
            if($perk->key === 'passive_conversion')
            {
                $slotFrom = (int)$perkValue[0];
                $slotTo = (int)$perkValue[1];
                $rate = (float)$perkValue[2];
                $building = (string)$perkValue[3];

                $unitFrom = $race->units->filter(static function ($unit) use ($slotFrom)
                    {
                        return ($unit->slot === $slotFrom);
                    })->first();

                $unitTo = $race->units->filter(static function ($unit) use ($slotTo)
                    {
                        return ($unit->slot === $slotTo);
                    })->first();

                $perkValue = [$unitFrom->name, $unitTo->name, $rate, $building];
            }
            if($perk->key === 'value_conversion')
            {
                $multiplier = (float)$perkValue[0];
                $convertToSlot = (int)$perkValue[1];

                $unitToConvertTo = $race->units->filter(static function ($unit) use ($convertToSlot)
                    {
                        return ($unit->slot === $convertToSlot);
                    })->first();

                $perkValue = [$multiplier, str_plural($unitToConvertTo->name)];
            }

            if($perk->key === 'plunders')
            {
                foreach ($perkValue as $index => $plunder) {
                    [$resource, $amount] = $plunder;

                    $perkValue[$index][1] = generate_sentence_from_array([$amount]);
                }
            }

            // Special case for dies_into, wins_into ("change_into"), fends_off_into
            if ($perk->key === 'dies_into' or $perk->key === 'wins_into' or $perk->key === 'fends_off_into')
            {
                $unitSlotsToConvertTo = array_map('intval', str_split($perkValue));
                $unitNamesToConvertTo = [];

                foreach ($unitSlotsToConvertTo as $slot) {
                    $unitToConvertTo = $race->units->filter(static function ($unit) use ($slot) {
                        return ($unit->slot === $slot);
                    })->first();

                    $unitNamesToConvertTo[] = $unitToConvertTo->name;
                }

                $perkValue = generate_sentence_from_array($unitNamesToConvertTo);
            }

            // Special case for returns faster if pairings
            if ($perk->key === 'dies_into_multiple')
            {
                $slot = (int)$perkValue[0];
                $pairedUnit = $race->units->filter(static function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $amount = (int)$perkValue[1];

                $perkValue[0] = $pairedUnit->name;
                if (isset($perkValue[1]) && $perkValue[1] > 0)
                {
                    $perkValue[0] = str_plural($perkValue[0]);
                }
                else
                {
                    $perkValue[1] = 1;
                }
            }

            // Special case for unit_production
            if ($perk->key === 'unit_production')
            {
                $unitSlotToProduce = intval($perkValue[0]);

                $unitToProduce = $race->units->filter(static function ($unit) use ($unitSlotToProduce) {
                    return ($unit->slot === $unitSlotToProduce);
                })->first();

                $unitNameToProduce[] = str_plural($unitToProduce->name);

                $perkValue = generate_sentence_from_array($unitNameToProduce);
            }

            if (is_array($perkValue))
            {
                if ($nestedArrays)
                {
                    foreach ($perkValue as $nestedKey => $nestedValue)
                    {
                        foreach($nestedValue as $key => $value)
                        {
                            $nestedValue[$key] = ucwords(str_replace('level','level ',str_replace('_', ' ',$value)));
                        }
                        $effectStrings[] = vsprintf($spyopEffects[$perk->key], $nestedValue);
                    }
                }
                else
                {
                    #var_dump($perkValue);
                    foreach($perkValue as $key => $value)
                    {
                        $perkValue[$key] = ucwords(str_replace('_', ' ',$value));
                    }
                    $effectStrings[] = vsprintf($spyopEffects[$perk->key], $perkValue);
                }
            }
            else
            {
                $perkValue = str_replace('_', ' ',ucwords($perkValue));
                $effectStrings[] = sprintf($spyopEffects[$perk->key], $perkValue);
            }
        }

        return $effectStrings;
    }

    public function getExclusivityString(Spyop $spyop): string
    {

        $exclusivityString = '<br><small class="text-muted"><em>';

        if($exclusives = count($spyop->exclusive_races))
        {
            foreach($spyop->exclusive_races as $raceName)
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
        elseif($excludes = count($spyop->excluded_races))
        {
            $exclusivityString .= 'All except ';
            foreach($spyop->excluded_races as $raceName)
            {
                $exclusivityString .= $raceName;
                if($excludes > 1)
                {
                    $exclusivityString .= ', ';
                }
                $excludes--;
            }
        }
        else
        {
            $exclusivityString .= 'All factions';
        }

        $exclusivityString .= '</em></small>';

        return $exclusivityString;

    }


}
