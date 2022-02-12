<?php

namespace OpenDominion\Services;

use Illuminate\Database\Eloquent\Builder;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Pack;
use OpenDominion\Models\Race;
use OpenDominion\Models\Round;

class RoundService
{

    public function checkIfRoundWinConditionIsMet(Round $round): bool
    {

    }

    public function getRoundWinner(Round $round)
    {

    }

    public function getCountdownDuration(Round $round): int
    {
        
    }

}
