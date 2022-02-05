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

}
