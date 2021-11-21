<?php

namespace OpenDominion\Calculators\Dominion;

use DB;
use Illuminate\Support\Collection;

use OpenDominion\Helpers\LandHelper;
use OpenDominion\Helpers\UnitHelper;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\Resource;
use OpenDominion\Models\DominionResource;

use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\LandImprovementCalculator;
use OpenDominion\Calculators\Dominion\PrestigeCalculator;

use OpenDominion\Services\Dominion\QueueService;

class ResourceCalculator
{

    protected $landHelper;
    protected $unitHelper;
    protected $landCalculator;
    protected $landImprovementsCalculator;
    protected $prestigeCalculator;
    protected $queueService;

    public function __construct(

          LandHelper $landHelper,
          UnitHelper $unitHelper,

          LandCalculator $landCalculator,
          LandImprovementCalculator $landImprovementCalculator,
          PrestigeCalculator $prestigeCalculator,

          QueueService $queueService
        )
    {
        $this->landHelper = app(LandHelper::class);
        $this->unitHelper = app(UnitHelper::class);

        $this->landCalculator = $landCalculator;
        $this->landImprovementCalculator = $landImprovementCalculator;
        $this->prestigeCalculator = $prestigeCalculator;

        $this->queueService = $queueService;
    }

    public function dominionHasResource(Dominion $dominion, string $resourceKey): bool
    {
        $resource = Resource::where('key', $resourceKey)->first();
        return DominionResource::where('resource_id',$resource->id)->where('dominion_id',$dominion->id)->first() ? true : false;
    }

    public function getDominionResources(Dominion $dominion): Collection
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
    public function getAmount(Dominion $dominion, string $resourceKey): int
    {
        $resource = Resource::where('key', $resourceKey)->first();

        $dominionResourceAmount = DominionResource::where('dominion_id', $dominion->id)->where('resource_id', $resource->id)->first();

        if($dominionResourceAmount)
        {
            return $dominionResourceAmount->amount;
        }

        return 0;
    }

    public function getProduction(Dominion $dominion, string $resourceKey): int
    {
        if(!in_array($resourceKey, $dominion->race->resources) or $dominion->race->getPerkValue('no_' . $resourceKey . '_production') or $dominion->getSpellPerkValue('no_' . $resourceKey . '_production') or $dominion->isAbandoned())
        {
            return 0;
        }

        $production = 0;
        $production += $dominion->getBuildingPerkValue($resourceKey . '_production_raw');
        $production += $dominion->getBuildingPerkValue($resourceKey . '_production_depleting_raw');
        $production += $dominion->getBuildingPerkValue($resourceKey . '_production_increasing_raw');
        $production += $dominion->getSpellPerkValue($resourceKey . '_production_raw');
        $production += $dominion->getImprovementPerkValue($resourceKey . '_production_raw');
        $production += $dominion->getTechPerkValue($resourceKey . '_production_raw');
        $production += $dominion->getUnitPerkProductionBonus($resourceKey . '_production_raw');
        $production += $dominion->getLandImprovementPerkValue($resourceKey . '_production_raw');
        $production += $dominion->race->getPerkValue($resourceKey . '_production_raw');
        $production += $dominion->getUnitPerkProductionBonusFromTitle($resourceKey);

        if(isset($dominion->title))
        {
            $production += $dominion->title->getPerkValue($resourceKey . '_production_raw');
        }

        if(isset($dominion->race->peasants_production[$resourceKey]))
        {
            $productionPerPeasant = (float)$dominion->race->peasants_production[$resourceKey];

            if($dominion->race->getPerkValue('unemployed_peasants_produce'))
            {
                $production += $dominion->peasants * $productionPerPeasant;
            }
            else
            {
                $production += $this->getPopulationEmployed($dominion) * $productionPerPeasant;
            }
        }

        # Check for resource_conversion
        if($resourceConversionData = $dominion->getBuildingPerkValue('resource_conversion'))
        {
            $resourceConversionMultiplier = 1;
            $resourceConversionMultiplier += $dominion->getImprovementPerkMultiplier('resource_conversion');
            foreach($dominion->race->resources as $factionResourceKey)
            {
                if(
                      isset($resourceConversionData['from'][$factionResourceKey]) and
                      isset($resourceConversionData['to'][$resourceKey])
                  )
                {
                    $production += floor($resourceConversionData['to'][$resourceKey] * $resourceConversionMultiplier);
                }
            }
        }

        # Check for RESOURCE_production_raw_from_ANOTHER_RESOURCE
        foreach($dominion->race->resources as $sourceResourceKey)
        {
            $production += $dominion->getBuildingPerkValue($resourceKey . '_production_raw_from_' . $sourceResourceKey);
        }



        # Unit specific perks
        for ($slot = 1; $slot <= 4; $slot++)
        {
              # Get the $unit
              $unit = $dominion->race->units->filter(function ($unit) use ($slot) {
                      return ($unit->slot == $slot);
                  })->first();

              # Check for RESOURCE_production_raw_from_pairing
              if($productionFromPairingPerk = $addsMorale = $dominion->race->getUnitPerkValueForUnitSlot($slot, ($resourceKey . '_production_raw_from_pairing')))
              {
                  $slotPairedWith = (int)$productionFromPairingPerk[0];
                  $productionPerPair = (float)$productionFromPairingPerk[1];

                  $availablePairingUnits = $dominion->{'military_unit' . $slotPairedWith};
                  $availablePairingUnits += $this->queueService->getTrainingQueueTotalByResource($dominion, "military_unit{$slotPairedWith}");
                  $availablePairingUnits += $this->queueService->getInvasionQueueTotalByResource($dominion, "military_unit{$slotPairedWith}");
                  $availablePairingUnits += $this->queueService->getExpeditionQueueTotalByResource($dominion, "military_unit{$slotPairedWith}");
                  $availablePairingUnits += $this->queueService->getTheftQueueTotalByResource($dominion, "military_unit{$slotPairedWith}");

                  $availableProducingUnit = $dominion->{'military_unit' . $slot};

                  $extraProducingUnits = min($availableProducingUnit, $availablePairingUnits);

                  $production += $extraProducingUnits * $productionPerPair;
              }

              # Check for RESOURCE_production_raw_from_time
              if ($timePerkData = $dominion->race->getUnitPerkValueForUnitSlot($slot, ($resourceKey . '_production_raw_from_time')))
              {
                  $amountProduced = (float)$timePerkData[2];
                  $hourFrom = $timePerkData[0];
                  $hourTo = $timePerkData[1];

                  if (
                      (($hourFrom < $hourTo) and (now()->hour >= $hourFrom and now()->hour < $hourTo)) or
                      (($hourFrom > $hourTo) and (now()->hour >= $hourFrom or now()->hour < $hourTo))
                  )
                  {
                      $production += $dominion->{'military_unit' . $slot} * $amountProduced;
                  }
              }
        }

        // raw_mod perks
        $rawModPerks = 1;
        $rawModPerks += $dominion->getBuildingPerkMultiplier($resourceKey . '_production_raw_mod');
        $rawModPerks += $dominion->getSpellPerkMultiplier($resourceKey . '_production_raw_mod');
        $rawModPerks += $dominion->getImprovementPerkMultiplier($resourceKey . '_production_raw_mod');
        $rawModPerks += $dominion->getTechPerkMultiplier($resourceKey . '_production_raw_mod');

        $production *= $rawModPerks;

        $production *= $this->getProductionMultiplier($dominion, $resourceKey);

        return max(0, $production);
    }

    public function getProductionMultiplier(Dominion $dominion, string $resourceKey): float
    {
        $multiplier = 1;
        $multiplier += $dominion->getBuildingPerkMultiplier($resourceKey . '_production_mod');
        $multiplier += $dominion->getSpellPerkMultiplier($resourceKey . '_production_mod');
        $multiplier += $dominion->getImprovementPerkMultiplier($resourceKey . '_production_mod');
        $multiplier += $dominion->getTechPerkMultiplier($resourceKey . '_production_mod');
        $multiplier += $dominion->getDeityPerkMultiplier($resourceKey . '_production_mod');
        if(isset($dominion->title))
        {
            $multiplier += $dominion->title->getPerkMultiplier($resourceKey . '_production_mod') * $dominion->title->getPerkBonus($dominion);
        }
        $multiplier += $dominion->race->getPerkMultiplier($resourceKey . '_production_mod');

        # Production from Land Improvements
        $multiplier += $dominion->getLandImprovementPerkMultiplier($resourceKey . '_production_mod');

        # Add prestige
        if($resourceKey == 'food')
        {
            $multiplier *= 1 + $this->prestigeCalculator->getPrestigeMultiplier($dominion);
        }

        $multiplier *= (0.9 + $dominion->morale / 1000); # Can't use militaryCalculator->getMoraleMultiplier()

        return $multiplier;
    }

    public function getConsumption(Dominion $dominion, string $consumedResourceKey): int
    {
        if(!in_array($consumedResourceKey, $dominion->race->resources) or $dominion->race->getPerkValue('no_' . $consumedResourceKey . '_consumption') or $dominion->isAbandoned())
        {
            return 0;
        }

        $consumption = 0;
        $consumption += $dominion->getBuildingPerkValue($consumedResourceKey . '_upkeep_raw');
        $consumption += $dominion->getSpellPerkValue($consumedResourceKey . '_upkeep_raw');
        $consumption += $dominion->getImprovementPerkValue($consumedResourceKey . '_upkeep_raw');
        $consumption += $dominion->getTechPerkValue($consumedResourceKey . '_upkeep_raw');
        $consumption += $dominion->getUnitPerkProductionBonus($consumedResourceKey . '_upkeep_raw');

        # Check for resource_conversion
        if($resourceConversionData = $dominion->getBuildingPerkValue('resource_conversion'))
        {
            foreach($dominion->race->resources as $resourceKey)
            {
                if(
                      isset($resourceConversionData['from'][$consumedResourceKey]) and
                      isset($resourceConversionData['to'][$resourceKey])
                  )
                {
                    $consumption += $resourceConversionData['from'][$consumedResourceKey];
                }
            }
        }

        # Food consumption
        if($consumedResourceKey === 'food')
        {
            $nonConsumingUnitAttributes = [
                'ammunition',
                'equipment',
                'magical',
                'machine',
                'ship',
                'ethereal'
              ];

            $consumers = $dominion->peasants;

            # Check each Unit for does_not_count_as_population perk.
            for ($slot = 1; $slot <= 4; $slot++)
            {
                  # Get the $unit
                  $unit = $dominion->race->units->filter(function ($unit) use ($slot) {
                          return ($unit->slot == $slot);
                      })->first();

                  $amount = $dominion->{'military_unit'.$slot};

                  # Check for housing_count
                  if($nonStandardHousing = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'housing_count'))
                  {
                      $amount *= $nonStandardHousing;
                  }

                  # Get the unit attributes
                  $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

                  if (!$dominion->race->getUnitPerkValueForUnitSlot($slot, 'does_not_count_as_population') and !$dominion->race->getUnitPerkValueForUnitSlot($slot, 'does_not_consume_food') and count(array_intersect($nonConsumingUnitAttributes, $unitAttributes)) === 0)
                  {
                      $consumers += $dominion->{'military_unit'.$slot};
                      $consumers += $this->queueService->getTrainingQueueTotalByResource($dominion, "military_unit{$slot}");
                  }
            }

            $consumers += $dominion->military_draftees;
            $consumers += $dominion->military_spies;
            $consumers += $dominion->military_wizards;
            $consumers += $dominion->military_archmages;

            $consumption += $consumers * 0.25;

            // Unit Perk: food_consumption
            $extraFoodEaten = 0;
            for ($unitSlot = 1; $unitSlot <= 4; $unitSlot++)
            {
                if ($extraFoodEatenPerUnit = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'food_consumption_raw'))
                {
                    $extraFoodUnits = $dominion->{'military_unit'.$unitSlot};
                    $extraFoodEaten += intval($extraFoodUnits * $extraFoodEatenPerUnit);
                }
            }

            $consumption += $extraFoodEaten;
        }

        # Multipliers
        $multiplier = 1;
        $multiplier += $dominion->getBuildingPerkMultiplier($consumedResourceKey . '_consumption_mod');
        $multiplier += $dominion->getSpellPerkMultiplier($consumedResourceKey . '_consumption_mod');
        $multiplier += $dominion->getImprovementPerkMultiplier($consumedResourceKey . '_consumption_mod');
        $multiplier += $dominion->getTechPerkMultiplier($consumedResourceKey . '_consumption_mod');
        $multiplier += $dominion->getDeityPerkMultiplier($consumedResourceKey . '_consumption_mod');
        $multiplier += $dominion->race->getPerkMultiplier($consumedResourceKey . '_consumption_mod');

        if($consumedResourceKey == 'food')
        {
            #dd($multiplier, $dominion->getSpellPerkValue('food_consumption_mod'), $dominion->getSpellPerkMultiplier('population_growth'));
        }

        if(isset($dominion->title))
        {
            $multiplier += $dominion->title->getPerkMultiplier($consumedResourceKey . '_consumption_mod');
        }

        $consumption *= $multiplier;

        if($decayRate = $this->getDecay($dominion, $consumedResourceKey))
        {
            $consumption += $this->getAmount($dominion, $consumedResourceKey) * $decayRate;
        }



        return max(0, $consumption);

        #return min(max(0, $consumption), $this->getAmount($dominion, $consumedResourceKey));

    }

    public function getDecay(Dominion $dominion, string $consumedResourceKey): float
    {
        if(!in_array($consumedResourceKey, $dominion->race->resources) or $dominion->race->getPerkValue('no_' . $consumedResourceKey . '_consumption') or $dominion->isAbandoned())
        {
            return 0;
        }

        $decayRate = 0;
        $decayRate += $dominion->race->getPerkMultiplier($consumedResourceKey . '_decay');
        $decayRate += $dominion->getBuildingPerkValue($consumedResourceKey . '_decay');
        $decayRate += $dominion->getSpellPerkValue($consumedResourceKey . '_decay');
        $decayRate += $dominion->getImprovementPerkValue($consumedResourceKey . '_decay');
        $decayRate += $dominion->getTechPerkValue($consumedResourceKey . '_decay');
        $decayRate += $dominion->getUnitPerkProductionBonus($consumedResourceKey . '_decay');

        return $decayRate;
    }

    public function isOnBrinkOfStarvation(Dominion $dominion): bool
    {
        if(!$dominion->race->getPerkValue('no_food_consumption'))
        {
            return ($this->getAmount($dominion, 'food') + ($this->getProduction($dominion, 'food') - $this->getConsumption($dominion, 'food')) < 0);
        }

        return false;
    }

    #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #
    /*
    *   Copied in from PopulationCalculator because calling PopulationCalculator in this class breaks the app.
    */

    /**
     * Returns the Dominion's employment jobs.
     *
     * Each building (sans home and barracks) employs 20 peasants.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getEmploymentJobs(Dominion $dominion): int
    {

        $jobs = 0;

        $jobs += $dominion->getBuildingPerkValue('jobs');

        for ($slot = 1; $slot <= 4; $slot++)
        {
            if($dominion->race->getUnitPerkValueForUnitSlot($slot, 'provides_jobs') and $dominion->{'military_unit' . $slot} > 0)
            {
                $jobs += $dominion->{'military_unit' . $slot} * $dominion->race->getUnitPerkValueForUnitSlot($slot, 'provides_jobs');
            }
        }

        foreach ($this->landHelper->getLandTypes($dominion) as $landType)
        {
            $jobs += $this->landCalculator->getTotalBarrenLandByLandType($dominion, $landType) * ($dominion->race->getPerkValue('extra_barren_' . $landType . '_jobs'));
        }

        $multiplier = 1;
        $multiplier += $dominion->getTechPerkMultiplier('jobs_per_building');
        $multiplier += $dominion->getImprovementPerkMultiplier('jobs_per_building');

        $jobs *= $multiplier;

        return $jobs;
    }

    /**
     * Returns the Dominion's employed population.
     *
     * The employed population consists of the Dominion's peasant count, up to the number of max available jobs.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getPopulationEmployed(Dominion $dominion): int
    {
        return min($this->getEmploymentJobs($dominion), $dominion->peasants);
    }

    public function getExchangeRatePerkMultiplier(Dominion $dominion): float
    {
        $perk = 1;

        // Faction perk
        $perk += $dominion->race->getPerkMultiplier('exchange_bonus');

        // Techs
        $perk += $dominion->getTechPerkMultiplier('exchange_rate');

        // Spells
        $perk += $dominion->getSpellPerkMultiplier('exchange_rate');

        // Buildings
        $perk += $dominion->getBuildingPerkMultiplier('exchange_rate');

        // Improvements
        $perk += $dominion->getImprovementPerkMultiplier('exchange_rate');

        // Ruler Title: Merchant
        $perk += $dominion->title->getPerkMultiplier('exchange_rate') * $dominion->title->getPerkBonus($dominion);

        $perk = min($perk, 2);

        return $perk;
    }

}
