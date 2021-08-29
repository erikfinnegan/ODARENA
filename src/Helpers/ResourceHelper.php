<?php

namespace OpenDominion\Helpers;

use Illuminate\Support\Collection;
use OpenDominion\Models\Race;
use OpenDominion\Models\Resource;
use OpenDominion\Models\Dominion;

class ResourceHelper
{

    public function getResourcesByRace(Race $race): Collection
    {
        return $race->resources;
        
        $deities = collect(Resource::all()->keyBy('key')->sortBy('name')->where('enabled',1));

        foreach($deities as $deity)
        {
          if(
                (count($deity->excluded_races) > 0 and in_array($race->name, $deity->excluded_races)) or
                (count($deity->exclusive_races) > 0 and !in_array($race->name, $deity->exclusive_races))
            )
          {
              $deities->forget($deity->key);
          }
        }

        return $deities;
    }

    public function getExclusivityString(Deity $deity): string
    {

        $exclusivityString = '<br><small class="text-muted">';

        if($exclusives = count($deity->exclusive_races))
        {
            foreach($deity->exclusive_races as $raceName)
            {
                $exclusivityString .= $raceName;
                if($exclusives > 1)
                {
                    $exclusivityString .= ', ';
                }
                $exclusives--;
            }

            $exclusivityString .= ' only';
        }
        elseif($excludes = count($deity->excluded_races))
        {
            $exclusivityString .= 'All except ';
            foreach($deity->excluded_races as $raceName)
            {
                $exclusivityString .= $raceName;
                if($excludes > 1)
                {
                    $exclusivityString .= ', ';
                }
                $excludes--;
            }
        }

        $exclusivityString .= '</small>';

        return $exclusivityString;

    }

}
