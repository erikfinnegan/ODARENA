<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\Resource;

use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Helpers\TitleHelper;

use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;

class TitleCalculator
{
    protected $militaryCalculator;
    protected $resourceCalculator;

    protected $theftHelper;
    protected $unitHelper;

    public function __construct()
    {
        $this->titleHelper = app(TitleHelper::class);
    }

    public function getUnitsReturningArray(Dominion $dominion, array $event, string $eventType): array
    {
        $unitsReturning = [];
        foreach($dominion->race->units as $unit)
        {
            $unitsReturning['military_unit'.$unit->slot] = array_fill(1, 12, 0);
        }

        $unitsReturning['military_spies'] = array_fill(1, 12, 0);
        $unitsReturning['military_wizards'] = array_fill(1, 12, 0);
        $unitsReturning['military_archmages'] = array_fill(1, 12, 0);



        return $unitsReturning;
    }

}
