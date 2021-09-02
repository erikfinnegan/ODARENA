<?php

namespace OpenDominion\Calculators;

use OpenDominion\Calculators\Dominion\BuildingCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Realm;
use OpenDominion\Models\Unit;

class NetworthCalculator
{
    /** @var BuildingCalculator */
    protected $buildingCalculator;

    /** @var LandCalculator */
    protected $landCalculator;

    /** @var MilitaryCalculator */
    protected $militaryCalculator;

    /**
     * NetworthCalculator constructor.
     *
     * @param BuildingCalculator $buildingCalculator
     * @param LandCalculator $landCalculator
     * @param MilitaryCalculator $militaryCalculator
     */
    public function __construct() {

          $this->buildingCalculator = app(BuildingCalculator::class);
          $this->landCalculator = app(LandCalculator::class);
          $this->militaryCalculator = app(MilitaryCalculator::class);
    }

    /**
     * Returns a Realm's networth.
     *
     * @param Realm $realm
     * @return int
     */
    public function getRealmNetworth(Realm $realm): int
    {
        $networth = 0;

        foreach ($realm->dominions as $dominion)
        {
            $networth += $this->getDominionNetworth($dominion);
        }

        return $networth;
    }

    /**
     * Returns a Dominion's networth.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getDominionNetworth(Dominion $dominion): int
    {
        $networth = 0;

        foreach ($dominion->race->units as $unit)
        {
            $totalUnitsOfType = $this->militaryCalculator->getTotalUnitsForSlot($dominion, $unit->slot);
            $networth += $totalUnitsOfType * $this->getUnitNetworth($dominion, $unit);
        }

        $networth += ($dominion->military_spies * 5);
        $networth += ($dominion->military_wizards * 5);
        $networth += ($dominion->military_archmages * 10);

        $networth += ($this->landCalculator->getTotalLand($dominion) * 20);
        $networth += ($this->buildingCalculator->getTotalBuildings($dominion) * 5);

        $networth += $dominion->resource_soul / 9;

        return round($networth);
    }

    /**
     * Returns a single Unit's networth.
     *
     * @param Dominion $dominion
     * @param Unit $unit
     * @return float
     */
     public function getUnitNetworth(Dominion $dominion, Unit $unit): float
     {
        if ($unit->static_networth !== 0)
        {
            return $unit->static_networth;
        }
        else
        {
            return (
                      $this->militaryCalculator->getUnitPowerWithPerks($dominion, null, null, $unit, 'offense') +
                      $this->militaryCalculator->getUnitPowerWithPerks($dominion, null, null, $unit, 'defense')
                    );
          }

      }
}
