<?php

namespace OpenDominion\Services\Dominion;

use Auth;
use Illuminate\Database\Eloquent\Collection;
use LogicException;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Round;
use RuntimeException;
use Session;

class RoundService
{
    public function hasUserDominionInRound(Round $round): bool
    {
        $user = Auth::user();

        return Dominion::where('user_id', $user->id)->where('round_id', $round->id)->first() ? true : false;
    }

    public function getUserDominionFromRound(Round $round): Dominion
    {
        $user = Auth::user();

        return Dominion::where('user_id', $user->id)->where('round_id', $round->id)->first();
    }

}
