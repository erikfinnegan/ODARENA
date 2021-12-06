<?php

namespace OpenDominion\Helpers;

use Illuminate\Support\Collection;
use OpenDominion\Models\Round;

class RoundHelper
{
    public function getRoundModeString(Round $round): string
    {
        switch ($round->mode)
        {
            case 'standard':
                return 'Standard';

            case 'deathmatch':
                return 'Deathmatch';

            case 'artefacts':
                return 'Artefacts';
        }
    }

    public function getRoundModeDescription(Round $round): string
    {
        switch ($round->mode)
        {
            case 'standard':
                return 'Your dominion is in a realm with friendly dominions fighting against all other realms to become the largest dominion.';

            case 'deathmatch':
                return 'Every dominion for itself!';

            case 'Artefacts':
                return 'Your dominion is in a realm with friendly dominions and your goal is to be the first realm to capture at least ten Artefacts.';
        }
    }

    public function getRoundModeIcon(Round $round): string
    {
        switch ($round->mode)
        {
            case 'standard':
                return '<i class="fas fa-users fa-fw text-green"></i>';

            case 'deathmatch':
                return '<i class="ra ra-daggers ra-fw text-red"></i>';

            default:
                return '&mdash;';
        }
    }

}
