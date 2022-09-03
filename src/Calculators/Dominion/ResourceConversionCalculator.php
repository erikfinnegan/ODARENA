<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Helpers\ConversionHelper;
use OpenDominion\Helpers\UnitHelper;

use OpenDominion\Models\Dominion;

use OpenDominion\Calculators\Dominion\CasualtiesCalculator;
use OpenDominion\Calculators\Dominion\ConversionCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\PopulationCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;

class ResourceConversionCalculator
{

    public function __construct()
    {
        $this->casualtiesCalculator = app(CasualtiesCalculator::class);
        $this->conversionCalculator = app(ConversionCalculator::class);
        $this->landCalculator = app(LandCalculator::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->populationCalculator = app(PopulationCalculator::class);
        $this->rangeCalculator = app(RangeCalculator::class);
        $this->resourceCalculator = app(ResourceCalculator::class);
        $this->spellCalculator = app(SpellCalculator::class);

        $this->conversionHelper = app(ConversionHelper::class);
        $this->unitHelper = app(UnitHelper::class);
    }

    public function getResourceConversions(Dominion $converter, Dominion $enemy, array $invasion, string $mode = 'offense'): array
    {
        $resourceConversionPerks = [
            'kills_into_resource_per_casualty',
            'kills_into_resource_per_casualty_on_success',
            'kills_into_resources_per_casualty',
            'kills_into_resource_per_value',
            'kills_into_resources_per_value',
        ];

        $convertingUnits = array_fill(1, 4, 0);

        foreach($converter->race->resources as $resourceKey)
        {
            $resourceConversions[$resourceKey] = 0;
        }

        # Check if any converting units were sent and survived
        foreach($converter->race->units as $unit)
        {
            if($this->unitHelper->checkUnitHasPerks($converter, $unit, $resourceConversionPerks) and isset($invasion['attacker']['surviving_units'][$unit->slot]))
            {
                $convertingUnits[$unit->slot] = 1;
            }
        }

        if(!array_sum($convertingUnits) > 0)
        {
            return $resourceConversions;
        }

        // kills_into_resource_per_casualty
        $killsIntoResourcePerCasualtyConversions = $this->getKillsIntoResourcePerCasualty($converter, $enemy, $invasion, $mode);
    }

    /**
     * Handles kills_into_resource_per_casualty and kills_into_resource_per_casualty_on_success
     *
     * The converting units convert each casualty to a resource:
     * each qualifying casualty is converted into the resource.
     *
     */
    public function getKillsIntoResourcePerCasualty(Dominion $converter, Dominion $enemy, array $invasion, string $mode = 'offense'): array
    {
        if($mode == 'offense')
        {
            $converterUnits = $invasion['attacker']['surviving_units'];
            $enemyUnitsKilled = $invasion['defender']['units_lost'];
            $enemyUnits = $invasion['defender']['surviving_units'];
        }
        else
        {
            $converterUnits = $invasion['attacker']['surviving_units'];
            $enemyUnitsKilled = $invasion['defender']['units_lost'];
            $enemyUnits = $invasion['defender']['surviving_units'];
        }

        $resourceConversions = $this->getResourceKeysArray($converter);

        $convertingUnits = array_fill(1, $converter->race->units->count(), 0);

        foreach($converterUnits as $converterUnitSlot => $converterUnitAmount)
        {
            $unit = $this->unitHelper->getRaceUnitFromSlot($converter->race, $converterUnitSlot);
            if($this->unitHelper->checkUnitHasPerks($converter, $unit, ['kills_into_resource_per_casualty']) or ($invasion['result']['success']) and $this->unitHelper->checkUnitHasPerks($converter, $unit, ['kills_into_resource_per_casualty_on_success']))
            {
                if($mode == 'offense')
                {
                    $unitsOp = $this->militaryCalculator->getOffensivePowerRaw($converter, $enemy, null, [$converterUnitSlot => $converterUnitAmount]);
                    $convertingUnits[$converterUnitSlot] = [
                        'amount' => $converterUnitAmount,
                        'power' => $unitsOp,
                        'power_proportion' => $unitsOp / $invasion['attacker']['op_raw']
                        ];
                }
                else
                {
                    $unitsDp = $this->militaryCalculator->getDefensivePowerRaw($converter, $enemy, null, [$converterUnitSlot => $converterUnitAmount]);
                    $convertingUnits[$converterUnitSlot] =  [
                        'amount' => $converterUnitAmount,
                        'power' => $unitsDp,
                        'power_proportion' => $unitsDp / $invasion['defender']['dp_raw']
                        ];
                }

                $resourceConversionPerk = $converter->race->getUnitPerkValueForUnitSlot($converterUnitSlot, 'kills_into_resource_per_casualty');
                is_array($resourceConversionPerk) ?: $resourceConversionPerk = $converter->race->getUnitPerkValueForUnitSlot($converterUnitSlot, 'kills_into_resource_per_casualty_on_success');
                
                $resourceAmount = $resourceConversionPerk[0];
                $resourceKey = $resourceConversionPerk[1];

                foreach($enemyUnitsKilled as $enemyUnitSlot => $enemyUnitAmount)
                {
                    if($this->conversionHelper->isUnitSlotConvertible($enemyUnitSlot, $enemy))
                    {
                        $resourceConversions[$resourceKey] += $enemyUnitAmount * $resourceAmount * $convertingUnits[$converterUnitSlot]['power_proportion'];
                        $resourceConversions[$resourceKey] *= $this->conversionCalculator->getConversionReductionMultiplier($enemy);
                    }
                }
            }
        }

        $resourceConversions = $resourceConversions('intval', $resourceConversions);

        dd($convertingUnits, $invasion, $resourceConversions);

    }
    

    protected function getResourceKeysArray(Dominion $dominion): array
    {
        $resourceConversions = [];

        foreach($dominion->race->resources as $resourceKey)
        {
            $resourceConversions[$resourceKey] = 0;
        }

        return $resourceConversions;
    }

}
