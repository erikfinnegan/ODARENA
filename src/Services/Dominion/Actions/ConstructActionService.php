<?php

namespace OpenDominion\Services\Dominion\Actions;

use DB;
use OpenDominion\Calculators\Dominion\Actions\ConstructionCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Helpers\BuildingHelper;
use OpenDominion\Helpers\LandHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Traits\DominionGuardsTrait;

class ConstructActionService
{
    use DominionGuardsTrait;

    /** @var BuildingHelper */
    protected $buildingHelper;

    /** @var ConstructionCalculator */
    protected $constructionCalculator;

    /** @var LandCalculator */
    protected $landCalculator;

    /** @var LandHelper */
    protected $landHelper;

    /** @var QueueService */
    protected $queueService;

    /**
     * ConstructionActionService constructor.
     */
    public function __construct()
    {
        $this->buildingHelper = app(BuildingHelper::class);
        $this->constructionCalculator = app(ConstructionCalculator::class);
        $this->landCalculator = app(LandCalculator::class);
        $this->landHelper = app(LandHelper::class);
        $this->queueService = app(QueueService::class);
    }

    /**
     * Does a construction action for a Dominion.
     *
     * @param Dominion $dominion
     * @param array $data
     * @return array
     * @throws GameException
     */
    public function construct(Dominion $dominion, array $data): array
    {
        $this->guardLockedDominion($dominion);

        $data = array_only($data, array_map(function ($value) {
            return "building_{$value}";
        }, $this->buildingHelper->getBuildingTypes($dominion)));

        $data = array_map('\intval', $data);

        $totalBuildingsToConstruct = array_sum($data);

        if ($totalBuildingsToConstruct <= 0)
        {
            throw new GameException('Construction was not started due to bad input.');
        }

        if ($dominion->race->getPerkValue('cannot_construct') or $dominion->race->getPerkValue('cannot_build'))
        {
            throw new GameException('Your faction is unable to construct buildings.');
        }

        if ($totalBuildingsToConstruct > $this->constructionCalculator->getMaxAfford($dominion))
        {
            throw new GameException('You do not have enough resources to construct ' . number_format($totalBuildingsToConstruct) . '  buildings.');
        }

        $buildingsByLandType = [];

        foreach ($data as $buildingType => $amount)
        {
            if ($amount === 0) {
                continue;
            }

            if ($amount < 0) {
                throw new GameException('Construction was not completed due to bad input.');
            }

            $landType = $this->landHelper->getLandTypeForBuildingByRace(
                str_replace('building_', '', $buildingType),
                $dominion->race
            );

            if (!isset($buildingsByLandType[$landType])) {
                $buildingsByLandType[$landType] = 0;
            }

            $buildingsByLandType[$landType] += $amount;
        }

        # Get construction materials
        $constructionMaterials = $this->constructionCalculator->getConstructionMaterials($dominion);

        $primaryResource = null;
        $secondaryResource = null;

        if(isset($constructionMaterials[0]))
        {
            $primaryResource = $constructionMaterials[0];
        }
        if(isset($constructionMaterials[1]))
        {
            $secondaryResource = $constructionMaterials[1];
        }

        # Calculate total cost per primary and secondary
        $primaryCost = $this->constructionCalculator->getConstructionCostPrimary($dominion) * $totalBuildingsToConstruct;
        $secondaryCost = $this->constructionCalculator->getConstructionCostSecondary($dominion) * $totalBuildingsToConstruct;

        foreach ($buildingsByLandType as $landType => $amount)
        {
            if ($amount > $this->landCalculator->getTotalBarrenLandByLandType($dominion, $landType))
            {
                throw new GameException("You do not have enough barren land to construct {$totalBuildingsToConstruct} buildings.");
            }
        }

        # Look for forest_construction_cost
        foreach ($buildingsByLandType as $landType => $amount)
        {
            if($forestConstructionCostPerk = $dominion->race->getPerkMultiplier('forest_construction_cost') and $landType == 'forest')
            {
                $primaryCost *= $forestConstructionCostPerk;
                $secondaryCost *= $forestConstructionCostPerk;
            }
        }

        DB::transaction(function () use ($dominion, $data, $primaryCost, $secondaryCost, $primaryResource, $secondaryResource, $totalBuildingsToConstruct) {
            $ticks = 12;
            # Gnome: increased construction speed
            if($dominion->race->getPerkValue('increased_construction_speed'))
            {
              $ticks = 12 - $dominion->race->getPerkValue('increased_construction_speed');
            }

            $this->queueService->queueResources('construction', $dominion, $data, $ticks);


            $dominion->{'resource_'.$primaryResource} -= $primaryCost;
            $dominion->{'stat_total_' . $primaryResource . '_spent_building'} += $primaryCost;

            if(isset($secondaryResource))
            {
                $dominion->{'resource_'.$secondaryResource} -= $secondaryCost;
                $dominion->{'stat_total_' . $secondaryResource . '_spent_building'} += $secondaryCost;
            }

            $dominion->save(['event' => HistoryService::EVENT_ACTION_CONSTRUCT]);
/*
            $dominion->fill([
                {'resource_'.$primaryResource} => ($dominion->{'resource_'.$primaryResource} - $primaryCost),
                {'resource_'.$secondaryResource} => ($dominion->{'resource_'.$secondaryResource} - $secondaryCost),

                {'stat_total_' . $primaryResource . '_spent_building'} => ($dominion->{'stat_total_' . $primaryResource . '_spent_building'} + $primaryCost),
                {'stat_total_' . $secondaryResource . '_spent_building'} => ($dominion->{'stat_total_' . $secondaryResource . '_spent_building'} + $secondaryCost),


            ])->save(['event' => HistoryService::EVENT_ACTION_CONSTRUCT]);
*/
        });

        if(isset($secondaryResource))
        {
            $return = [
                'message' => sprintf(
                    'Construction started at a cost of %s %s and %s %s.',
                    number_format($primaryCost),
                    $primaryResource,
                    number_format($secondaryCost),
                    $secondaryResource
                ),
                'data' => [
                    'primaryCost' => $primaryCost,
                    'secondaryCost' => $secondaryCost,
                ]
            ];
        }
        else
        {
            $return = [
                'message' => sprintf(
                    'Construction started at a cost of %s %s.',
                    number_format($primaryCost),
                    $primaryResource
                ),
                'data' => [
                    'primaryCost' => $primaryCost
                ]
            ];
        }

        return $return;
    }
}
