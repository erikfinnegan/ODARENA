<?php

namespace OpenDominion\Calculators\Dominion;

use DB;
use Illuminate\Support\Collection;
use OpenDominion\Helpers\ResourceHelper;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\Resource;
use OpenDominion\Models\DominionResource;

use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\PopulationCalculator;

use OpenDominion\Services\Dominion\QueueService;

class ResourceCalculator
{

    public function __construct()
    {
        $this->resourceHelper = app(ResourceHelper::class);

        $this->landCalculator = app(LandCalculator::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->populationCalculator = app(PopulationCalculator::class);
    }

    public function dominionHasResource(Dominion $dominion, string $resourceKey): bool
    {
        $resource = Resource::where('key', $resourceKey)->first();
        return DominionResource::where('resource_id',$resource->id)->where('dominion_id',$dominion->id)->first() ? true : false;
    }

    public function getDominionResouces(Dominion $dominion): Collection
    {
        return DominionResource::where('dominion_id',$dominion->id)->get();
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


    public function getProduction(Dominion $dominion, string $resourceKey): int
    {
        if(!in_array($resourceKey, $dominion->race->resources))
        {
            return 0;
        }

        $production = 0;
        $production += $dominion->getBuildingPerkValue($resourceKey . '_production_raw');
        $production += $dominion->getSpellPerkValue($resourceKey . '_production_raw');
        $production += $dominion->getImprovementPerkValue($resourceKey . '_production_raw');
        $production += $dominion->getTechPerkValue($resourceKey . '_production_raw');
        $production += $dominion->getUnitPerkProductionBonus($resourceKey . '_production_raw');

        if(isset($dominion->race->peasants_production[$resourceKey]))
        {
            $productionPerPeasant = (float)$dominion->race->peasants_production[$resourceKey];

            if($dominion->race->getPerkValue('unemployed_peasants_produce'))
            {
                $production += $dominion->peasants * $productionPerPeasant;
            }
            else
            {
                $production += $this->populationCalculator->getPopulationEmployed($dominion) * $productionPerPeasant;
            }

        }

        $multiplier = 1;
        $multiplier += $dominion->getBuildingPerkMultiplier($resourceKey . '_production_mod');
        $multiplier += $dominion->getSpellPerkMultiplier($resourceKey . '_production_mod');
        $multiplier += $dominion->getImprovementPerkMultiplier($resourceKey . '_production_mod');
        $multiplier += $dominion->getTechPerkMultiplier($resourceKey . '_production_mod');
        $multiplier += $dominion->getDeityPerkMultiplier($resourceKey . '_production_mod');
        $multiplier += $dominion->title->getPerkMultiplier($resourceKey . '_production_mod');
        $multiplier += $dominion->race->getPerkMultiplier($resourceKey . '_production_mod');
        $multiplier += $dominion->getUnitPerkProductionBonusFromTitle($resourceKey);

        $production *= $multiplier;

        $production *= $this->militaryCalculator->getMoraleMultiplier($dominion);

        return max(0, $production);

    }

}
