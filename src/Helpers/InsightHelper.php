<?php

namespace OpenDominion\Helpers;
use DB;
use Illuminate\Support\Collection;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\DominionInsight;

class InsightHelper
{
    public function canBeArchived(Dominion $dominion): bool
    {
        return ($dominion->protection_ticks == 0 and $dominion->round->hasStarted() or !$dominion->getSpellPerkValue('fog_of_war'));
    }

    public function getArchiveCount(Dominion $dominion, Dominion $source): int
    {
          return DominionInsight::where('dominion_id',$dominion->id)->where(function($query) use($source)
          {
              $query->where('source_realm_id', $source->realm->id)
                    ->orWhere('source_realm_id', NULL);
          })
          ->count();
    }

}
