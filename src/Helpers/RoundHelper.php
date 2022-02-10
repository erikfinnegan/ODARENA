<?php

namespace OpenDominion\Helpers;

use DB;
use Illuminate\Support\Collection;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Round;

use OpenDominion\Calculators\Dominion\LandCalculator;

use OpenDominion\Services\Dominion\StatsService;

class RoundHelper
{
    public function __construct()
    {
        $this->landCalculator = app(LandCalculator::class);
        $this->statsService = app(StatsService::class);
    }


    public function getRoundModeString(Round $round): string
    {
        switch ($round->mode)
        {
            case 'standard':
                return 'Standard';

            case 'deathmatch':
                return 'Deathmatch';

            case 'artefacts':
                return 'Artefacts';
        }
    }

    public function getRoundCountdownTickLength(): int
    {
        return 52;
    }

    public function getRoundModeDescription(Round $round): string
    {
        switch ($round->mode)
        {
            case 'standard':
                return 'Your dominion is in a realm with friendly dominions fighting against all other realms to become the largest dominion.';

            case 'deathmatch':
                return 'Every dominion for itself!';

            case 'Artefacts':
                return 'Your dominion is in a realm with friendly dominions and your goal is to be the first realm to capture at least ten Artefacts.';
        }
    }

    public function getRoundModeIcon(Round $round): string
    {
        switch ($round->mode)
        {
            case 'standard':
                return '<i class="fas fa-users fa-fw text-green"></i>';

            case 'deathmatch':
                return '<i class="ra ra-daggers ra-fw text-red"></i>';

            default:
                return '&mdash;';
        }
    }

    public function getRoundDominions(Round $round, bool $inclueActiveRounds = false)
    {
        $dominions = Dominion::where('round_id', $round->id)
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

    public function getRoundDominionsByLand(Round $round, int $max = null): array
    {
        $dominions = [];

        foreach($this->getRoundDominions($round) as $dominion)
        {
            $dominions[$dominion->id] = $this->landCalculator->getTotalLand($dominion);
        }

        arsort($dominions);

        if($max)
        {
            $dominions = array_slice($dominions, 0, $max, true);
        }

        $rankedList = [];

        $rank = 1;
        foreach($dominions as $dominionId => $landSize)
        {
            $rankedList[$rank] = $dominionId;
            $rank++;
        }

        return $rankedList;
    }

    public function getDominionPlacementInRound(Dominion $dominion): int
    {
        $round = $dominion->round;
        $dominions = $this->getRoundDominionsByLand($round);

        return array_search($dominion->id, $dominions);
    }

    public function getRoundPlacementEmoji(int $placement): string
    {
        $emojis = [
            1 => "🥇",
            2 => "🥈",
            3 => "🥉",
        ];

        return $emojis[$placement];
    }

}
