<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\Resource;
use OpenDominion\Models\Spyop;

use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Helpers\SabotageHelper;

use OpenDominion\Calculators\Dominion\EspionageCalculator;
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

          EspionageCalculator $espionageCalculator,
          MilitaryCalculator $militaryCalculator,
          ResourceCalculator $resourceCalculator
        )
    {
        $this->sabotageHelper = $sabotageHelper;
        $this->unitHelper = $unitHelper;

        $this->espionageCalculator = $espionageCalculator;
        $this->militaryCalculator = $militaryCalculator;
        $this->resourceCalculator = $resourceCalculator;
    }

    public function getRatioMultiplier(Dominion $saboteur, Dominion $target, Spyop $spyop, string $attribute, array $units, bool $forCalculator = false): float
    {
        if($forCalculator and $target->getSpellPerkValue('fog_of_war'))
        {
            return 0;
        }

        $saboteurSpa = max($this->militaryCalculator->getSpyRatio($saboteur, 'offense'), 0.0001);
        $targetSpa = $this->militaryCalculator->getSpyRatio($target, 'defense');
        $spaSpaRatio = max(min((1-(($targetSpa / $saboteurSpa) * 0.5)),1),0);

        return $spaSpaRatio;
    }

    public function getTargetDamageMultiplier(Dominion $target, string $attribute): float
    {
        $multiplier = 1;

        $multiplier += $target->getBuildingPerkMultiplier('sabotage_damage_suffered');
        $multiplier += $target->getBuildingPerkMultiplier($attribute . '_sabotage_damage_suffered');

        $multiplier += $target->getImprovementPerkMultiplier('sabotage_damage_suffered');
        $multiplier += $target->getImprovementPerkMultiplier($attribute . '_sabotage_damage_suffered');

        $multiplier += $target->getSpellPerkMultiplier('sabotage_damage_suffered');
        $multiplier += $target->getSpellPerkMultiplier($attribute . '_sabotage_damage_suffered');

        $multiplier += $target->getTechPerkMultiplier('sabotage_damage_suffered');
        $multiplier += $target->getTechPerkMultiplier($attribute . '_sabotage_damage_suffered');

        $multiplier += $target->race->getPerkMultiplier('sabotage_damage_suffered');
        $multiplier += $target->race->getPerkMultiplier($attribute . '_sabotage_damage_suffered');

        $multiplier += $target->realm->getArtefactPerkMultiplier('sabotage_damage_suffered');
        $multiplier += $target->realm->getArtefactPerkMultiplier($attribute . '_sabotage_damage_suffered');

        $multiplier += $target->title->getPerkMultiplier('sabotage_damage_suffered');
        $multiplier += $target->title->getPerkMultiplier($attribute . '_sabotage_damage_suffered');

        return $multiplier;
    }

    public function getSaboteurDamageMultiplier(Dominion $saboteur, string $attribute): float
    {
        $multiplier = 1;

        $multiplier += $saboteur->getBuildingPerkMultiplier('sabotage_damage_dealt');
        $multiplier += $saboteur->getBuildingPerkMultiplier($attribute . '_sabotage_damage_dealt');

        $multiplier += $saboteur->getImprovementPerkMultiplier('sabotage_damage_dealt');
        $multiplier += $saboteur->getImprovementPerkMultiplier($attribute . '_sabotage_damage_dealt');

        $multiplier += $saboteur->getSpellPerkMultiplier('sabotage_damage_dealt');
        $multiplier += $saboteur->getSpellPerkMultiplier($attribute . '_sabotage_damage_dealt');

        $multiplier += $saboteur->getTechPerkMultiplier('sabotage_damage_dealt');
        $multiplier += $saboteur->getTechPerkMultiplier($attribute . '_sabotage_damage_dealt');

        $multiplier += $saboteur->race->getPerkMultiplier('sabotage_damage_dealt');
        $multiplier += $saboteur->race->getPerkMultiplier($attribute . '_sabotage_damage_dealt');

        $multiplier += $saboteur->realm->getArtefactPerkMultiplier('sabotage_damage_dealt');
        $multiplier += $saboteur->realm->getArtefactPerkMultiplier($attribute . '_sabotage_damage_dealt');

        $multiplier += $saboteur->title->getPerkMultiplier('sabotage_damage_dealt');
        $multiplier += $saboteur->title->getPerkMultiplier($attribute . '_sabotage_damage_dealt');

        return $multiplier;
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

        # If SPA/SPA is 0.33 or less, there is a random chance spies are immortal.
        if($spaRatio <= 0.33 and random_chance(1 / $spaRatio))
        {
            $baseCasualties = 0;
        }

        $baseCasualties *= (1 + $spaRatio);

        $casualties = $baseCasualties * $this->getSpyLossesReductionMultiplier($saboteur);

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

    public function canPerformSpyop(Dominion $dominion, Spyop $spyop): bool
    {

        if(
          # Must be available to the dominion's faction (race)
          !$this->espionageCalculator->isSpyopAvailableToDominion($dominion, $spyop)

          # Cannot cast disabled spells
          or $spyop->enabled !== 1

          # Espionage cannot be performed at all after offensive actions are disabled
          or $dominion->round->hasOffensiveActionsDisabled()

          # Round must have started
          or !$dominion->round->hasStarted()

          # Dominion must not be in protection
          or $dominion->isUnderProtection()
        )
        {
            return false;
        }

        return true;
    }

}
