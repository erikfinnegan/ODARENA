<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\Resource;

use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Helpers\TitleHelper;

use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;

class TitleCalculator
{
    protected $militaryCalculator;
    protected $resourceCalculator;

    protected $theftHelper;
    protected $unitHelper;

    public function __construct()
    {
        $this->titleHelper = app(TitleHelper::class);
    }

    public function canChangeTitle(Dominion $dominion): bool
    {
        return ($dominion->history()->count() == 0);
    }

}
