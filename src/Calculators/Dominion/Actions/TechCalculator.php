<?php

namespace OpenDominion\Calculators\Dominion\Actions;

use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Tech;

class TechCalculator
{
    /** @var LandCalculator */
    protected $landCalculator;

    /** @var ImprovementCalculator */
    protected $improvementCalculator;

    /**
     * TechCalculator constructor.
     *
     * @param LandCalculator $landCalculator
     */
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
    public function getTechCost(Dominion $dominion, Tech $techToUnlock = Null): int
    {

        $xpCostPerAcre = 10;
        $minimumCost = 10000;
        $perkMultiplier = 1 + $this->getTechCostMultiplier($dominion);
        $land = $this->landCalculator->getTotalLand($dominion);


        if($techToUnlock == Null)
        {
            return max($minimumCost, $land * $xpCostPerAcre * $perkMultiplier);
        }

        $techToUnlock = Tech::where('key', $techToUnlock->key)->first();

        $cost = max($minimumCost, $land * $xpCostPerAcre * $perkMultiplier) * (1 + $techToUnlock->cost_multiplier / 100);

        return $cost;

    }

    public function getTechCostMultiplier(Dominion $dominion)
    {
        $multiplier = 0;

        # Perk
        if($dominion->race->getPerkMultiplier('tech_costs'))
        {
          $multiplier += $dominion->race->getPerkMultiplier('tech_costs');
        }

        return $multiplier;
    }

    /**
     * Determine if the Dominion meets the requirements to unlock a new tech.
     *
     * @param Dominion $dominion
     * @return bool
     */
    public function hasPrerequisites(Dominion $dominion, Tech $tech): bool
    {
        $unlockedTechs = $dominion->techs->pluck('key')->all();

        return count(array_diff($tech->prerequisites, $unlockedTechs)) == 0;
    }
}
