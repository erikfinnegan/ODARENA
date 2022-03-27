<?php

namespace OpenDominion\Calculators\Dominion;

use DB;
use Illuminate\Support\Collection;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Improvement;
use OpenDominion\Models\DominionImprovement;

class ImprovementCalculator
{

    /**
     * ImprovementCalculator constructor.
     *
     * @param LandCalculator $landCalculator
     */
    public function __construct()
    {
        $this->landCalculator = app(LandCalculator::class);
        $this->spellCalculator = app(SpellCalculator::class);
    }

    public function getResourceWorthRaw(string $resource, Dominion $dominion = null, int $population = 0): float
    {
        # Standard values;
        $worth = [
                    'gold' => 1,
                    'lumber' => 2,
                    'ore' => 2,
                    'gems' => 12,
                ];

        if($dominion)
        {
            $worth = $dominion->race->improvement_resources;
        }

        return $worth[$resource];

    }

    public function getResourceWorthMultipler(string $resourceKey, Dominion $dominion = null): float
    {
        if(!isset($dominion))
        {
            return 0;
        }
        else
        {
            $multiplier = 0;

            ## Extra imp points
            $multiplier += $dominion->race->getPerkMultiplier($resourceKey . '_improvement_points');

            # Techs
            if($resourceKey == 'gems' and $dominion->getTechPerkMultiplier('gemcutting'))
            {
                $multiplier += $dominion->getTechPerkMultiplier('gemcutting');
            }

            # Advancements
            $multiplier += $dominion->getTechPerkMultiplier('invest_bonus');
            $multiplier += $dominion->getTechPerkMultiplier($resourceKey . '_invest_bonus');

            # Spells
            $multiplier += $dominion->getSpellPerkMultiplier('invest_bonus');
            $multiplier += $dominion->getSpellPerkMultiplier($resourceKey . '_invest_bonus');

            # Buildings
            $multiplier += $dominion->getBuildingPerkMultiplier('improvement_points');
            $multiplier += $dominion->getBuildingPerkMultiplier($resourceKey . '_improvement_points');
            $multiplier += $dominion->getBuildingPerkMultiplier($resourceKey . '_invest_bonus');

            # Improvements
            $multiplier += $dominion->getImprovementPerkMultiplier('improvement_points');
            $multiplier += $dominion->getImprovementPerkMultiplier($resourceKey . '_improvement_points');

            # Deity
            $multiplier += $dominion->getDeityPerkMultiplier('improvement_points');
            $multiplier += $dominion->getDeityPerkMultiplier($resourceKey . '_improvement_points');

            # Artefacts
            $multiplier += $dominion->realm->getArtefactPerkMultiplier('improvement_points');
            $multiplier += $dominion->realm->getArtefactPerkMultiplier($resourceKey . '_improvement_points');

            # Faction
            $multiplier += $dominion->race->getPerkMultiplier('invest_bonus');

            # Title: improvements (Engineer)
            if(isset($dominion->title) and $dominion->title->getPerkMultiplier('improvements'))
            {
                $multiplier += $dominion->title->getPerkMultiplier('improvements') * $dominion->title->getPerkBonus($dominion);
            }

            # Check units
            for ($slot = 1; $slot <= 4; $slot++)
            {
                if($dominion->race->getUnitPerkValueForUnitSlot($slot, ($resourceKey . '_improvements')))
                {
                    $multiplier += ($dominion->{'military_unit'.$slot} / $this->landCalculator->getTotalLand($dominion)) / 25;
                }
            }

            return $multiplier;
        }
    }

    public function getResourceWorth(string $resource, Dominion $dominion = null): float
    {
        return $this->getResourceWorthRaw($resource, $dominion) * (1 + $this->getResourceWorthMultipler($resource, $dominion));
    }


   public function dominionHasImprovement(Dominion $dominion, string $improvementKey): bool
   {
       $improvement = Improvement::where('key', $improvementKey)->first();
       return DominionImprovement::where('improvement_id',$improvement->id)->where('dominion_id',$dominion->id)->first() ? true : false;
   }

    public function createOrIncrementImprovements(Dominion $dominion, array $improvements): void
    {
        foreach($improvements as $improvementKey => $amount)
        {
            if($amount > 0)
            {
                $improvement = Improvement::where('key', $improvementKey)->first();
                $amount = intval(max(0, $amount));

                if($this->dominionHasImprovement($dominion, $improvementKey))
                {
                    DB::transaction(function () use ($dominion, $improvement, $amount)
                    {
                        DominionImprovement::where('dominion_id', $dominion->id)->where('improvement_id', $improvement->id)
                        ->increment('invested', $amount);
                    });
                }
                else
                {
                    DB::transaction(function () use ($dominion, $improvement, $amount)
                    {
                        DominionImprovement::create([
                            'dominion_id' => $dominion->id,
                            'improvement_id' => $improvement->id,
                            'invested' => $amount
                        ]);
                    });
                }
            }
        }
    }

    public function decreaseImprovements(Dominion $dominion, array $improvements): void
    {
        foreach($improvements as $improvementKey => $amountToRemove)
        {
            if($amountToRemove > 0)
            {
                $improvement = Improvement::where('key', $improvementKey)->first();
                $amount = intval($amountToRemove);

                if($this->dominionHasImprovement($dominion, $improvementKey))
                {
                    DB::transaction(function () use ($dominion, $improvement, $amount)
                    {
                        DominionImprovement::where('dominion_id', $dominion->id)->where('improvement_id', $improvement->id)
                        ->decrement('invested', $amount);
                    });
                }
            }
        }
    }

    public function getDominionImprovements(Dominion $dominion): Collection
    {
        return DominionImprovement::where('dominion_id',$dominion->id)->get();
    }

    /*
    *   Returns an integer of how much this dominion has invested in this this improvement.
    */
    public function getDominionImprovementAmountInvested(Dominion $dominion, Improvement $improvement): int
    {

        $dominionImprovements = $this->getDominionImprovements($dominion);

        if($dominionImprovements->contains('improvement_id', $improvement->id))
        {
            return $dominionImprovements->where('improvement_id', $improvement->id)->first()->invested;
        }
        else
        {
            return 0;
        }
    }
    public function getDominionImprovementTotalAmountInvested(Dominion $dominion): int
    {
        $totalAmountInvested = 0;
        $dominionImprovements = $this->getDominionImprovements($dominion);

        foreach($dominionImprovements as $dominionImprovement)
        {
            $totalAmountInvested += $dominionImprovement->invested;
        }

        return $totalAmountInvested;
    }

}
