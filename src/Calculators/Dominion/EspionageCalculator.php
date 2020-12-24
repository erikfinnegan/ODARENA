<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\Spyop;

class EspionageCalculator
{
    // todo: clean this up
    public function canPerform(Dominion $dominion, Spyop $spyop): bool
    {

        if(
          !$this->isSpyopAvailableToDominion($dominion, $spyop)
          or ($dominion->spy_strength - $this->getSpyStrengthCost($spyop)) < 0
          or $dominion->round->hasOffensiveActionsDisabled())
          or !$this->isSpyopAvailableToDominion($dominion, $spell)
          or ($spell->scope == 'hostile' and (now()->diffInDays($dominion->round->start_date) < 1)
        )
        {
            return false;
        }

        return true;
    }

    public function isSpyopAvailableToDominion(Dominion $dominion, Spyop $spyop): bool
    {
        $isAvailable = true;

        if(count($spyop->exclusive_races) > 0 and !in_array($dominion->race->name, $spyop->exclusive_races))
        {
            $isAvailable = false;
        }

        if(count($spyop->excluded_races) > 0 and in_array($dominion->race->name, $spyop->excluded_races))
        {
            $isAvailable = false;
        }

        return $isAvailable;
    }

    public function getSpyStrengthCost(Spyop $spyop)
    {
        # Default values
        $scopeCost = [
                'info' => 1,
                'theft' => 4,
                'hostile' => 4,
            ];

        $cost = $scopeCost[$spyop->scope];

        return $spyop->spy_strength ?? $cost;
    }


}
