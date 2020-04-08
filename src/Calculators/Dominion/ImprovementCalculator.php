<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Models\Dominion;

# ODA
use OpenDominion\Calculators\Dominion\SpellCalculator;

class ImprovementCalculator
{
    /** @var LandCalculator */
    protected $landCalculator;

    /** @var SpellCalculator */
    protected $spellCalculator;

    /**
     * ImprovementCalculator constructor.
     *
     * @param LandCalculator $landCalculator
     */
    public function __construct(
        SpellCalculator $spellCalculator,
        LandCalculator $landCalculator)
    {
        $this->spellCalculator = $spellCalculator;
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

    public function getResourceWorthRaw(string $resource, ?Dominion $dominion): int
    {
        # Standard values;
        $worth = [
                    'platinum' => 1,
                    'lumber' => 2,
                    'ore' => 2,
                    'gems' => 12,
                ];

        # Void: only sees mana
        if($dominion->race->getPerkValue('can_invest_mana'))
        {
          unset($worth);
          $worth['mana'] = 5;
        }
        # Growth: only sees food
        if($dominion->race->getPerkValue('tissue_improvement'))
        {
          unset($worth);
          $worth['food'] = 1;
        }
        # Demon: can also invest souls (unused?)
        if($dominion->race->getPerkValue('can_invest_soul'))
        {
          $worth['soul'] = 100;
        }

        return $worth[$resource];

    }

    public function getResourceWorthMultipler(string $resource, ?Dominion $dominion): float
    {
        if(!isset($dominion))
        {
            return 0;
        }
        else
        {
            $multiplier = 0;

            ## Extra Ore imp points
            if($resource == 'ore' and $dominion->race->getPerkValue('ore_improvement_points'))
            {
              $multiplier += $dominion->race->getPerkValue('ore_improvement_points') / 100;
            }

            ## Extra Lumber imp points
            if($resource == 'lumber' and $dominion->race->getPerkValue('lumber_improvement_points'))
            {
              $multiplier += $dominion->race->getPerkValue('lumber_improvement_points') / 100;
            }

            ## Extra gem imp points (from Gemcutting)
            if($resource == 'gems' and $dominion->getTechPerkMultiplier('gemcutting'))
            {
              $multiplier += $dominion->getTechPerkMultiplier('gemcutting');
            }

            ## Extra imp points from racial improvements bonus
            if($dominion->race->getPerkMultiplier('invest_bonus'))
            {
              $multiplier += $dominion->race->getPerkMultiplier('invest_bonus');
              dd($dominion->race->getPerkMultiplier('invest_bonus'));
            }

            # Imperial Gnome: Spell (increase imp points by 10%)
            if($this->spellCalculator->isSpellActive($dominion, 'spiral_architecture'))
            {
                $multiplier += 0.10;
            }

            return $multiplier;
        }
    }

    public function getResourceWorth(string $resource, ?Dominion $dominion): float
    {
        $resourceWorthRaw = $this->getResourceWorthRaw($resource, $dominion);
        $resurceWorthMultiplier = $this->getResourceWorthMultipler($resource, $dominion);

        return $resourceWorthRaw * (1 + $resurceWorthMultiplier);

    }
}
