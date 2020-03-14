<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Models\Dominion;

class ImprovementCalculator
{
    /** @var LandCalculator */
    protected $landCalculator;

    /**
     * ImprovementCalculator constructor.
     *
     * @param LandCalculator $landCalculator
     */
    public function __construct(LandCalculator $landCalculator)
    {
        $this->landCalculator = $landCalculator;
    }

    /**
     * Returns the Dominion's improvement multiplier for a given improvement type.
     *
     * @param Dominion $dominion
     * @param string $improvementType
     * @return float
     */
    public function getImprovementMultiplierBonus(Dominion $dominion, string $improvementType): float
    {

        $improvementPoints = $dominion->{'improvement_' . $improvementType};
        $totalLand = $this->landCalculator->getTotalLand($dominion);

        $masonriesBonus = $this->getMasonriesBonus($dominion);
        $techBonus = $this->getTechBonus($dominion);
        $bonusMultiplier = 1 + $masonriesBonus + $techBonus;

        $multiplier = $this->getImprovementMaximum($improvementType, $dominion)
            * (1 - exp(-$improvementPoints / ($this->getImprovementCoefficient($improvementType) * $totalLand + 15000)))
            * $bonusMultiplier;

        return round($multiplier, 4);
    }

    /**
     * Returns the Dominion's masonries bonus.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getMasonriesBonus(Dominion $dominion): float
    {
        $totalLand = $this->landCalculator->getTotalLand($dominion);
        $multiplier = (($dominion->building_masonry * 2.75) / $totalLand);

        return round($multiplier, 4);
    }


    /**
     * Returns the Dominion's masonries bonus.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getTechBonus(Dominion $dominion): float
    {
        // Tech
        if($dominion->getTechPerkMultiplier('improvements'))
        {
          $multiplier = $dominion->getTechPerkMultiplier('improvements');
        }
        else
        {
          $multiplier = 0;
        }

        return round($multiplier, 4);
    }

    /**
     * Returns the improvement maximum percentage.
     *
     * @param string $improvementType
     * @return float
     */
    protected function getImprovementMaximum(string $improvementType, Dominion $dominion): float
    {
        $maximumPercentages = [
            'markets' => 20, # Increases platinum production
            'keep' => 15, # Increases max population
            'towers' => 40, # Increases wizard strength, mana production, and reduces damage form black-ops
            'forges' => 20, # Increases OP
            'walls' => 20, # Increases DP
            'harbor' => 40, # Increase food and boat production
            'armory' => 20, # Reduces training costs
            'infirmary' => 20, # Reduces casualties
            'workshops' => 20, # Reduces construction and rezoning costs
            'observatory' => 20, # Increases RP gains and reduces tech costs
            'cartography' => 30, # Increases land explored and lower cost of exploring
            'hideouts' => 40, # Increases spy strength and reduces spy losses
            'forestry' => 20, # Increases lumber production
            'refinery' => 20, # Increases ore production
            'granaries' => 80, # Reduces food and lumber rot
            'tissue' => 20, # Increases max population (Growth)
        ];

        if($dominion->race->getPerkMultiplier('improvements_max'))
        {
          foreach($maximumPercentages as $type => $max)
          {
            $maximumPercentages[$type] = $max * (1 + $dominion->race->getPerkMultiplier('improvements_max'));
          }
        }

        return (($maximumPercentages[$improvementType] / 100) ?: null);
    }

    /**
     * Returns the improvement calculation coefficient.
     *
     * A higher number makes it harder to reach higher improvement percentages.
     *
     * @param string $improvementType
     * @return int
     */
    protected function getImprovementCoefficient(string $improvementType): int
    {
        $coefficients = [
            'markets' => 4000,
            'keep' => 4000,
            'towers' => 5000,
            'forges' => 7500,
            'walls' => 7500,
            'harbor' => 5000,
            'armory' => 4000,
            'infirmary' => 4000,
            'workshops' => 4000,
            'observatory' => 5000,
            'cartography' => 4000,
            'hideouts' => 5000,
            'forestry' => 4000,
            'refinery' => 4000,
            'granaries' => 5000,
            'tissue' => 7500,
        ];

        return ($coefficients[$improvementType] ?: null);
    }
}
