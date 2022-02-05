<?php

namespace OpenDominion\Helpers;

use DB;
use OpenDominion\Models\Stat;

use OpenDominion\Services\Dominion\StatsService;

class StatsHelper
{
    public function getStatName(string $statKey)
    {
        return Stat::where('key', $statKey)->firstOrFail()->name;
    }
}
