<?php

namespace OpenDominion\Calculators;


use DB;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Realm;
use OpenDominion\Calculators\Dominion\ProductionCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;

class RealmCalculator
{

    /** @var ProductionCalculator */
    protected $productionCalculator;

    /** @var SpellCalculator */
    protected $spellCalculator;

    /**
     * RealmCalculator constructor.
     *
     * @param ProductionCalculator $productionCalculator
     * @param SpellCalculator $productionCalculator
     */
    public function __construct(
        ProductionCalculator $productionCalculator,
        SpellCalculator $spellCalculator
    )
    {
        $this->productionCalculator = $productionCalculator;
        $this->spellCalculator = $spellCalculator;
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
     * For each resource, calculate how much is contributed to the monster in total.
     *
     * @param Realm $realm
     * @return int
     */
    public function getTotalContributions(Realm $realm): array
    {
        $contributions = [
            'food' => 0,
            'lumber' => 0,
            'ore' => 0
          ];

        if($this->hasMonster($realm))
        {
            $dominions = $realm->dominions->flatten();

            foreach($contributions as $resource => $amount)
            {
                foreach($dominions as $dominion)
                {
                    #echo '<p>' . $dominion->name . ' contributes ' . $this->productionCalculator->getContribution($dominion, $resource) . ' ' . $resource . '</p>';
                    $contributions[$resource] += $this->productionCalculator->getContribution($dominion, $resource);
                }
            }
        }
        return $contributions;
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
        if($realm->alignment !== 'evil' or $realm->crypt === 0)
        {
            return $bodiesDecayed;
        }
        else
        {
            $bodiesDecayed = max(1, round($realm->crypt * 0.01));
        }

        return $bodiesDecayed;
    }

    /**
     * Calculate the ratio of crypt bodies available to the $dominion.
     *
     * @param Realm $realm
     * @return int
     */
    public function getCryptBodiesProportion($dominion): float
    {

        if ($this->spellCalculator->isSpellActive($dominion, 'dark_rites'))
        {
          $dominionDarkRiteUnits = $dominion->military_unit3 + $dominion->military_unit4;
          $totaldarkRiteUnits = max(1,$dominion->military_unit3 + $dominion->military_unit4);

          $undeads = DB::table('dominions')
                       ->join('races', 'dominions.race_id', '=', 'races.id')
                       ->join('realms', 'realms.id', '=', 'dominions.realm_id')
                       ->select('dominions.id')
                       ->where('dominions.round_id', '=', $dominion->realm->round->id)
                       ->where('realms.id', '=', $dominion->realm->id)
                       ->where('races.name', '=', 'Undead')
                       ->where('dominions.protection_ticks', '=', 0)
                       ->where('dominions.is_locked', '=', 0)
                       ->pluck('dominions.id');

          foreach($undeads as $undead)
          {
              $undeadDominion = Dominion::findOrFail($undead);
              if ($this->spellCalculator->isSpellActive($undeadDominion, 'dark_rites'))
              {
                  $totaldarkRiteUnits += $undeadDominion->military_unit3 + $undeadDominion->military_unit4;
              }
          }

          return $dominionDarkRiteUnits / $totaldarkRiteUnits;

        }
        else
        {
            return 0.0;
        }

    }

}
