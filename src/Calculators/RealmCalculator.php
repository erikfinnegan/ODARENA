<?php

namespace OpenDominion\Calculators;


use DB;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Realm;

class RealmCalculator
{
    /**
     * Checks if Realm has a monster.
     *
     * @param Realm $realm
     * @return int
     */
     public function hasMonster(Realm $realm): bool
     {
          $monster = DB::table('dominions')
                         ->join('races', 'dominions.race_id', '=', 'races.id')
                         ->join('realms', 'realms.id', '=', 'dominions.realm_id')
                         ->select('dominions.id')
                         ->where('dominions.round_id', '=', $realm->round->id)
                         ->where('realms.id', '=', $realm->id)
                         ->where('races.name', '=', 'Monster')
                         ->groupBy('realms.alignment')
                         ->pluck('dominions.id')->first();

          if($monster === null)
          {
            return false;
          }

         return $monster;
     }

    public function getMonster(Realm $realm): Dominion
    {
        $monster = DB::table('dominions')
                        ->join('races', 'dominions.race_id', '=', 'races.id')
                        ->join('realms', 'realms.id', '=', 'dominions.realm_id')
                        ->select('dominions.id')
                        ->where('dominions.round_id', '=', $realm->round->id)
                        ->where('realms.id', '=', $realm->id)
                        ->where('races.name', '=', 'Monster')
                        ->groupBy('realms.alignment')
                        ->pluck('dominions.id')->first();

                        #dd($monsters);

        $monster = Dominion::findorfail($monster);

        return $monster;
    }

}
