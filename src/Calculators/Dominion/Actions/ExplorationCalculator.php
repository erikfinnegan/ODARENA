<?php

namespace OpenDominion\Calculators\Dominion\Actions;

use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Models\Dominion;
use OpenDominion\Services\Dominion\GuardMembershipService;

# ODA
use OpenDominion\Calculators\Dominion\SpellCalculator;

class ExplorationCalculator
{
    /** @var LandCalculator */
    protected $landCalculator;

    /** @var ImprovementCalculator */
    protected $improvementCalculator;

    /** @var GuardMembershipService */
    protected $guardMembershipService;

    /** @var SpellCalculator */
    protected $spellCalculator;

    /**
     * ExplorationCalculator constructor.
     *
     * @param LandCalculator $landCalculator
     * @param GuardMembershipService $guardMembershipService
     */
    public function __construct()
    {
          $this->landCalculator = app(LandCalculator::class);
          $this->guardMembershipService = app(GuardMembershipService::class);
          $this->spellCalculator = app(SpellCalculator::class);
          $this->landImprovementCalculator = app(LandImprovementCalculator::class);
    }

    /**
     * Returns the Dominion's exploration platinum cost (raw).
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getPlatinumCostRaw(Dominion $dominion): int
    {
        $totalLand = $this->landCalculator->getTotalLand($dominion);
        $totalLand += $this->landCalculator->getTotalLandIncoming($dominion);
        $platinum = sqrt($totalLand)*$totalLand/6-1000;

        return $platinum;
    }

     /**
      * Returns the Dominion's exploration platinum cost bonus.
      *
      * @param Dominion $dominion
      * @return int
      */
      public function getPlatinumCostBonus(Dominion $dominion): float
      {
        $multiplier = 0;

        // Techs (returns negative value)
        $multiplier += $dominion->getTechPerkMultiplier('explore_platinum_cost');

        // Title (returns negative value)
        if(isset($dominion->title))
        {
            $multiplier += $dominion->title->getPerkMultiplier('explore_cost') * $dominion->title->getPerkBonus($dominion);
        }

        // Racial bonus
        $multiplier += $dominion->race->getPerkMultiplier('explore_cost');

        // Improvement: Cartography (returns positive value)
        $multiplier -= $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'cartography');

        # Cap explore plat reduction to 50%.
        $multiplier = max($multiplier, -0.50);

        return (1 + $multiplier);

      }

   /**
    * Returns the Dominion's exploration platinum cost.
    *
    * @param Dominion $dominion
    * @return int
    */
    public function getPlatinumCost(Dominion $dominion): int
    {
      return $this->getPlatinumCostRaw($dominion) * $this->getPlatinumCostBonus($dominion);
    }

    /**
     * Returns the Dominion's exploration draftee cost (raw).
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getDrafteeCostRaw(Dominion $dominion): int
    {
        $draftees = 5;
        $totalLand = $this->landCalculator->getTotalLand($dominion);
        $draftees += (0.003 * (($totalLand - 300) ** 1.07));

        return ceil($draftees);
    }

    /**
     * Returns the Dominion's exploration draftee cost modifier.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getDrafteeCostModifier(Dominion $dominion): int
    {
        $modifier = 0;

        // Techs
        $modifier += $dominion->getTechPerkValue('explore_draftee_cost');

        return round($modifier);
    }

    /**
     * Returns the Dominion's exploration platinum cost.
     *
     * @param Dominion $dominion
     * @return int
     */
     public function getDrafteeCost(Dominion $dominion): int
     {
       return max(3, $this->getDrafteeCostRaw($dominion) + $this->getDrafteeCostModifier($dominion));
     }

    /**
     * Returns the maximum number of acres of land a Dominion can afford to
     * explore.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getMaxAfford(Dominion $dominion): int
    {
        return min(
            floor($dominion->resource_platinum / $this->getPlatinumCost($dominion)),
            floor($dominion->military_draftees / $this->getDrafteeCost($dominion)),
            floor($this->landCalculator->getTotalLand($dominion) * (($dominion->morale/100)/8))
        );
    }

    /**
     * Returns the morale drop after exploring for $amount of acres of land.
     *
     * @param int $amount
     * @return int
     * @todo Does this really belong here? Maybe it should go in a helper, since it isn't dependent on a Dominion instance
     */
    public function getMoraleDrop($dominion, $amountToExplore): int
    {
        $moraleDrop = ($amountToExplore / $this->landCalculator->getTotalLand($dominion)) * 8 * 100;

        return max($moraleDrop, 1);

        #return floor(($amount + 2) / 3);
    }
}
