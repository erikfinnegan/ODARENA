<?php

namespace OpenDominion\Calculators;


use DB;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Realm;
use OpenDominion\Calculators\Dominion\SpellCalculator;

class RealmCalculator
{


    public function __construct()
    {
    }

    /**
     * Checks if Realm has a monster.
     *
     * @param Realm $realm
     * @return int
     */
     public function hasMonster(Realm $realm): bool
     {
          $monster_dominion_id = DB::table('dominions')
                         ->join('races', 'dominions.race_id', '=', 'races.id')
                         ->join('realms', 'realms.id', '=', 'dominions.realm_id')
                         ->select('dominions.id')
                         ->where('dominions.round_id', '=', $realm->round->id)
                         ->where('realms.id', '=', $realm->id)
                         ->where('races.name', '=', 'Monster')
                         ->where('dominions.protection_ticks', '=', 0)
                         ->pluck('dominions.id')->first();

          if($monster_dominion_id === null)
          {
            return false;
          }

         return $monster_dominion_id;
     }

   /**
    * Return the monster.
    *
    * @param Realm $realm
    * @return int
    */
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

        $monster = Dominion::findOrFail($monster);

        return $monster;
    }

    /**
     * Calculate how many bodies in the crypt decayed this tick.
     *
     * @param Realm $realm
     * @return int
     */
    public function getCryptBodiesDecayed(Realm $realm): int
    {
        $bodiesDecayed = 0;
        $entombedBodies = 0;

        if($realm->alignment !== 'evil' or $realm->crypt === 0)
        {
            return $bodiesDecayed;
        }
        else
        {
            $bodiesToDecay = $realm->crypt;

            $dominions = $realm->dominions->flatten();
            foreach($dominions as $dominion)
            {
                $entombedBodies += $dominion->getBuildingPerkValue('crypt_bodies_decay_protection');
            }

            $bodiesToDecay -= $entombedBodies;
            $bodiesToDecay = max(0, $bodiesToDecay);

            $bodiesDecayed = max(1, round($realm->crypt * 0.01));
        }

        return $bodiesDecayed;
    }


}
