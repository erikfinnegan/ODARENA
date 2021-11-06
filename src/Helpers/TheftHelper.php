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
                return 128;

            case 'food':
                return 192;

            case 'lumber':
                return 96;

            case 'ore':
                return 96;

            case 'gems':
                return 64;

            case 'blood':
                return 96;

            case 'horse':
                return 0.1;

            case 'mana':
                return 0;

            default:
                return 0;
        }

    }


}
