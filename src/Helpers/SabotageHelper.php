<?php

namespace OpenDominion\Helpers;

use Illuminate\Support\Collection;
use OpenDominion\Models\Race;
use OpenDominion\Models\Spyop;

use OpenDominion\Calculators\Dominion\EspionageCalculator;

class SabotageHelper
{
    public function __construct()
    {
        $this->espionageCalculator = app(EspionageCalculator::class);
    }

    public function getSabotageOperationsForRace(Race $race)
    {
        $spyops = Spyop::all()->where('scope','hostile')->where('enabled',1)->sortBy('name');

        foreach($spyops as $key => $spyop)
        {
            if(!$this->espionageCalculator->isSpyopAvailableToRace($race, $spyop))
            {
                $spyops->forget($key);
            }
        }

        return $spyops;
    }

}
