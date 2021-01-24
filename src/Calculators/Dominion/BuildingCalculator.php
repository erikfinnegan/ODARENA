<?php

namespace OpenDominion\Calculators\Dominion;

use DB;
use Illuminate\Support\Collection;
use OpenDominion\Helpers\BuildingHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Building;
use OpenDominion\Models\DominionBuilding;
use OpenDominion\Services\Dominion\QueueService;

class BuildingCalculator
{
    /** @var BuildingHelper */
    protected $buildingHelper;

    /** @var QueueService */
    protected $queueService;

    /**
     * BuildingCalculator constructor.
     *
     * @param BuildingHelper $buildingHelper
     * @param QueueService $queueService
     */
    public function __construct(BuildingHelper $buildingHelper, QueueService $queueService)
    {
        $this->buildingHelper = $buildingHelper;
        $this->queueService = $queueService;
    }

    /**
     * Returns the Dominion's total number of constructed buildings.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getTotalBuildings(Dominion $dominion): int
    {
        $totalBuildings = 0;

        foreach ($this->buildingHelper->getBuildingTypes($dominion) as $buildingType) {
            $totalBuildings += $dominion->{"building_{$buildingType}"};
        }

        return $totalBuildings;
    }

    public function getTotalBuildingsForLandType(Dominion $dominion, string $landType): int
    {
        $totalBuildings = 0;
        $buildingTypesForLandType = $this->buildingHelper->getBuildingTypesByRace($dominion)[$landType];

        foreach ($buildingTypesForLandType as $buildingType) {
            $totalBuildings += $dominion->{"building_{$buildingType}"};
        }

        return $totalBuildings;
    }

    public function getBuildingTypesToDestroy(Dominion $dominion, int $totalBuildingsToDestroy, string $landType): array
    {
        if($totalBuildingsToDestroy <= 0) {
            return [];
        }

        $buildingTypesForLandType = $this->buildingHelper->getBuildingTypesByRace($dominion)[$landType];

        $buildingsPerType = [];

        $totalBuildingsForLandType = 0;

        foreach($buildingTypesForLandType as $buildingType) {
            $resourceName = "building_{$buildingType}";
            $buildingsForType = $dominion->$resourceName;
            $totalBuildingsForLandType += $buildingsForType;

            $buildingsInQueueForType = $this->queueService->getConstructionQueueTotalByResource($dominion, $resourceName);
            $totalBuildingsForLandType += $buildingsInQueueForType;

            $buildingsPerType[$buildingType] = [
                'constructedBuildings' => $buildingsForType,
                'buildingsInQueue' => $buildingsInQueueForType];
        }

        uasort($buildingsPerType, function ($item1, $item2) {
            $item1Total = $item1['constructedBuildings'] + $item1['buildingsInQueue'];
            $item2Total = $item2['constructedBuildings'] + $item2['buildingsInQueue'];

            return $item2Total <=> $item1Total;
        });

        $buildingsToDestroyRatio = $totalBuildingsToDestroy / $totalBuildingsForLandType;

        $buildingsLeftToDestroy = $totalBuildingsToDestroy;
        $buildingsToDestroyByType = [];
        foreach($buildingsPerType as $buildingType => $buildings) {
            if($buildingsLeftToDestroy == 0) {
                break;
            }

            $constructedBuildings = $buildings['constructedBuildings'];
            $buildingsInQueue = $buildings['buildingsInQueue'];

            $totalBuildings = $constructedBuildings + $buildingsInQueue;
            $buildingsToDestroy = (int)ceil($totalBuildings * $buildingsToDestroyRatio);

            if($buildingsToDestroy <= 0) {
                continue;
            }

            if($buildingsToDestroy > $buildingsLeftToDestroy) {
                $buildingsToDestroy = $buildingsLeftToDestroy;
            }

            $buildingsToDestroyByType[$buildingType] = $buildingsToDestroy;

            $buildingsLeftToDestroy -= $buildingsToDestroy;
        }

        $actualTotalBuildingsDestroyed = 0;
        $buildingsDestroyedByType = [];
        foreach($buildingsToDestroyByType as $buildingType => $buildingsToDestroy) {
            $buildings = $buildingsPerType[$buildingType];
            $constructedBuildings = $buildings['constructedBuildings'];
            $buildingsInQueue = $buildings['buildingsInQueue'];

            $buildingsInQueueToDestroy = 0;
            if($buildingsInQueue <= $buildingsToDestroy) {
                $buildingsInQueueToDestroy = $buildingsInQueue;
            }
            else {
                $buildingsInQueueToDestroy = $buildingsToDestroy;
            }

            $constructedBuildingsToDestroy = $buildingsToDestroy - $buildingsInQueueToDestroy;

            $actualTotalBuildingsDestroyed += $buildingsToDestroy;

            $buildingsDestroyedByType[$buildingType] = [
                'builtBuildingsToDestroy' => $constructedBuildingsToDestroy,
                'buildingsInQueueToRemove' => $buildingsInQueueToDestroy];
        }

        return $buildingsDestroyedByType;
    }

    # BUILDINGS VERSION 2
    public function dominionHasBuilding(Dominion $dominion, string $buildingKey): bool
    {
        $building = Building::where('key', $buildingKey)->first();
        return DominionBuilding::where('building_id',$building->id)->where('dominion_id',$dominion->id)->first() ? true : false;
    }

    public function createOrIncrementBuildings(Dominion $dominion, array $buildingKeys): void
    {
        foreach($buildingKeys as $buildingKey => $amount)
        {
            if($amount > 0)
            {
                $building = Building::where('key', $buildingKey)->first();
                $amount = intval(max(0, $amount));

                if($this->dominionHasBuilding($dominion, $buildingKey))
                {
                    DB::transaction(function () use ($dominion, $building, $amount)
                    {
                        DominionBuilding::where('dominion_id', $dominion->id)->where('building_id', $building->id)
                        ->increment('owned', $amount);
                    });
                }
                else
                {
                    DB::transaction(function () use ($dominion, $building, $amount)
                    {
                        DominionBuilding::create([
                            'dominion_id' => $dominion->id,
                            'building_id' => $building->id,
                            'owned' => $amount
                        ]);
                    });
                }
            }
        }
    }

    public function removeBuildings(Dominion $dominion, array $buildingKeys): void
    {
        foreach($buildingKeys as $buildingKey => $amount)
        {
            if($amount > 0)
            {
                $building = Building::where('key', $buildingKey)->first();
                $amount = intval($amount);

                if($this->dominionHasBuilding($dominion, $buildingKey))
                {
                    DB::transaction(function () use ($dominion, $building, $amount)
                    {
                        DominionBuilding::where('dominion_id', $dominion->id)->where('building_id', $building->id)
                        ->decrement('owned', $amount);
                    });
                }
            }
        }
    }

    public function getDominionBuildings(Dominion $dominion): Collection
    {
        return DominionBuilding::where('dominion_id',$dominion->id)->get();
    }

}
