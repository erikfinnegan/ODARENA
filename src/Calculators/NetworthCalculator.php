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
    public function __construct(
        BuildingCalculator $buildingCalculator,
        LandCalculator $landCalculator,
        MilitaryCalculator $militaryCalculator
    ) {
        $this->buildingCalculator = $buildingCalculator;
        $this->landCalculator = $landCalculator;
        $this->militaryCalculator = $militaryCalculator;
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

        // Values
        $networthPerSpy = 5;
        $networthPerWizard = 5;
        $networthPerArchMage = 10;
        $networthPerLand = 20;
        $networthPerBuilding = 5;

        foreach ($dominion->race->units as $unit) {
            $totalUnitsOfType = $this->militaryCalculator->getTotalUnitsForSlot($dominion, $unit->slot);
            $networth += $totalUnitsOfType * $this->getUnitNetworth($dominion, $unit);
        }

        $networth += ($dominion->military_spies * $networthPerSpy);
        $networth += ($dominion->military_wizards * $networthPerWizard);
        $networth += ($dominion->military_archmages * $networthPerArchMage);

        $networth += ($this->landCalculator->getTotalLand($dominion) * $networthPerLand);
        $networth += ($this->buildingCalculator->getTotalBuildings($dominion) * $networthPerBuilding);

        // todo: Certain units have conditional bonus DP/OP. Do we need to calculate those too?
        // racial networth bonuses (wood elf, dryad, sylvan, rockapult, gnome, adept, dark elf, frost mage, ice elemental, icekin)

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
        if (isset($unit->static_networth) && $unit->static_networth > 0)
        {
          return (float)$unit->static_networth;
        }
        else
        {
         return ($unit->cost_platinum
                 + $unit->cost_ore*2
                 + $unit->cost_lumber*2
                 + $unit->cost_food*2
                 + $unit->cost_mana*5
                 + $unit->cost_gem*7.5
                 + $unit->cost_soul*10
                 + $unit->cost_blood*3
                 + $unit->cost_unit1*10
                 + $unit->cost_unit2*10
                 + $unit->cost_unit3*20
                 + $unit->cost_unit4*20
                 + $unit->cost_morale*10
                 + $unit->cost_prestige*10
                 + $unit->cost_wild_yeti*20
             )/100;
          }

      }
}
