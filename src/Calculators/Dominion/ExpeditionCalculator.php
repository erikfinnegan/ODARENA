<?php

namespace OpenDominion\Calculators\Dominion;

use DB;
use Illuminate\Support\Collection;

use OpenDominion\Helpers\LandHelper;
use OpenDominion\Helpers\UnitHelper;

use OpenDominion\Models\Dominion;

use OpenDominion\Calculators\Dominion\LandCalculator;

use OpenDominion\Services\Dominion\QueueService;

class ExpeditionCalculator
{

    protected $landHelper;
    protected $unitHelper;
    protected $landCalculator;
    protected $prestigeCalculator;
    protected $queueService;

    public function __construct(

          LandHelper $landHelper,
          UnitHelper $unitHelper,

          LandCalculator $landCalculator,
          PrestigeCalculator $prestigeCalculator,

          QueueService $queueService
        )
    {
        $this->landHelper = $landHelper;
        $this->unitHelper = app(UnitHelper::class);

        $this->landCalculator = $landCalculator;
        $this->prestigeCalculator = $prestigeCalculator;

        $this->queueService = $queueService;
    }

    public function getOpPerLand(Dominion $dominion): float
    {
        $land = $this->landCalculator->getTotalLand($dominion);
        return $land ** 1.25;
    }

    public function getLandDiscoveredAmount(Dominion $dominion, float $op): int
    {
        $land = $this->landCalculator->getTotalLand($dominion);

        return floor($op / $this->getOpPerLand($dominion));

        return $this->landCalculator->getTotalLand($dominion) / 256;
    }

    public function getLandDiscovered(Dominion $dominion, int $landDiscoveredAmount): array
    {
        $landDiscovered = [];
        $landSize = $this->landCalculator->getTotalLand($dominion);
        foreach($this->landHelper->getLandTypes() as $landType)
        {
            $ratio = $dominion->{'land_' . $landType} / $landSize;
            $landDiscovered['land_' . $landType] = (int)floor($landDiscoveredAmount * $ratio);
        }

        if(array_sum($landDiscovered) < $landDiscoveredAmount)
        {
            $landDiscovered['land_' . $dominion->race->home_land_type] += ($landDiscoveredAmount - array_sum($landDiscovered));
        }

        return $landDiscovered;

    }

}
