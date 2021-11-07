<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\Resource;

use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Helpers\TheftHelper;

use OpenDominion\Calculators\Dominion\CasualtiesCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;

class TheftCalculator
{

    protected $casualtiesCalculator;
    protected $militaryCalculator;
    protected $resourceCalculator;

    protected $theftHelper;
    protected $unitHelper;

    public function __construct(
          TheftHelper $theftHelper,
          UnitHelper $unitHelper,

          CasualtiesCalculator $casualtiesCalculator,
          MilitaryCalculator $militaryCalculator,
          ResourceCalculator $resourceCalculator
        )
    {
        $this->theftHelper = $theftHelper;
        $this->unitHelper = $unitHelper;

        $this->casualtiesCalculator = $casualtiesCalculator;
        $this->militaryCalculator = $militaryCalculator;
        $this->resourceCalculator = $resourceCalculator;
    }

    public function getTheftAmount(Dominion $thief, Dominion $target, Resource $resource, array $units): int
    {
        $resourceAvailableAmount = $this->resourceCalculator->getAmount($target, $resource->key);

        // Unit theft protection
        for ($slot = 1; $slot <= 4; $slot++)
        {
            if($theftProtection = $target->race->getUnitPerkValueForUnitSlot($slot, 'protects_resource_from_theft'))
            {
                if($theftProtection[0] == $resource->key)
                {
                    $resourceAvailableAmount -= $target->{'military_unit'.$slot} * $theftProtection[1];
                }
            }
        }

        $resourceAvailableAmount = max(0, $resourceAvailableAmount);
        $maxPerSpy = $this->getMaxCarryPerSpyForResource($thief, $resource);

        $thiefSpa = max($this->militaryCalculator->getSpyRatio($thief, 'offense'), 0.01);
        $targetSpa = $this->militaryCalculator->getSpyRatio($target, 'defense');
        $spaRatio = $targetSpa / $thiefSpa;
        $invertedSpaRatio = min(1, 1/$spaRatio);

        $theftAmount = min($resourceAvailableAmount, array_sum($units) * $maxPerSpy * $invertedSpaRatio);

        # But the target can decrease, which comes afterwards
        $targetModifier = 0;
        $targetModifier += $target->getSpellPerkMultiplier($resource->key . '_theft');
        $targetModifier += $target->getSpellPerkMultiplier('all_theft');
        $targetModifier += $target->getBuildingPerkMultiplier($resource->key . '_theft_reduction');

        $theftAmount *= (1 + $targetModifier);

        $theftAmount = max(0, $theftAmount);

        return $theftAmount;
    }

    public function getMaxCarryPerSpyForResource(Dominion $thief, Resource $resource)
    {
        $max = $this->theftHelper->getMaxCarryPerSpyForResource($resource);

        # The stealer can increase
        $thiefModifier = 1;
        $thiefModifier += $thief->getTechPerkMultiplier('amount_stolen');
        $thiefModifier += $thief->getDeityPerkMultiplier('amount_stolen');
        $thiefModifier += $thief->getImprovementPerkMultiplier('amount_stolen');
        $thiefModifier += $thief->race->getPerkMultiplier('amount_stolen');

        $thiefModifier += $thief->getTechPerkMultiplier($resource->key . '_amount_stolen');
        $thiefModifier += $thief->getDeityPerkMultiplier($resource->key . '_amount_stolen');
        $thiefModifier += $thief->getImprovementPerkMultiplier($resource->key . '_amount_stolen');

        return $max * $thiefModifier;
    }

    public function getUnitsKilled(Dominion $thief, Dominion $target, array $units): array
    {
        if($thief->getSpellPerkValue('immortal_spies') or $thief->race->getPerkValue('immortal_spies'))
        {
            return [];
        }

        $baseCasualties = 0.01; # 1%

        $thiefSpa = $this->militaryCalculator->getSpyRatio($thief, 'offense');
        $targetSpa = $this->militaryCalculator->getSpyRatio($target, 'defense');
        $spaRatio = $targetSpa / $thiefSpa;

        # If SPA/SPA is 0.25 or less, there is a random chance spies are immortal.
        if($spaRatio <= 0.25 and random_chance(1 / $spaRatio))
        {
            $baseCasualties = 0;
        }

        $baseCasualties *= (1 + $spaRatio);

        $casualties = $baseCasualties * $this->getSpyLossesReductionMultiplier($thief);

        #dd($thiefSpa, $targetSpa, $spaRatio, $baseCasualties, $casualties);

        foreach($units as $slot => $amount)
        {
            $killedUnits[$slot] = (int)min(ceil($amount * $casualties), $units[$slot]);
        }

        return $killedUnits;
    }


    protected function getSpyLossesReductionMultiplier(Dominion $dominion): float
    {
        $spiesKilledMultiplier = 1;

        // Buildings
        $spiesKilledMultiplier -= $dominion->getBuildingPerkMultiplier('spy_losses');

        # Techs
        $spiesKilledMultiplier += $dominion->getTechPerkMultiplier('spy_losses');

        // Improvements
        $spiesKilledMultiplier += $dominion->getImprovementPerkMultiplier('spy_losses');

        # Cap at 10% losses (-90%)
        $spiesKilledMultiplier = max(0.10, $spiesKilledMultiplier);

        return $spiesKilledMultiplier;
    }

}
