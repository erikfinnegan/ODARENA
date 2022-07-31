<?php

namespace OpenDominion\Calculators\Dominion;

use DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenDominion\Helpers\DecreeHelper;
use OpenDominion\Models\Decree;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\DominionDecreeState;
use OpenDominion\Models\Race;

class DecreeCalculator
{
    /** @var array */
    protected $activeDeitys = [];

    public function __construct()
    {
        $this->decreeHelper = app(DecreeHelper::class);
    }

    public function isDecreeAvailableToDominion(Dominion $dominion, Decree $decree): bool
    {
        return $this->isDecreeAvailableToRace($dominion->race, $decree);
    }

    public function isDecreeAvailableToRace(Race $race, Decree $decree): bool
    {
        $isAvailable = true;

        if(count($decree->exclusive_races) > 0 and !in_array($race->name, $decree->exclusive_races))
        {
            $isAvailable = false;
        }

        if(count($decree->excluded_races) > 0 and in_array($race->name, $decree->excluded_races))
        {
            $isAvailable = false;
        }

        return $isAvailable;
    }

    public function getTicksUntilDominionCanRevokeDecree(Dominion $dominion, Decree $decree): int
    {
        if(!$this->decreeHelper->isDominionDecreeIssued($dominion, $decree))
        {
            return 0;
        }

        $dominionDecreeState = DominionDecreeState::where('dominion_id', $dominion->id)
            ->where('decree_id', $decree->id)
            ->first();

        $decreeIssueTick = $dominionDecreeState->tick;
        $decreeCooldown = $decree->cooldown;
        $roundTick = $dominion->round->ticks;

        return max($decreeIssueTick + $decreeCooldown - $roundTick, 0);

    }

    public function canDominionRevokeDecree(Dominion $dominion, Decree $decree): bool
    {
        return $this->getTicksUntilDominionCanRevokeDecree($dominion, $decree) === 0;
    }
}
