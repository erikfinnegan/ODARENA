<?php

namespace OpenDominion\Services\Dominion;

use DB;
use Carbon\Carbon;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\DominionResource;
use OpenDominion\Models\Resource;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Helpers\ResourceHelper;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Models\GameEvent;

class ResourceService
{
    public function __construct()
    {
        $this->resourceHelper = app(ResourceHelper::class);
        $this->resourceCalculator = app(ResourceCalculator::class);
        $this->queueService = app(QueueService::class);
    }

    public function createOrIncrementResources(Dominion $dominion, array $resourceKeys): void
    {
        foreach($resourceKeys as $resourceKey => $amount)
        {
            if($amount > 0)
            {
                $resource = Resource::where('key', $resourceKey)->first();
                $amount = intval(max(0, $amount));

                if($this->resourceCalculator->dominionHasResource($dominion, $resourceKey))
                {
                    DB::transaction(function () use ($dominion, $resource, $amount)
                    {
                        DominionResource::where('dominion_id', $dominion->id)->where('resource_id', $resource->id)
                        ->increment('amount', $amount);
                    });
                }
                else
                {
                    DB::transaction(function () use ($dominion, $resource, $amount)
                    {
                        DominionResource::create([
                            'dominion_id' => $dominion->id,
                            'resource_id' => $resource->id,
                            'amount' => $amount
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
            $owned = $this->resourceCalculator->getResourceAmountOwned($dominion, $resource);

            $amountToRemove = min($amountToRemove, $owned);

            if($this->resourceCalculator->dominionHasResource($dominion, $resourceKey))
            {
                # Are we removing some or all?

                # Some...
                if($amountToRemove < $owned)
                {
                    DB::transaction(function () use ($dominion, $resource, $amountToRemove)
                    {
                        DominionResource::where('dominion_id', $dominion->id)->where('resource_id', $resource->id)
                        ->decrement('owned', $amountToRemove);
                    });
                }
                # All
                elseif($amountToRemove == $owned)
                {
                    DB::transaction(function () use ($dominion, $resource)
                    {
                        DominionResource::where('dominion_id', $dominion->id)->where('resource_id', $resource->id)
                        ->delete();
                    });
                }
            }
        }
    }

}
