<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Helpers\ConversionHelper;
use OpenDominion\Helpers\UnitHelper;

use OpenDominion\Models\Dominion;

use OpenDominion\Calculators\Dominion\CasualtiesCalculator;
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
            if($this->unitHelper->unitHasPerk($converter, $unit, $resourceConversionPerks) and isset($invasion['attacker']['surviving_units'][$unit->slot]))
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
     * Handles kills_into_resource_per_casualty
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
            $enemyUnits = $invasion['defender']['surviving_units']
        }
        else
        {
            $converterUnits = $invasion['attacker']['surviving_units'];
            $enemyUnits = $invasion['defender']['surviving_units']
        }
    }
    

}
