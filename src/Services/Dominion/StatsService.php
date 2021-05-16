<?php

namespace OpenDominion\Services\Dominion;

use DB;
use Carbon\Carbon;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Stat;
use OpenDominion\Models\DominionStat;
use OpenDominion\Models\RealmStat;
use OpenDominion\Models\RoundStat;

class StatsService
{

    public function getStat(Dominion $dominion, string $statKey): int
    {
        $stat = Stat::where('key', $statKey)->first();

        if($dominionStat = DominionStat::where('stat_id',$stat->id)->where('dominion_id',$dominion->id)->first())
        {
            return $dominionStat->value;
        }

        return 0;
    }

    public function updateStat(Dominion $dominion, string $statKey, int $value): void
    {
        $stat = Stat::where('key', $statKey)->first();

        if($this->dominionHasStat($dominion, $statKey))
        {
            DB::transaction(function () use ($dominion, $stat, $value)
            {
                DominionStat::where('dominion_id', $dominion->id)->where('stat_id', $stat->id)
                ->increment('value', $value);
            });
        }
        else
        {
            DB::transaction(function () use ($dominion, $stat, $value)
            {
                DominionStat::create([
                    'dominion_id' => $dominion->id,
                    'stat_id' => $stat->id,
                    'value' => $value
                ]);
            });

        }
    }

    public function setStat(Dominion $dominion, string $statKey, int $value): void
    {
        $stat = Stat::where('key', $statKey)->first();

        if($this->dominionHasStat($dominion, $statKey))
        {
            DB::transaction(function () use ($dominion, $stat, $value)
            {
                DominionStat::where('dominion_id', $dominion->id)->where('stat_id', $stat->id)
                ->set('value', $value);
            });
        }
        else
        {
            DB::transaction(function () use ($dominion, $stat, $value)
            {
                DominionStat::create([
                    'dominion_id' => $dominion->id,
                    'stat_id' => $stat->id,
                    'value' => $value
                ]);
            });

        }
    }

    public function dominionHasStat(Dominion $dominion, string $statKey): bool
    {
        $stat = Stat::where('key', $statKey)->first();
        if(!$stat)
        {
        #  dd($stat, $statKey);
        }
        return DominionStat::where('stat_id',$stat->id)->where('dominion_id',$dominion->id)->first() ? true : false;
    }



    # Realm stats
    public function getRealmStat(Realm $realm, string $statKey): int
    {
        $stat = Stat::where('key', $statKey)->first();

        if($dominionStat = RelamStat::where('stat_id',$stat->id)->where('realm_id', $realm->id)->first())
        {
            return $dominionStat->value;
        }

        return 0;
    }

    public function updateRealmStats(Realm $realm, string $statKey, int $value): void
    {
        $stat = Stat::where('key', $statKey)->first();

        if($this->realmHasStat($realm, $statKey))
        {
            DB::transaction(function () use ($realm, $stat, $value)
            {
                RealmStat::where('realm_id', $realm->id)->where('stat_id', $stat->id)
                ->increment('value', $value);
            });
        }
        else
        {
            DB::transaction(function () use ($realm, $stat, $value)
            {
                DominionStat::create([
                    'realm_id' => $realm->id,
                    'stat_id' => $stat->id,
                    'value' => $value
                ]);
            });

        }
    }

    public function realmHasStat(Realm $realm, string $statKey): bool
    {
        $stat = Stat::where('key', $statKey)->first();
        return RealmStat::where('stat_id',$stat->id)->where('realm_id',$dominion->id)->first() ? true : false;
    }

}
