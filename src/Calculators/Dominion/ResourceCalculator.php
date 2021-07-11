<?php

namespace OpenDominion\Calculators\Dominion;

use DB;
use Illuminate\Support\Collection;
use OpenDominion\Helpers\BuildingHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Resource;
use OpenDominion\Models\DominionResource;
use OpenDominion\Services\Dominion\QueueService;

class BuildingCalculator
{
    /** @var BuildingHelper */
    protected $resourceHelper;

    /** @var QueueService */
    protected $queueService;

    /**
     * BuildingCalculator constructor.
     *
     * @param BuildingHelper $resourceHelper
     * @param QueueService $queueService
     */
    public function __construct(BuildingHelper $resourceHelper, QueueService $queueService)
    {
        $this->resourceHelper = $resourceHelper;
        $this->queueService = $queueService;
    }

    public function dominionHasResource(Dominion $dominion, string $resourceKey): bool
    {
        $resource = Resource::where('key', $resourceKey)->first();
        return DominionResource::where('resource_id',$resource->id)->where('dominion_id',$dominion->id)->first() ? true : false;
    }

    public function createOrIncrementResources(Dominion $dominion, array $resourceKeys): void
    {
        foreach($resourceKeys as $resourceKey => $amount)
        {
            if($amount > 0)
            {
                $resource = Resource::where('key', $resourceKey)->first();
                $amount = intval(max(0, $amount));

                if($this->dominionHasBuilding($dominion, $resourceKey))
                {
                    DB::transaction(function () use ($dominion, $resource, $amount)
                    {
                        DominionResource::where('dominion_id', $dominion->id)->where('resource_id', $resource->id)
                        ->increment('owned', $amount);
                    });
                }
                else
                {
                    DB::transaction(function () use ($dominion, $resource, $amount)
                    {
                        DominionResource::create([
                            'dominion_id' => $dominion->id,
                            'resource_id' => $resource->id,
                            'owned' => $amount
                        ]);
                    });
                }
            }
        }
    }

    public function removeResources(Dominion $dominion, array $resourceKeys): void
    {
        foreach($resourceKeys as $resourceKey => $amountToRemove)
        {
            $resource = Resource::where('key', $resourceKey)->first();
            $owned = $this->getResourceAmountOwned($dominion, $resource);

            $amountToRemove = min($amountToRemove, $owned);

            if($this->dominionHasBuilding($dominion, $resourceKey))
            {
                # Are we destroying some or all?

                # Some...
                if($amountToDestroy < $owned)
                {
                    DB::transaction(function () use ($dominion, $resource, $amountToRemove)
                    {
                        DominionResource::where('dominion_id', $dominion->id)->where('resource_id', $resource->id)
                        ->decrement('owned', $amountToDestroy);
                    });
                }
                # All
                elseif($amountToDestroy == $owned)
                {
                    DB::transaction(function () use ($dominion, $resource, $amountToRemove)
                    {
                        DominionResource::where('dominion_id', $dominion->id)->where('resource_id', $resource->id)
                        ->delete();
                    });
                }
                else
                {
                    // Do nothing.
                }
            }
        }
    }

    public function getDominionResouces(Dominion $dominion): Collection
    {
        return = DominionResource::where('dominion_id',$dominion->id)->get();
    }

    /*
    *   Returns an integer ($owned) of how many of this building the dominion has.
    *   Three arguments are permitted and evaluated in order:
    *   Building $resource - if we pass a Building object
    *   string $resourceKey - if we pass a building key
    *   int $resourceId - if we pass a building ID
    *
    */
    public function getResourceAmountOwned(Dominion $dominion, Resource $resource): int
    {
        $owned = 0;

        $dominionResources = $this->getDominionBuildings($dominion);

        if($dominionResources->contains('resource_id', $resource->id))
        {
            return $dominionResources->where('resource_id', $resource->id)->first()->owned;
        }
        else
        {
            return 0;
        }
    }

}
