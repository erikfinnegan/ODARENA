<?php

namespace OpenDominion\Calculators\Dominion\Actions;

use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;

# ODA
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Calculators\Dominion\SpellCalculator;

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

    /**
     * TrainingCalculator constructor.
     *
     * @param LandCalculator $landCalculator
     * @param UnitHelper $unitHelper
     */
    public function __construct(
          LandCalculator $landCalculator,
          UnitHelper $unitHelper,
          ImprovementCalculator $improvementCalculator,
          MilitaryCalculator $militaryCalculator,
          QueueService $queueService,
          SpellCalculator $spellCalculator
          )
    {
        $this->landCalculator = $landCalculator;
        $this->unitHelper = $unitHelper;
        $this->improvementCalculator = $improvementCalculator;
        $this->militaryCalculator = $militaryCalculator;
        $this->queueService = $queueService;
        $this->spellCalculator = $spellCalculator;
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
        $spyBaseCost = 500;
        $wizardBaseCost = 500;
        $archmageBaseCost = 1000;
        $archmageBaseCost += $dominion->race->getPerkValue('archmage_cost');

        $spyCostMultiplier = $this->getSpyCostMultiplier($dominion);
        $wizardCostMultiplier = $this->getWizardCostMultiplier($dominion);

        // Values
        $spyPlatinumCost = (int)ceil($spyBaseCost * $spyCostMultiplier);
        $wizardPlatinumCost = (int)ceil($wizardBaseCost * $wizardCostMultiplier);
        $archmagePlatinumCost = (int)ceil($archmageBaseCost * $wizardCostMultiplier);

        $units = $dominion->race->units;

        foreach ($this->unitHelper->getUnitTypes() as $unitType) {
            $cost = [];

            switch ($unitType) {
                case 'spies':
                    $cost['draftees'] = 1;
                    $cost['platinum'] = $spyPlatinumCost;
                    break;

                case 'wizards':
                    $cost['draftees'] = 1;
                    $cost['platinum'] = $wizardPlatinumCost;
                    break;

                case 'archmages':
                    $cost['platinum'] = $archmagePlatinumCost;
                    $cost['wizards'] = 1;
                    break;

                default:
                    $unitSlot = (((int)str_replace('unit', '', $unitType)) - 1);

                    $platinum = $units[$unitSlot]->cost_platinum;
                    $ore = $units[$unitSlot]->cost_ore;

                    // New unit cost resources
                    $food = $units[$unitSlot]->cost_food;
                    $mana = $units[$unitSlot]->cost_mana;
                    $gem = $units[$unitSlot]->cost_gem;
                    $lumber = $units[$unitSlot]->cost_lumber;
                    $prestige = $units[$unitSlot]->cost_prestige;
                    $boat = $units[$unitSlot]->cost_boat;
                    $champion = $units[$unitSlot]->cost_champion;
                    $soul = $units[$unitSlot]->cost_soul;
                    $morale = $units[$unitSlot]->cost_morale;
                    $wild_yeti = $units[$unitSlot]->cost_wild_yeti;
                    $blood = $units[$unitSlot]->cost_blood;

                    $unit1 = $units[$unitSlot]->cost_unit1;
                    $unit2 = $units[$unitSlot]->cost_unit2;
                    $unit3 = $units[$unitSlot]->cost_unit3;
                    $unit4 = $units[$unitSlot]->cost_unit4;

                    $spy = $units[$unitSlot]->cost_spy;
                    $wizard = $units[$unitSlot]->cost_wizard;
                    $archmage = $units[$unitSlot]->cost_archmage;

                    #if ($platinum > 0) {
                        $cost['platinum'] = (int)ceil($platinum * $this->getSpecialistEliteCostMultiplier($dominion, 'platinum'));
                    #}

                    #if ($ore > 0) {
                        $cost['ore'] = $ore;
                        $cost['ore'] = (int)ceil($ore * $this->getSpecialistEliteCostMultiplier($dominion, 'ore'));
                    #}

                    // FOOD cost for units
                    #if ($food > 0) {
                        $cost['food'] = $food;
                        $cost['food'] = (int)ceil($food * $this->getSpecialistEliteCostMultiplier($dominion, 'food'));
                    #}
                    // MANA cost for units
                    #if ($mana > 0) {
                        $cost['mana'] = $mana;
                        $cost['mana'] = (int)ceil($mana * $this->getSpecialistEliteCostMultiplier($dominion, 'mana'));
                    #}
                    // GEM cost for units
                    #if ($gem > 0) {
                        $cost['gem'] = $gem;
                        $cost['gem'] = (int)ceil($gem * $this->getSpecialistEliteCostMultiplier($dominion, 'gem'));
                    #}
                    // LUMBER cost for units
                    #if ($lumber > 0) {
                        $cost['lumber'] = $lumber;
                        $cost['lumber'] = (int)ceil($lumber * $this->getSpecialistEliteCostMultiplier($dominion, 'lumber'));
                    #}
                    // PRESTIGE cost for units
                    #if ($prestige > 0) {
                        $cost['prestige'] = $prestige;
                        $cost['prestige'] = (int)ceil($prestige * $this->getSpecialistEliteCostMultiplier($dominion, 'prestige'));
                    #}

                    // BOAT cost for units
                    #if ($boat > 0) {
                        $cost['boat'] = $boat;
                        $cost['boat'] = (int)ceil($boat * $this->getSpecialistEliteCostMultiplier($dominion, 'boat'));
                    #}

                    // CHAMPION cost for units
                    #if ($champion > 0) {
                        $cost['champion'] = $champion;
                        $cost['champion'] = (int)ceil($champion * $this->getSpecialistEliteCostMultiplier($dominion, 'champion'));
                    #}

                    // SOUL cost for units
                    #if ($soul > 0) {
                        $cost['soul'] = $soul;
                        $cost['soul'] = (int)ceil($soul * $this->getSpecialistEliteCostMultiplier($dominion, 'soul'));
                    #}

                    // BLOOD cost for units
                    if ($blood > 0) {
                        $cost['blood'] = $blood;
                        $cost['blood'] = (int)ceil($blood * $this->getSpecialistEliteCostMultiplier($dominion, 'blood'));
                    }

                    // UNIT1 cost for units
                    #if ($unit1 > 0) {
                        $cost['unit1'] = $unit1;
                        $cost['unit1'] = (int)ceil($unit1 * $this->getSpecialistEliteCostMultiplier($dominion, 'unit1'));
                    #}

                    // UNIT2 cost for units
                    #if ($unit2 > 0) {
                        $cost['unit2'] = $unit2;
                        $cost['unit2'] = (int)ceil($unit2 * $this->getSpecialistEliteCostMultiplier($dominion, 'unit2'));
                    #}

                    // UNIT3 cost for units
                    #if ($unit3 > 0) {
                        $cost['unit3'] = $unit3;
                        $cost['unit3'] = (int)ceil($unit3 * $this->getSpecialistEliteCostMultiplier($dominion, 'unit3'));
                    #}

                    // UNIT4 cost for units
                    #if ($unit4 > 0) {
                        $cost['unit4'] = $unit4;
                        $cost['unit4'] = (int)ceil($unit4 * $this->getSpecialistEliteCostMultiplier($dominion, 'unit4'));
                    #}

                    // MORALE cost for units
                    #if ($morale > 0) {
                        $cost['morale'] = $morale;
                        $cost['morale'] = (int)ceil($morale * $this->getSpecialistEliteCostMultiplier($dominion, 'morale'));
                    #}

                    // WILD YETI cost for units
                    #if ($wild_yeti > 0) {
                        $cost['wild_yeti'] = $wild_yeti;
                        $cost['wild_yeti'] = (int)ceil($wild_yeti * $this->getSpecialistEliteCostMultiplier($dominion, 'wild_yeti'));
                    #}

                    // SPY cost for units
                    #if ($spy > 0) {
                        $cost['spy'] = $spy;
                        $cost['spy'] = (int)ceil($spy * $this->getSpecialistEliteCostMultiplier($dominion, 'spy'));
                    #}

                    // WIZARD cost for units
                    #if ($wizard > 0) {
                        $cost['wizard'] = $wizard;
                        $cost['wizard'] = (int)ceil($wizard * $this->getSpecialistEliteCostMultiplier($dominion, 'wizard'));
                    #}

                    // ARCHMAGE cost for units
                    #if ($archmage > 0) {
                        $cost['archmage'] = $archmage;
                        $cost['archmage'] = (int)ceil($archmage * $this->getSpecialistEliteCostMultiplier($dominion, 'archmage'));
                    #}

                    if($dominion->race->getUnitPerkValueForUnitSlot(intval(str_replace('unit','',$unitType)), 'no_draftee') == 1)
                    {
                      $cost['draftees'] = 0;
                    }
                    else
                    {
                      $cost['draftees'] = 1;
                    }

                    break;
            }

            $costsPerUnit[$unitType] = $cost;
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

        $fieldMapping = [
            'platinum' => 'resource_platinum',
            'ore' => 'resource_ore',
            'draftees' => 'military_draftees',
            'wizards' => 'military_wizards',

            //New unit cost resources

            'food' => 'resource_food',
            'mana' => 'resource_mana',
            'gem' => 'resource_gems',
            'lumber' => 'resource_lumber',
            'prestige' => 'prestige',
            'boat' => 'resource_boats',
            'champion' => 'resource_champion',
            'soul' => 'resource_soul',
            'morale' => 'morale',
            'wild_yeti' => 'resource_wild_yeti',
            'blood' => 'resource_blood',

            'unit1' => 'military_unit1',
            'unit2' => 'military_unit2',
            'unit3' => 'military_unit3',
            'unit4' => 'military_unit4',

            'spy' => 'military_spies',
            'wizard' => 'military_wizards',
            'archmage' => 'military_archmages',
        ];

        $costsPerUnit = $this->getTrainingCostsPerUnit($dominion);

        foreach ($costsPerUnit as $unitType => $costs)
        {
            $trainableByCost = [];

            foreach ($costs as $type => $value)
            {
                if($value !== 0)
                {
                  $trainableByCost[$type] = (int)floor($dominion->{$fieldMapping[$type]} / $value);
                }
            }

            $trainable[$unitType] = min($trainableByCost);

            $slot = intval(str_replace('unit','',$unitType));
            # Look for building_limit
            if($buildingLimit = $dominion->race->getUnitPerkValueForUnitSlot($slot,'building_limit'))
            {
              $buildingLimitedTo = 'building_'.$buildingLimit[0]; # Land type
              $unitsPerBuilding = (float)$buildingLimit[1]; # Units per building
              $improvementToIncrease = $buildingLimit[2]; # Resource that can raise the limit

              $unitsPerBuilding *= (1 + $this->improvementCalculator->getImprovementMultiplierBonus($dominion, $improvementToIncrease));

              $amountOfLimitingBuilding = $dominion->{$buildingLimitedTo};

              $maxAdditionalPermittedOfThisUnit = intval($amountOfLimitingBuilding * $unitsPerBuilding) - $this->militaryCalculator->getTotalUnitsForSlot($dominion, $slot) - $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit'.$slot);

              $trainable[$unitType] = min($trainable[$unitType], $maxAdditionalPermittedOfThisUnit);
            }

            # Look for pairing_limit
            if($pairingLimit = $dominion->race->getUnitPerkValueForUnitSlot($slot,'pairing_limit'))
            {
              $pairingLimitedBy = intval($pairingLimit[0]);
              $pairingLimitedTo = $pairingLimit[1];

              $pairingLimitedByTrained = $dominion->{'military_unit'.$pairingLimitedBy};

              $maxAdditionalPermittedOfThisUnit = intval($pairingLimitedByTrained * $pairingLimitedTo) - $this->militaryCalculator->getTotalUnitsForSlot($dominion, $slot) - $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit'.$slot);

              $trainable[$unitType] = min($trainable[$unitType], $maxAdditionalPermittedOfThisUnit);
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

        # Smithies: discount Platinum (for all) and Ore (for non-Gnomes)
        # Armory: discounts Platinum and Ore (for all)
        # Techs: discounts Platinum, Ore, and Lumber (for all); Food ("Lean Mass" techs); Mana ("Magical Weapons" techs)

        // Only discount these resources.
        $discountableResourceTypesBySmithies = ['platinum', 'ore'];
        $discountableResourceTypesByArmory = ['platinum', 'ore'];
        $discountableResourceTypesByTech = ['platinum', 'ore', 'lumber'];
        $discountableResourceTypesByTitle = ['platinum', 'ore', 'lumber', 'mana', 'food'];

        $discountableResourceTypesByTechFood = ['food'];
        $discountableResourceTypesByTechMana = ['mana'];

        $racesExemptFromOreDiscountBySmithies = ['Gnome', 'Imperial Gnome'];

        // Smithies
        if(in_array($resourceType,$discountableResourceTypesBySmithies))
        {
          if($resourceType == 'ore' and in_array($dominion->race->name, $racesExemptFromOreDiscountBySmithies))
          {
            $multiplier = 0;
          }
          elseif($resourceType !== 'lumber')
          {
            $multiplier -= min(
                (($dominion->building_smithy / $this->landCalculator->getTotalLand($dominion)) * $smithiesReduction),
                ($smithiesReductionMax / 100)
            );
          }
        }

        // Armory
        if(in_array($resourceType,$discountableResourceTypesByArmory))
        {
          // Armory
          if($this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'armory') > 0)
          {
              $multiplier -= $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'armory');
          }
        }

        // Techs
        if(in_array($resourceType,$discountableResourceTypesByTech))
        {
          $multiplier += $dominion->getTechPerkMultiplier('military_cost');
        }

        // Title
        if(isset($dominion->title))
        {
            if(in_array($resourceType,$discountableResourceTypesByTitle))
            {
              $multiplier += $dominion->title->getPerkMultiplier('military_cost') * $dominion->title->getPerkBonus($dominion);
            }
        }

        if(in_array($resourceType,$discountableResourceTypesByTechFood))
        {
          $multiplier += $dominion->getTechPerkMultiplier('military_cost_food');
        }

        if(in_array($resourceType,$discountableResourceTypesByTechMana))
        {
          $multiplier += $dominion->getTechPerkMultiplier('military_cost_mana');
        }


        $multiplier = max(-0.50, $multiplier);

        if ($this->spellCalculator->isSpellActive($dominion, 'call_to_arms'))
        {
            $multiplier -= 0.10;
        }

        return (1 + $multiplier);
    }

    /**
     * Returns the Dominion's training platinum cost multiplier for spies.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getSpyCostMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Techs
        $multiplier += $dominion->getTechPerkMultiplier('spy_cost');

        // Cap $multiplier at -50%
        $multiplier = max($multiplier, -0.50);

        return (1 + $multiplier);
    }

    /**
     * Returns the Dominion's training platinum cost multiplier for wizards and archmages.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getWizardCostMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Techs
        $multiplier += $dominion->getTechPerkMultiplier('wizard_cost');

        // Values (percentages)
        $wizardGuildReduction = 2;
        $wizardGuildReductionMax = 40;

        // Wizard Guilds
        $multiplier -= min(
            (($dominion->building_wizard_guild / $this->landCalculator->getTotalLand($dominion)) * $wizardGuildReduction),
            ($wizardGuildReductionMax / 100)
        );

        return (1 + $multiplier);
    }
}
