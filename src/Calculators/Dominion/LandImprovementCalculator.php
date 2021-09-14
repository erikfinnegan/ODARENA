<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Models\Dominion;
use OpenDominion\Calculators\Dominion\PrestigeCalculator;

class LandImprovementCalculator
{
    protected $prestigeCalculator;

    public function __construct(
        PrestigeCalculator $prestigeCalculator
        )
    {
        $this->prestigeCalculator = $prestigeCalculator;
    }

    /**
     * Returns the Dominion's improvement multiplier for a given improvement type.
     *
     * @param Dominion $dominion
     * @param string $improvementType
     * @return float
     */
    private function getLandImprovementBonusMultiplier(Dominion $dominion): float
    {
        $bonus = 0;
        if($dominion->race->getPerkValue('land_improvements'))
        {
            $bonus = 1;
            $bonus += $dominion->getTechPerkMultiplier('improvements');
            $bonus *= 1 + $this->prestigeCalculator->getPrestigeMultiplier($dominion);
        }

        return $bonus;
    }

}
