<?php

namespace OpenDominion\Helpers;

use DB;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\DominionStat;
use OpenDominion\Models\Round;
use OpenDominion\Models\RoundStat;
use OpenDominion\Models\Stat;

use OpenDominion\Services\Dominion\StatsService;

class StatsHelper
{
    public function __construct()
    {
        $this->roundHelper = app(RoundHelper::class);

        $this->statsService = app(StatsService::class);
    }

    public function getStatName(string $statKey)
    {
        return Stat::where('key', $statKey)->firstOrFail()->name;
    }

    public function getTopDominionForRoundForStat(Round $round, string $statKey): array
    {
        $stat = Stat::where('key', $statKey)->firstOrFail();

        $dominions = $this->roundHelper->getRoundDominions($round, true, true);

        foreach($dominions as $key => $dominion)
        {
            $results[$dominion->id] = $this->statsService->getStat($dominion, $statKey);
        }

        # Sort the dominions by ID
        ksort($results);

        # Sort the dominions by value
        arsort($results);

        $result = array_slice($results, 0, 1, true);

        return $result;

    }

}
