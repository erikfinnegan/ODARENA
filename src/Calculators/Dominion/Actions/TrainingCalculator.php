<?php

namespace OpenDominion\Calculators\Dominion\Actions;

use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Building;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Calculators\Dominion\BuildingCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Services\Dominion\ResourceService;
use OpenDominion\Calculators\Dominion\SpellCalculator;
use OpenDominion\Helpers\RaceHelper;
use OpenDominion\Helpers\ResourceHelper;
use OpenDominion\Calculators\Dominion\PopulationCalculator;

class TrainingCalculator
{
    /** @var LandCalculator */
    protected $landCalculator;

    /** @var UnitHelper */
    protected $unitHelper;

    /** @var ImprovementCalculator */
    protected $improvementCalculator;

    /** @var MilitaryCalculator */
    protected $militaryCalculator;

    /** @var QueueService */
    protected $queueService;

    /** @var SpellCalculator */
    protected $spellCalculator;

    /** @var RaceHelper */
    protected $raceHelper;

    /** @var PopulationCalculator */
    protected $populationCalculator;

    /**
     * TrainingCalculator constructor.
     *
     * @param LandCalculator $landCalculator
     * @param UnitHelper $unitHelper
     */
    public function __construct()
    {
        $this->landCalculator = app(LandCalculator::class);
        $this->unitHelper = app(UnitHelper::class);
        $this->improvementCalculator = app(ImprovementCalculator::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->queueService = app(QueueService::class);
        $this->spellCalculator = app(SpellCalculator::class);
        $this->raceHelper = app(RaceHelper::class);
        $this->resourceHelper = app(ResourceHelper::class);
        $this->populationCalculator = app(PopulationCalculator::class);
        $this->buildingCalculator = app(BuildingCalculator::class);
        $this->resourceCalculator = app(ResourceCalculator::class);
    }

    /**
     * Returns the Dominion's training costs per unit.
     *
     * @param Dominion $dominion
     * @return array
     */
    public function getTrainingCostsPerUnit(Dominion $dominion): array
    {
        $costsPerUnit = [];

        $spyCostMultiplier = $this->getSpyCostMultiplier($dominion);
        $wizardCostMultiplier = $this->getWizardCostMultiplier($dominion);

        // Values
        $units = $dominion->race->units;

        $spyCost = $this->raceHelper->getSpyCost($dominion->race);
        $wizardCost = $this->raceHelper->getWizardCost($dominion->race);
        $archmageCost = $this->raceHelper->getArchmageCost($dominion->race);

        $spyCost['trainedFrom'] = 'draftees';
        $wizardCost['trainedFrom'] = 'draftees';

        # Generally, do not mess with this one.
        $archmageCost['trainedFrom'] = 'wizards';

        foreach ($this->unitHelper->getUnitTypes() as $unitType) {
            $cost = [];

            switch ($unitType) {
                case 'spies':
                    $cost[$spyCost['trainedFrom']] = 1;
                    $cost[$spyCost['resource']] = round($spyCost['amount'] * $spyCostMultiplier);
                    break;

                case 'wizards':
                    $cost[$spyCost['trainedFrom']] = 1;
                    $cost[$wizardCost['resource']] = round($wizardCost['amount'] * $wizardCostMultiplier);
                    break;

                case 'archmages':
                    $cost[$archmageCost['trainedFrom']] = 1;
                    $cost[$archmageCost['resource']] = round($archmageCost['amount'] * $wizardCostMultiplier);
                    break;

                default:
                    $unitSlot = (((int)str_replace('unit', '', $unitType)) - 1);

                    $slot = $unitSlot+1;
                    $unit = $dominion->race->units->filter(function ($unit) use ($slot) {
                        return ($unit->slot === $slot);
                    })->first();

                    foreach($unit->cost as $costResourceKey => $amount)
                    {
                        $cost[$costResourceKey] = ceil($amount * $this->getSpecialistEliteCostMultiplier($dominion, $costResourceKey));
                    }

                    if($dominion->race->getUnitPerkValueForUnitSlot(/*intval(str_replace('unit','',$unitType))*/$slot, 'no_draftee') == 1)
                    {
                        $cost['draftees'] = 0;
                    }
                    # Check for housing_count
                    elseif($nonStandardHousing = $dominion->race->getUnitPerkValueForUnitSlot(intval(str_replace('unit','',$unitType)), 'housing_count'))
                    {
                        $cost['draftees'] = min($nonStandardHousing, 1);
                    }
                    else
                    {
                        $cost['draftees'] = 1;
                    }

                    break;
            }

            $costsPerUnit[$unitType] = $cost;
        }

        if($dominion->race->getPerkValue('cannot_train_spies'))
        {
            unset($costsPerUnit['spies']);
        }

        if($dominion->race->getPerkValue('cannot_train_wizards'))
        {
            unset($costsPerUnit['wizards']);
        }

        if($dominion->race->getPerkValue('cannot_train_archmages'))
        {
            unset($costsPerUnit['archmages']);
        }

        return $costsPerUnit;
    }

    /**
     * Returns the Dominion's max military trainable population.
     *
     * @param Dominion $dominion
     * @return array
     */
    public function getMaxTrainable(Dominion $dominion): array
    {
        $trainable = [];

        $costsPerUnit = $this->getTrainingCostsPerUnit($dominion);

        foreach ($costsPerUnit as $unitType => $costs)
        {
            $trainableByCost = [];

            unset($costs['morale']);

            foreach ($costs as $type => $value)
            {
                if($value != 0)
                {
                    if(in_array($type, $dominion->race->resources))
                    {
                        $trainableByCost[$type] = (int)floor($this->resourceCalculator->getAmount($dominion, $type) / $value);
                    }
                    elseif($type == 'peasant' or $type == 'peasants')
                    {
                        $trainableByCost[$type] = (int)floor($dominion->peasants / $value);
                    }
                    elseif($type == 'draftees')
                    {
                        $trainableByCost[$type] = (int)floor($dominion->military_draftees / $value);
                    }
                    elseif($type == 'wizards' or $type == 'wizard')
                    {
                        $trainableByCost[$type] = (int)floor($dominion->military_wizards / $value);
                    }
                    elseif($type == 'archmages' or $type == 'archmage')
                    {
                        $trainableByCost[$type] = (int)floor($dominion->military_archmages / $value);
                    }
                    elseif($type == 'spies' or $type == 'spy')
                    {
                        $trainableByCost[$type] = (int)floor($dominion->military_spies / $value);
                    }
                    elseif($type == 'prestige')
                    {
                        $trainableByCost[$type] = (int)floor($dominion->prestige / $value);
                    }
                    elseif($type == 'wizard_strength')
                    {
                        $trainableByCost[$type] = (int)floor($dominion->wizard_strength / $value);
                    }
                    elseif($type == 'spy_strength')
                    {
                        $trainableByCost[$type] = (int)floor($dominion->spy_strength / $value);
                    }
                    elseif($type == 'unit1')
                    {
                        $trainableByCost[$type] = (int)floor($dominion->military_unit1 / $value);
                    }
                    elseif($type == 'unit2')
                    {
                        $trainableByCost[$type] = (int)floor($dominion->military_unit2 / $value);
                    }
                    elseif($type == 'unit3')
                    {
                        $trainableByCost[$type] = (int)floor($dominion->military_unit3 / $value);
                    }
                    elseif($type == 'unit4')
                    {
                        $trainableByCost[$type] = (int)floor($dominion->military_unit4 / $value);
                    }
                    else
                    {
                        dd("Undefined cost parameter for \$type $type with \$value $value", $costs);
                    }
                }
            }

            $trainable[$unitType] = min($trainableByCost);

            $slot = intval(str_replace('unit','',$unitType));

            if($maxCapacity = $this->unitHelper->getUnitMaxCapacity($dominion, $slot))
            {
                $availableCapacity = $maxCapacity - $this->militaryCalculator->getTotalUnitsForSlot($dominion, $slot);
                $trainable[$unitType] = min($trainable[$unitType], $availableCapacity);
            }

            $trainable[$unitType] = max(0, $trainable[$unitType]);

        }
        return $trainable;
    }

    /**
     * Returns the Dominion's training cost multiplier.
     *
     * @param Dominion $dominion
     * @param string $resourceType
     * @return float
     */
    public function getSpecialistEliteCostMultiplier(Dominion $dominion, string $resourceType): float
    {
        $multiplier = 0;

        // Values (percentages)
        $smithiesReduction = 2;
        $smithiesReductionMax = 40;

        # Smithies: discount Gold (for all) and Ore (for non-Gnomes)
        # Armory: discounts Gold and Ore (for all)
        # Techs: discounts Gold, Ore, and Lumber (for all); Food ("Lean Mass" techs); Mana ("Magical Weapons" techs)

        // Only discount these resources.
        $discountableResourceTypesByArmory = ['gold', 'ore'];
        $discountableResourceTypesByTech = ['gold', 'ore', 'lumber'];
        $discountableResourceTypesByUnitBonus = ['gold', 'ore', 'lumber', 'mana', 'food'];

        $discountableResourceTypesByTechFood = ['food'];
        $discountableResourceTypesByTechMana = ['mana'];

        $racesExemptFromOreDiscountBySmithies = ['Gnome', 'Imperial Gnome'];

        // Buildings
        $multiplier -= $dominion->getBuildingPerkMultiplier('unit_' . $resourceType . '_costs');

        // Improvements
        $multiplier += $dominion->getImprovementPerkMultiplier('unit_' . $resourceType . '_costs');

        // Faction perk: unit_gold_costs_reduced_by_prestige
        if($dominion->race->getPerkValue('unit_' . $resourceType . '_costs_reduced_by_prestige'))
        {
            $multiplier -= $dominion->prestige / 10000;
        }

        // Techs
        if(in_array($resourceType,$discountableResourceTypesByTech))
        {
            $multiplier += $dominion->getTechPerkMultiplier('military_cost');
        }

        // Units
        if(in_array($resourceType,$discountableResourceTypesByUnitBonus))
        {
            $reducingUnits = 0;
            for ($slot = 1; $slot <= 4; $slot++)
            {
                if($reducesUnitCosts = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'reduces_unit_costs'))
                {
                    $reductionPerPercentOfUnit = (float)$reducesUnitCosts[0];
                    $maxReduction = (float)$reducesUnitCosts[1] / 100;
                    $unitMultiplier = min(($dominion->{'military_unit'.$slot} / $this->populationCalculator->getPopulation($dominion)) * $reductionPerPercentOfUnit, $maxReduction);
                    $multiplier -= $unitMultiplier;
                }
            }
        }

        // Title
        if(isset($dominion->title))
        {
            $multiplier += $dominion->title->getPerkMultiplier('unit_' . $resourceType . '_costs') * $dominion->title->getPerkBonus($dominion);
        }

        if(in_array($resourceType, $discountableResourceTypesByTechFood))
        {
            $multiplier += $dominion->getTechPerkMultiplier('military_cost_food');
        }

        if(in_array($resourceType, $discountableResourceTypesByTechMana))
        {
            $multiplier += $dominion->getTechPerkMultiplier('military_cost_mana');
        }

        # Cap reduction at -50%
        $multiplier = max(-0.50, $multiplier);

        # Spells: can take reduction below 50%!
        $multiplier += $dominion->getSpellPerkMultiplier('unit_' . $resourceType . '_costs');

        // Deity: can take reduction below 50%!
        $multiplier += $dominion->getDeityPerkMultiplier('unit_' . $resourceType . '_costs');

        # Sanity cap, so it doesn't go under -1.
        $multiplier = max(-1, $multiplier);

        return (1 + $multiplier);
    }

    /**
     * Returns the Dominion's training gold cost multiplier for spies.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getSpyCostMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Techs
        $multiplier += $dominion->getTechPerkMultiplier('spy_cost');

        // Buildings
        $multiplier -= $dominion->getBuildingPerkMultiplier('spy_cost');

        // Deity
        $multiplier += $dominion->getDeityPerkMultiplier('spy_cost');

        // Cap $multiplier at -50%
        $multiplier = max($multiplier, -0.50);

        return (1 + $multiplier);
    }

    /**
     * Returns the Dominion's training gold cost multiplier for wizards and archmages.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getWizardCostMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Techs
        $multiplier += $dominion->getTechPerkMultiplier('wizard_cost');

        // Buildings
        $multiplier += $dominion->getBuildingPerkMultiplier('wizard_cost');

        // Deity
        $multiplier += $dominion->getDeityPerkMultiplier('wizard_cost');

        // Cap $multiplier at -100%
        $multiplier = max($multiplier, -1);

        return (1 + $multiplier);
    }

}
