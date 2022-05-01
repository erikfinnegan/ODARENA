<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\Resource;
use OpenDominion\Models\Spyop;

use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Helpers\SabotageHelper;

use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;

class SabotageCalculator
{
    protected $militaryCalculator;
    protected $resourceCalculator;

    protected $sabotageHelper;
    protected $unitHelper;

    public function __construct(
          SabotageHelper $sabotageHelper,
          UnitHelper $unitHelper,

          MilitaryCalculator $militaryCalculator,
          ResourceCalculator $resourceCalculator
        )
    {
        $this->sabotageHelper = $sabotageHelper;
        $this->unitHelper = $unitHelper;

        $this->militaryCalculator = $militaryCalculator;
        $this->resourceCalculator = $resourceCalculator;
    }

    public function getSabotageDamage(Dominion $saboteur, Dominion $target, Spyop $spyop, string $attribute, array $units, bool $forCalculator = false): int
    {
        if($forCalculator and $target->getSpellPerkValue('fog_of_war'))
        {
            return 0;
        }

        $saboteurSpa = max($this->militaryCalculator->getSpyRatio($saboteur, 'offense'), 0.0001);
        $targetSpa = $this->militaryCalculator->getSpyRatio($target, 'defense');
        $spaSpaRatio = max(min((1-(($targetSpa / $saboteurSpa) * 0.5)),1),0);

        $sabotageAmount = min($resourceAvailableAmount, $maxAmountStolen * $spaSpaRatio);

        # But the target can decrease, which comes afterwards
        $targetModifier = 1;
        $targetModifier += $target->getSpellPerkMultiplier($resource->key . '_sabotage');
        $targetModifier += $target->getSpellPerkMultiplier('sabotage');
        $targetModifier += $target->getImprovementPerkMultiplier('sabotage');
        $targetModifier += $target->getBuildingPerkMultiplier('sabotage');

        $sabotageAmount *= $targetModifier;

        $sabotageAmount = max(0, $sabotageAmount);

        return $sabotageAmount;
    }

    public function getSabotageProtection(Dominion $target, string $resourceKey)
    {
        $sabotageProtection = 0;
        $sabotageProtection += $target->getBuildingPerkValue($resourceKey . '_sabotage_protection');

        // Unit sabotage protection
        for ($slot = 1; $slot <= 4; $slot++)
        {
            if($sabotageProtectionPerk = $target->race->getUnitPerkValueForUnitSlot($slot, 'protects_resource_from_sabotage'))
            {
                if($sabotageProtectionPerk[0] == $resourceKey)
                {
                    $sabotageProtection += $target->{'military_unit'.$slot} * $sabotageProtectionPerk[1];
                }
            }
        }

        $sabotageProtectionMultiplier = 1;
        $sabotageProtectionMultiplier += $target->getImprovementPerkMultiplier('sabotage_protection');

        return $sabotageProtection *= $sabotageProtectionMultiplier;
    }

    public function getUnitsKilled(Dominion $saboteur, Dominion $target, array $units): array
    {
        if(
              $saboteur->getSpellPerkValue('immortal_spies') or
              $saboteur->race->getPerkValue('immortal_spies') or
              $saboteur->realm->getArtefactPerkMultiplier('immortal_spies') or
              $target->race->getPerkValue('does_not_kill') or
              ($target->getSpellPerkValue('blind_to_reptilian_spies_on_sabotage') and $saboteur->race->name == 'Reptilians')
          )
        {
            foreach($units as $slot => $amount)
            {
                $killedUnits[$slot] = 0;
            }

            return $killedUnits;
        }

        $baseCasualties = 0.025; # 2.5%

        $saboteurSpa = $this->militaryCalculator->getSpyRatio($saboteur, 'offense');
        $targetSpa = $this->militaryCalculator->getSpyRatio($target, 'defense');
        $spaRatio = max($targetSpa / $saboteurSpa, 0.001);

        # If SPA/SPA is 0.25 or less, there is a random chance spies are immortal.
        if($spaRatio <= 0.25 and random_chance(1 / $spaRatio))
        {
            $baseCasualties = 0;
        }

        $baseCasualties *= (1 + $spaRatio);

        $casualties = $baseCasualties * $this->getSpyLossesReductionMultiplier($saboteur);

        #dd($saboteurSpa, $targetSpa, $spaRatio, $baseCasualties, $casualties);

        foreach($units as $slot => $amount)
        {
            $killedUnits[$slot] = (int)min(ceil($amount * $casualties), $units[$slot]);
        }

        return $killedUnits;
    }

    public function getSpyStrengthCost(Dominion $dominion, array $units): int
    {
        $cost = 0;

        $spyUnits = $this->militaryCalculator->getTotalUnitsForSlot($dominion, 'spies');
        foreach ($dominion->race->units as $unit)
        {
            if($this->unitHelper->isUnitOffensiveSpy($unit))
            {
                $spyUnits += $this->militaryCalculator->getTotalUnitsForSlot($dominion, $unit->slot);
            }
        }

        $cost = (int)ceil(array_sum($units) / $spyUnits * 100);

        return $cost;
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
