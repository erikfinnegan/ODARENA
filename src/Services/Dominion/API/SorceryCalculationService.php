<?php

namespace OpenDominion\Services\Dominion\API;

use LogicException;
use OpenDominion\Calculators\Dominion\SorceryCalculator;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Spell;

class SorceryCalculationService
{


    /** @var array Calculation result array. */
    protected $calculationResult = [
        'mana_cost' => 0,
    ];

    /**
     * InvadeActionService constructor.
     *
     * @param MilitaryCalculator $militaryCalculator
     * @param RangeCalculator $rangeCalculator
     */
    public function __construct(
        SorceryCalculator $sorceryCalculator
    )
    {
        $this->sorceryCalculator = $sorceryCalculator;
    }

    /**
     * Calculates an expedition
     *
     * @param Dominion $dominion
     * @param array $units
     * @return array
     */
    public function calculate(Dominion $caster, Spell $spell, int $wizardStrength): array
    {
        $this->calculationResult['mana_cost'] = $this->sorceryCalculator->getSorcerySpellManaCost($caster, $spell, $wizardStrength);

        return $this->calculationResult;
    }
}
