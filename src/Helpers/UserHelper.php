<?php

namespace OpenDominion\Helpers;

use DB;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\User;

use OpenDominion\Services\Dominion\StatsService;

class UserHelper
{

    public function __construct()
    {
        $this->statsService = app(StatsService::class);
    }


    public function getRoundsPlayed(User $user)
    {
        return $this->getUserDominions($user)->count();
    }

    public function getUserDominions(User $user)
    {
        $dominions = Dominion::where('user_id', $user->id)
                      ->where('is_locked','=',0)
                      ->where('protection_ticks','=',0)
                      ->get();

        return $dominions;
    }

    public function getStatSumForUser(User $user, string $statKey): float
    {
        $value = 0.00;

        foreach($this->getUserDominions($user) as $dominion)
        {
            $value += $this->statsService->getStat($dominion, $statKey);
        }

        return $value;
    }

    public function getStatMaxForUser(User $user, string $statKey): float
    {
        $value = 0.00;

        foreach($this->getUserDominions($user) as $dominion)
        {
            $value = max($this->statsService->getStat($dominion, $statKey), $value);
        }

        return $value;
    }

}
