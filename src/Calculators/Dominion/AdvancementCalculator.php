<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Models\Advancement;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\DominionAdvancement;
use OpenDominion\Models\Race;

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
     * Returns the Dominion's current XP cost to level up a specific advancement.
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

    public function canAffordToLevelUpAdvancement(Dominion $dominion, Advancement $advancement)
    {
        $dominionAdvancement = DominionAdvancement::where('dominion_id', $dominion->id)
            ->where('advancement_id', $advancement->id)
            ->first();

        $cost = $this->getLevelUpCost($dominion, $dominionAdvancement);

        return $dominion->xp >= $cost;
    }

    public function canLevelUp(Dominion $dominion, Advancement $advancement): bool
    {
        if($currentLevel = $this->getCurrentLevel($dominion, $advancement))
        {
            # Not checking for canAffordToeveUp, for UI purposes
            return ($currentLevel < $this->getDominionMaxLevel($dominion));
        }
        return true;
    }

    public function hasAdvancementLevel(Dominion $dominion, Advancement $advancement, int $level): bool
    {
        $dominionAdvancement = DominionAdvancement::where('dominion_id', $dominion->id)
            ->where('advancement_id', $advancement->id)
            ->first();

        if(!$dominionAdvancement)
        {
            return false;
        }

        return $dominionAdvancement->level >= $level;
    }

    public function isAdvancementAvailableToDominion(Dominion $dominion, Advancement $advancement): bool
    {
        return $this->isAdvancementAvailableToRace($dominion->race, $advancement);
    }

    public function isAdvancementAvailableToRace(Race $race, Advancement $advancement): bool
    {
        $isAvailable = true;

        if(count($advancement->exclusive_races) > 0 and !in_array($race->name, $advancement->exclusive_races))
        {
            $isAvailable = false;
        }

        if(count($advancement->excluded_races) > 0 and in_array($race->name, $advancement->excluded_races))
        {
            $isAvailable = false;
        }

        return $isAvailable;
    }

}
