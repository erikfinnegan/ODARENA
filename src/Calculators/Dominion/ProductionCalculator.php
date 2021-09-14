<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Models\Dominion;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;

class ProductionCalculator
{
    public function __construct()
    {
        $this->militaryCalculator = app(MilitaryCalculator::class);
    }

    /**
     * Returns the Dominion's experience point production.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getXpGeneration(Dominion $dominion): int
    {
        return floor($this->getXpGenerationRaw($dominion) * $this->getXpGenerationMultiplier($dominion));
    }

    public function getXpGenerationRaw(Dominion $dominion): float
    {

        if($dominion->getSpellPerkValue('no_xp_production'))
        {
            return 0;
        }

        $xp = max(0, floor($dominion->prestige));

        $xp += $dominion->getUnitPerkProductionBonus('xp_generation_raw');
        $xp += $dominion->getBuildingPerkValue('xp_generation_raw');

        // Unit Perk: production_from_title
        $xp += $dominion->getUnitPerkProductionBonusFromTitle('xp');

        return max(0, $xp);
    }

    public function getXpGenerationMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Racial Bonus
        $multiplier += $dominion->race->getPerkMultiplier('xp_generation_mod');

        // Buildings
        $multiplier += $dominion->getBuildingPerkMultiplier('xp_generation_mod');

        // Deity
        $multiplier += $dominion->getDeityPerkMultiplier('xp_generation_mod');

        // Land improvements
        $multiplier += $dominion->getLandImprovementsPerkMultiplier('xp_generation_mod');

        // Title
        if(isset($dominion->title))
        {
            $multiplier += $dominion->title->getPerkMultiplier('xp_generation_mod') * $dominion->title->getPerkBonus($dominion);
        }

        // Spell
        $multiplier += $dominion->getSpellPerkMultiplier('xp_generation_mod');

        // Improvements
        $multiplier += $dominion->getImprovementPerkMultiplier('xp_generation_mod');

        return (1 + $multiplier);
    }

    public function getPrestigeInterest(Dominion $dominion): float
    {
        if($dominion->isAbandoned())
        {
            return 0;
        }
        return $dominion->prestige * max(0, $this->militaryCalculator->getNetVictories($dominion) / 40000);
    }
}
