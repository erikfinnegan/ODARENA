<?php

namespace OpenDominion\Helpers;

use DB;
use Illuminate\Database\Eloquent\Collection;
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

    public function getDominionsForRoundForStat(Round $round, string $statKey): array
    {
          $dominions = $this->roundHelper->getRoundDominions($round, false, true);

          foreach($dominions as $key => $dominion)
          {
              $results[$dominion->id] = $this->statsService->getStat($dominion, $statKey);
          }

          # Sort the dominions by ID
          ksort($results);

          # Sort the dominions by value
          arsort($results);

          return $results;
    }

    public function getTopDominionForRoundForStat(Round $round, string $statKey): array
    {
        $results = $this->getDominionsForRoundForStat($round, $statKey);

        $result = array_slice($results, 0, 1, true);

        return $result;
    }

    public function getTopOpStatsForRound(Round $round): Collection
    {
        $stats = Stat::where('key','like','day%op')->pluck('id');

        return RoundStat::where('round_id',$round->id)->whereIn('stat_id',$stats)->get();
    }

    public function getAllDominionStatKeysForRound(Round $round): array
    {
        $statKeys = [];
        $dominions = $this->roundHelper->getRoundDominions($round, false, true);

        $dominionStats = DominionStat::whereIn('dominion_id', $dominions)->get();

        foreach($dominionStats as $key => $dominionStat)
        {
            $statKeys[] = Stat::where('id',$dominionStat->stat_id)->firstOrFail()->key;
        }

        $statKeys = array_unique($statKeys);

        # Sort the dominions by value
        #asort($statKeys);

        return $statKeys;

    }

}
