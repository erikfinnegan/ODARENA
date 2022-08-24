<?php

namespace OpenDominion\Helpers;

use DB;
use Illuminate\Support\Collection;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Round;

use OpenDominion\Calculators\Dominion\LandCalculator;

use OpenDominion\Services\Dominion\StatsService;

class ConversionHelper
{
    public function __construct()
    {
        #
    }

    public function getUnconvertibleAttributes(bool $isPsionic = false): array
    {
        
        $unconvertibleAttributes = [
            'ammunition',
            'aspect',
            'equipment',
            'fused',
            'immobile',
            'magical',
            'massive',
            'machine',
            'ship'
          ];

        if($isPsionic)
        {
            unset($unconvertibleAttributes['aspect']);
            unset($unconvertibleAttributes['fused']);
            $unconvertibleAttributes[] = 'mindless';
            $unconvertibleAttributes[] = 'wise';
        }

        return $unconvertibleAttributes;
    }
    
    public function getUnconvertiblePerks(bool $isPsionic = false): array
    {
        $unconvertiblePerks = [
            'fixed_casualties',
            'dies_into',
            'dies_into_spy',
            'dies_into_wizard',
            'dies_into_archmage',
            'dies_into_multiple',
            'dies_into_resource',
            'dies_into_resources',
            'dies_into_multiple_on_offense',
            'dies_into_on_offense',
            'dies_into_multiple_on_victory'
          ];

        return $unconvertiblePerks;
    }

}
