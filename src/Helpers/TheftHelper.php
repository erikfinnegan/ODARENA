<?php

namespace OpenDominion\Helpers;

use Illuminate\Support\Collection;
use OpenDominion\Models\Resource;

class TheftHelper
{
    public function getMaxCarryPerSpyForResource(Resource $resource)
    {
        # Default values
        switch ($resource->key)
        {
            case 'gold':
                return 16;

            case 'food':
                return 24;

            case 'lumber':
                return 12;

            case 'ore':
                return 12;

            case 'gems':
                return 8;

            case 'blood':
                return 12;

            case 'mana':
                return 0;

            default:
                return 0;
        }

    }


}
