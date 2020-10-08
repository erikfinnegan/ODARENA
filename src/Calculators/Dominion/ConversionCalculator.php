<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;

# ODA
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;

class ConversionCalculator
{
    /** @var MilitaryCalculator */
    protected $militaryCalculator;

    /** @var RangeCalculator */
    protected $rangeCalculator;

    /**
     * CasualtiesCalculator constructor.
     *
     * @param LandCalculator $landCalculator
     * @param PopulationCalculator $populationCalculator
     * @param SpellCalculator $spellCalculator
     * @param UnitHelper $unitHelper
     * @param MilitaryCalculator $militaryCalculator
     * @param RangeCalculator $rangeCalculator
     */
    public function __construct(
        MilitaryCalculator $militaryCalculator,
        RangeCalculator $rangeCalculator)
    {
        $this->militaryCalculator = $militaryCalculator;
        $this->rangeCalculator = $rangeCalculator;
    }

    public function getConversionMultiplier(Dominion $converter, Dominion $target, array $converterUnits = null, array $targetUnits = null)
    {

        $conversionMultiplier = 0;

        # Tech: up to +15%
        if($converter->getTechPerkMultiplier('conversions'))
        {
            $conversionMultiplier += $converter->getTechPerkMultiplier('conversions');
        }
        # Title: Embalmer
        if($converter->title->getPerkMultiplier('conversions'))
        {
            $conversionMultiplier += $converter->title->getPerkMultiplier('conversions') * $converter->title->getPerkBonus($converter);
        }

        $conversionMultiplier -= $target->race->getPerkMultiplier('reduced_conversions');

        return 1 + $conversionMultiplier;

    }

    public function getOffensiveConversions(Dominion $converter, Dominion $target, array $converterUnits, array $converterUnitsLost, array $targetUnits, array $targetUnitsLost)
    {
        $conversions = array_fill(1,4,0);



        return $conversions;
    }

}
