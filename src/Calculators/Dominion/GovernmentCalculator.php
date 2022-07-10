<?php

namespace OpenDominion\Calculators\Dominion;

use DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Realm;

class GovernmentCalculator
{
    protected const TICKS_BETWEEN_VOTES = 192;

    public function canVote(Dominion $dominion): bool
    {
        if(isset($dominion->tick_voted))
        {
            #dd($dominion->tick_voted < ($dominion->round->ticks - static::TICKS_BETWEEN_VOTES), $dominion->tick_voted);
            return $dominion->tick_voted < ($dominion->round->ticks - static::TICKS_BETWEEN_VOTES);
        }

        return true;
    }

    public function getTicksUntilCanVote(Dominion $dominion): int
    {
        $tickWhenCanVote = $dominion->tick_voted + static::TICKS_BETWEEN_VOTES;
        $currentTick = $dominion->round->ticks;

        if($tickWhenCanVote <= $currentTick)
        {
            return 0; #Can vote now
        }

        return $tickWhenCanVote - $currentTick;
    }

}
