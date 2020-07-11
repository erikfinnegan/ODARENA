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
    public function getTechCost(Dominion $dominion, Tech $techToUnlock = Null, int $level = 1): int
    {
        # Start at 0
        $cost = 0;

        # Add 10 x acres, minimum 10,000
        $cost += min($this->landCalculator->getTotalLand($dominion) * 10, 10000);

        # Add extra cost from level (from $techToUnlock, if known, or from $level)
        if($techToUnlock = Tech::where('key', $techToUnlock->key)->first())
        {
            $cost *= 1 + $techToUnlock->level / 10;
        }
        elseif(isset($level) and ($level >= 1 and $level <= 6))
        {
            $cost *= 1 + $level / 10;
        }

        $cost *= 1 + $this->getTechCostMultiplier($dominion);

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
