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

    public function getUserDominions(User $user, bool $inclueActiveRounds = false)
    {
        $dominions = Dominion::where('user_id', $user->id)
                      ->where('is_locked','=',0)
                      ->where('protection_ticks','=',0)
                      ->get();

        if(!$inclueActiveRounds)
        {
            foreach($dominions as $key => $dominion)
            {
                if(!$dominion->round->hasEnded())
                {
                    $dominions->forget($key);
                }
            }
        }

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

    public function getPrestigeSumForUser(User $user): float
    {
        $value = 0.00;

        foreach($this->getUserDominions($user) as $dominion)
        {
            $value += $dominion->prestige;
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

    public function getTopRaces(User $user, int $max = null): array
    {
        $races = [];

        foreach($this->getUserDominions($user) as $dominion)
        {
            if(isset($races[$dominion->race->name]))
            {
                $races[$dominion->race->name] += 1;
            }
            else
            {
                $races[$dominion->race->name] = 1;
            }
            #$races[$dominion->race->name] = (isset($races[$dominion->race->name]) ? $races[$dominion->race->name] + 1 : 1);
        }

        arsort($races);

        if($max)
        {
            $races = array_slice($races, 0, $max);
        }

        return $races;
    }

}
