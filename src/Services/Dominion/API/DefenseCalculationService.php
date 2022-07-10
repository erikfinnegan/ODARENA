<?php

namespace OpenDominion\Services\Dominion\API;

use LogicException;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Models\Building;
use OpenDominion\Models\Deity;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\DominionDeity;
use OpenDominion\Models\DominionSpell;
use OpenDominion\Models\DominionBuilding;
use OpenDominion\Models\DominionImprovement;
use OpenDominion\Models\Improvement;
use OpenDominion\Models\Realm;
use OpenDominion\Models\Spell;

class DefenseCalculationService
{
    /** @var MilitaryCalculator */
    protected $militaryCalculator;

    /** @var array Calculation result array. */
    protected $calculationResult = [
        'result' => 'success',
        'race' => null,
        'dp' => 0,
        'dp_multiplier' => 0,
        'dp_raw' => 0,
        'units' => [ // raw DP
            '1' => ['dp' => 0],
            '2' => ['dp' => 0],
            '3' => ['dp' => 0],
            '4' => ['dp' => 0],
        ],
    ];

    /**
     * DefenseCalculationService constructor.
     *
     * @param LandCalculator $landCalculator
     * @param MilitaryCalculator $militaryCalculator
     */
    public function __construct(
        LandCalculator $landCalculator,
        MilitaryCalculator $militaryCalculator
    )
    {
        $this->landCalculator = $landCalculator;
        $this->militaryCalculator = $militaryCalculator;
    }

    /**
     * Calculates the total defense of a $dominion instance.
     *
     * @param Dominion $dominion
     * @param array $calc
     * @return array
     */
    public function calculate(Dominion $dominion, ?array $calc): array
    {
        // Sanitize input
        if($calc !== null) {
            $dominion->calc = $calc;
        }

        if($calc['deity'])
        {
            $deity = Deity::findOrFail($calc['deity']);
            $dominionDeity = new DominionDeity([
                'dominion_id' => $dominion->id,
                'deity_id' => $deity->id,
                'duration' => intval($calc['devotion'])
            ]);
            $dominion->setRelation('deity', $deity);
            $dominion->setRelation('devotion', $dominionDeity);
            #$dominion->deity = $dominionDeity;
            #dd($dominion->deity->name);
        }



        // Calculate unit stats
        foreach ($dominion->race->units as $unit) {
            $this->calculationResult['units'][$unit->slot]['dp'] = $this->militaryCalculator->getUnitPowerWithPerks(
                $dominion,
                null,
                null,
                $unit,
                'defense'
            );
        }

        // Calculate total defense
        $templeReduction = 0;#$this->militaryCalculator->getTempleReduction($dominion);
        $this->calculationResult['race'] = $dominion->race->id;
        $this->calculationResult['temple_reduction'] = $templeReduction * 100;
        $this->calculationResult['dp_raw'] = $this->militaryCalculator->getDefensivePowerRaw($dominion);
        $this->calculationResult['dp_multiplier'] = ($this->militaryCalculator->getDefensivePowerMultiplier($dominion, null, $templeReduction) - 1) * 100;
        #$this->calculationResult['dp'] = $this->militaryCalculator->getDefensivePower($dominion, null, null, null, $templeReduction);
        $this->calculationResult['dp'] = $this->militaryCalculator->getDefensivePower($dominion);

        return $this->calculationResult;
    }
}
