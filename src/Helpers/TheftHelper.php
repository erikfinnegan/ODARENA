<?php

namespace OpenDominion\Helpers;

use Illuminate\Support\Collection;
use OpenDominion\Models\Resource;

class TheftHelper
{
    public function getMaxCarryPerSpyForResource(Resource $resource): float
    {
        # Default values
        switch ($resource->key)
        {
            case 'gold':
                return 32;

            case 'food':
                return 48;

            case 'lumber':
                return 24;

            case 'ore':
                return 24;

            case 'gems':
                return 16;

            case 'blood':
                return 24;

            case 'horse':
                return 0.02;

            case 'mana':
                return 0;

            default:
                return 0;
        }

    }


}
