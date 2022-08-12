<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Models\Advancement;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\DominionAdvancement;

class AdvancementCalculator
{

    public function __construct(
        LandCalculator $landCalculator,
        ImprovementCalculator $improvementCalculator)
    {
        $this->landCalculator = $landCalculator;
        $this->improvementCalculator = $improvementCalculator;
    }

    /**
     * Returns the Dominion's current experience point cost to unlock a new tech.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getLevelUpCost(Dominion $dominion, DominionAdvancement $dominionAdvancement = null): int
    {
        $cost = max($this->landCalculator->getTotalLand($dominion) * 5, 5000);

        if($dominionAdvancement)
        {
            $cost *= (1 + $dominionAdvancement->level / 10);
        }

        $cost *= 1 + $this->getAdvancementCostMultiplier($dominion);

        return $cost;
    }

    public function getAdvancementCostMultiplier(Dominion $dominion)
    {
        $multiplier = 0;

        $multiplier += $dominion->race->getPerkMultiplier('advancement_costs');
        $multiplier += $dominion->getImprovementPerkMultiplier('advancement_costs');
        $multiplier += $dominion->getSpellPerkMultiplier('advancement_costs');

        return $multiplier;
    }

    public function getCurrentLevel(Dominion $dominion, Advancement $advancement): int
    {
        $advancement = DominionAdvancement::where('dominion_id', $dominion->id)
            ->where('advancement_id', $advancement->id)
            ->first();

        if($advancement === null) {
            return 0;
        }

        return $advancement->level;
    }

    public function getDominionMaxLevel(Dominion $dominion): int
    {
        if($dominion->race->getPerkValue('cannot_tech'))
        {
            return 0;
        }
        return 10;
    }

    public function canLevelUp(Dominion $dominion, Advancement $advancement): bool
    {
        if($currentLevel = $this->getCurrentLevel($dominion, $advancement))
        {
            return ($currentLevel + 1 < $this->getDominionMaxLevel($dominion));
        }
        return true;
    }

}
