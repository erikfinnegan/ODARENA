<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Models\Dominion;
use OpenDominion\Services\Dominion\GuardMembershipService;
use OpenDominion\Services\Dominion\QueueService;

use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\LandImprovementCalculator;
use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Helpers\LandHelper;

class ProductionCalculator
{
    /**
     * ProductionCalculator constructor.
     *
     * @param ImprovementCalculator $improvementCalculator
     * @param LandCalculator $landCalculator
     * @param PopulationCalculator $populationCalculator
     * @param PrestigeCalculator $prestigeCalculator
     * @param SpellCalculator $spellCalculator
     * @param GuardMembershipService $guardMembershipService
     * @param MilitaryCalculator $militaryCalculator
     */
    public function __construct()
    {
        $this->improvementCalculator = app(ImprovementCalculator::class);
        $this->landCalculator = app(LandCalculator::class);
        $this->landHelper = app(LandHelper::class);
        $this->populationCalculator = app(PopulationCalculator::class);
        $this->prestigeCalculator = app(PrestigeCalculator::class);
        $this->spellCalculator = app(SpellCalculator::class);
        $this->guardMembershipService = app(GuardMembershipService::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->landImprovementCalculator = app(LandImprovementCalculator::class);
        $this->unitHelper = app(UnitHelper::class);
        $this->spellDamageCalculator = app(SpellDamageCalculator::class);
        $this->queueService = app(QueueService::class);
    }

    /**
     * Returns the Dominion's platinum production.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getPlatinumProduction(Dominion $dominion): int
    {
        $platinum = 0;

        $platinum = floor($this->getPlatinumProductionRaw($dominion) * $this->getPlatinumProductionMultiplier($dominion));

        return max(0,$platinum);
    }

    /**
     * Returns the Dominion's raw platinum production.
     *
     * Platinum is produced by:
     * - Employed Peasants (2.7 per)
     * - Building: Alchemy (45 per, or 60 with Alchemist Flame racial spell active)
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getPlatinumProductionRaw(Dominion $dominion): float
    {
        $platinum = 0;

        // Values
        $peasantTax = 2.7;
        $platinumPerAlchemy = 45;

        // Race specialty: Swarm peasants
        if($dominion->race->getPerkValue('unemployed_peasants_produce_platinum'))
        {
            $platinum += $dominion->peasants * $dominion->race->getPerkValue('unemployed_peasants_produce_platinum');
        }
        // Myconid: no plat from peasants
        elseif($dominion->race->name == 'Myconid')
        {
          $platinum = 0;
        }
        else
        {
          // Peasant Tax
          $platinum += ($this->populationCalculator->getPopulationEmployed($dominion) * $peasantTax);
        }

        // Spells
        $platinumPerAlchemy += $this->spellCalculator->getPassiveSpellPerkValue($dominion, 'alchemy_production');

        // Building: Alchemy
        $platinum += ($dominion->building_alchemy * $platinumPerAlchemy);

        // Unit Perk: Production Bonus
        $platinum += $dominion->getUnitPerkProductionBonus('platinum_production');

        // Unit Perk Production Reduction
        $upkeep = $dominion->getUnitPerkProductionBonus('upkeep_platinum');

        // Unit Perk: production_from_title
        $platinum += $dominion->getUnitPerkProductionBonusFromTitle('platinum');

        $platinum = max(0, $platinum-$upkeep);

        return $platinum;
    }

    /**
     * Returns the Dominion's platinum production multiplier.
     *
     * Platinum production is modified by:
     * - Racial Bonus
     * - Spell: Midas Touch (+10%)
     * - Improvement: Science
     * - Guard Tax (-2%)
     * - Tech: Treasure Hunt (+12.5%) or Banker's Foresight (+5%)
     *
     * Platinum production multiplier is capped at +50%.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getPlatinumProductionMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Racial Bonus
        $multiplier += $dominion->race->getPerkMultiplier('platinum_production');

        // Techs
        $multiplier += $dominion->getTechPerkMultiplier('platinum_production');

        // Improvement: Markets
        $multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'markets');

        // Land improvements
        $multiplier += $this->landImprovementCalculator->getPlatinumProductionBonus($dominion);

        // Spells
        $multiplier += $this->spellCalculator->getPassiveSpellPerkMultiplier($dominion, 'platinum_production');

        // Apply Morale multiplier to production multiplier
        return (1 + $multiplier) * $this->militaryCalculator->getMoraleMultiplier($dominion);
    }

    //</editor-fold>

    //<editor-fold desc="Food">

    /**
     * Returns the Dominion's food production.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getFoodProduction(Dominion $dominion): int
    {
        return max(0, floor($this->getFoodProductionRaw($dominion) * $this->getFoodProductionMultiplier($dominion)));
    }

    /**
     * Returns the Dominion's raw food production.
     *
     * Food is produced by:
     * - Building: Farm (80 per)
     * - Building: Dock (35 per)
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getFoodProductionRaw(Dominion $dominion): float
    {
        $food = 0;

        // Building: Farm
        $food += ($dominion->building_farm * 80);

        // Building: Dock
        $food += ($dominion->building_dock * 35 * (1 + $this->spellCalculator->getPassiveSpellPerkMultiplier($dominion, 'food_production_docks')));

        // Building: Tissue
        $food += ($dominion->building_tissue * 4);

        // Building: Mycelia
        $food += ($dominion->building_mycelia * 4);

        // Unit Perk: Production Bonus (Growth Unit)
        $food += $dominion->getUnitPerkProductionBonus('food_production');

        // Unit Perk: sacrified peasants
        $food += $this->populationCalculator->getPeasantsSacrificed($dominion) * 2;

        // Racial Perk: peasants_produce_food
        if($dominion->race->getPerkValue('peasants_produce_food'))
        {
          $food += $dominion->peasants * $dominion->race->getPerkValue('peasants_produce_food');
        }

        $food *= 1 + $this->spellCalculator->getPassiveSpellPerkMultiplier($dominion, 'food_production_raw');

        return max(0,$food);
    }

    /**
     * Returns the Dominion's food production multiplier.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getFoodProductionMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Racial Bonus
        $multiplier += $dominion->race->getPerkMultiplier('food_production');

        // Techs
        $multiplier += $dominion->getTechPerkMultiplier('food_production');

        // Spells
        $multiplier += $this->spellCalculator->getPassiveSpellPerkMultiplier($dominion, 'food_production');

        // Improvement: Harbor
        $multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'harbor');

        // Improvement: Tissue (Growth)
        $multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'tissue');

        // Prestige Bonus
        $prestigeMultiplier = $this->prestigeCalculator->getPrestigeMultiplier($dominion);

        // Land improvements
        $multiplier += $this->landImprovementCalculator->getFoodProductionBonus($dominion);

        // Apply Morale multiplier to production multiplier

        $moraleMultiplier = $this->militaryCalculator->getMoraleMultiplier($dominion);
        if($this->spellCalculator->getPassiveSpellPerkMultiplier($dominion, 'food_production') > 0)
        {
            $moraleMultiplier = max(1, $moraleMultiplier);
        }

        return ((1 + $multiplier) * (1 + $prestigeMultiplier)) * $moraleMultiplier;
    }

    /**
     * Returns the Dominion's food consumption.
     *
     * Each unit in a Dominion's population eats 0.25 food per hour.
     *
     * Food consumption is modified by Racial Bonus.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getFoodConsumption(Dominion $dominion): float
    {
        $consumers = 0;
        $consumption = 0;
        $multiplier = 0;

        $nonConsumingUnitAttributes = [
            'ammunition',
            'equipment',
            'magical',
            'machine',
            'ship',
            'ethereal'
          ];

        $consumers += $dominion->peasants;

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

              if (!$dominion->race->getUnitPerkValueForUnitSlot($slot, 'does_not_count_as_population') and count(array_intersect($nonConsumingUnitAttributes, $unitAttributes)) === 0)
              {
                  $consumers += $dominion->{'military_unit'.$slot};
                  $consumers += $this->queueService->getTrainingQueueTotalByResource($dominion, "military_unit{$slot}");
              }
        }

        $consumers += $dominion->military_draftees;
        $consumers += $dominion->military_spies;
        $consumers += $dominion->military_wizards;
        $consumers += $dominion->military_archmages;

        if($dominion->race->getPerkValue('gryphon_nests_drafts'))
        {
            $consumers -= $dominion->peasants;
        }

        // Values
        $populationConsumption = 0.25;

        // Population Consumption
        $consumption += $consumers * $populationConsumption;

        // Racial Bonus
        $multiplier = $dominion->race->getPerkMultiplier('food_consumption');

        // Invasion Spell: Unhealing Wounds (+10% consumption)
        if ($multiplier !== -1.00 and $this->spellCalculator->isSpellActive($dominion, 'unhealing_wounds'))
        {
            $multiplier += 0.10 * $this->spellDamageCalculator->getDominionHarmfulSpellDamageModifier($dominion, null, 'unhealing_wounds', null);
        }

        // Unit Perk: food_consumption
        $extraFoodEaten = 0;
        for ($unitSlot = 1; $unitSlot <= 4; $unitSlot++)
        {
            if ($dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'food_consumption'))
            {
                $extraFoodUnits = $dominion->{'military_unit'.$unitSlot};
                $extraFoodEatenPerUnit = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'food_consumption');
                $extraFoodEaten += intval($extraFoodUnits * $extraFoodEatenPerUnit);
            }
        }

        $consumption += $extraFoodEaten;

        # Add multiplier.
        $consumption *= (1 + $multiplier);

        return $consumption;
    }

    /**
     * Returns the Dominion's food decay.
     *
     * Food decays 1% per hour.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getFoodDecay(Dominion $dominion): float
    {
        $decay = 0;
        $foodDecay = 0.01;

        $decayProtection = 0;
        $multiplier = 0;
        $food = $dominion->resource_food - $this->getContribution($dominion, 'food');

        # Check for decay protection
        for ($slot = 1; $slot <= 4; $slot++)
        {
            if($decayProtectionPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'decay_protection'))
            {
                $amountPerUnit = $decayProtectionPerk[0];
                $resource = $decayProtectionPerk[1];

                if($resource == 'food' and $amountPerUnit > 0)
                {
                    $decayProtection += $dominion->{"military_unit".$slot} * $amountPerUnit;
                }
            }
        }

        $food = max(0, $food - $decayProtection);

        // Improvement: Granaries (max -100% decay)
        $multiplier -= $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'granaries');

        // Perk: decay reduction
        if($dominion->race->getPerkMultiplier('food_decay'))
        {
            $multiplier += $dominion->race->getPerkMultiplier('food_decay');
        }

        $multiplier = min(0, $multiplier);

        $foodDecay *= (1 + $multiplier);

        $decay += $food * $foodDecay;

        $decay = max(0, $decay);

        return $decay;
    }

    /**
     * Returns the Dominion's net food change.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getFoodNetChange(Dominion $dominion): int
    {
        return round($this->getFoodProduction($dominion) - $this->getFoodConsumption($dominion) - $this->getFoodDecay($dominion));
    }

    public function isOnBrinkOfStarvation(Dominion $dominion): bool
    {
        return $this->getFoodNetChange($dominion) > $dominion->resource_food;
    }

    //</editor-fold>

    //<editor-fold desc="Lumber">

    /**
     * Returns the Dominion's lumber production.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getLumberProduction(Dominion $dominion): int
    {
        return floor($this->getLumberProductionRaw($dominion) * $this->getLumberProductionMultiplier($dominion));
    }

    /**
     * Returns the Dominion's raw lumber production.
     *
     * Lumber is produced by:
     * - Building: Lumberyard (50 per)
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getLumberProductionRaw(Dominion $dominion): float
    {
        $lumber = 0;

        // Values
        $lumberPerLumberyard = 50;

        // Building: Lumberyard
        $lumber += ($dominion->building_lumberyard * $lumberPerLumberyard);

        // Unit Perk Production Bonus (Ant Unit: Worker Ant)
        $lumber += $dominion->getUnitPerkProductionBonus('lumber_production');

        // Unit Perk Production Reduction
        $upkeep = $dominion->getUnitPerkProductionBonus('upkeep_lumber');

        // Unit Perk: production_from_title
        $lumber += $dominion->getUnitPerkProductionBonusFromTitle('lumber');

        // Faction Perk: barren_forest_lumber_production
        foreach ($this->landHelper->getLandTypes($dominion) as $landType)
        {
            $lumber += $this->landCalculator->getTotalBarrenLand($dominion, $landType) * $dominion->race->getPerkValue('barren_' . $landType . '_lumber_production');
        }

        return max(0,$lumber - $upkeep);
    }

    /**
     * Returns the Dominion's lumber production multiplier.
     *
     * Lumber production is modified by:
     * - Racial Bonus
     * - Spell: Gaia's Blessing (+10%)
     * - Tech: Fruits of Labor (20%)
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getLumberProductionMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Racial Bonus
        $multiplier += $dominion->race->getPerkMultiplier('lumber_production');

        // Techs
        $multiplier += $dominion->getTechPerkMultiplier('lumber_production');

        // Title
        if(isset($dominion->title))
        {
            $multiplier += $dominion->title->getPerkMultiplier('lumber_production') * $dominion->title->getPerkBonus($dominion);
        }

        // Spells
        $multiplier += $this->spellCalculator->getPassiveSpellPerkMultiplier($dominion, 'lumber_production');

        // Improvement: Forestry
        $multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'forestry');

        // Apply Morale multiplier to production multiplier
        return (1 + $multiplier) * $this->militaryCalculator->getMoraleMultiplier($dominion);
    }


    /**
     * Returns the Dominion's contribution.
     *
     * Set by Governor to feed the Monster.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getContribution(Dominion $dominion, string $resourceType): float
    {
        $contributed = 0;
        $contribution = $dominion->realm->contribution / 100;

        # Cap contribution to 0-10%, in case something is screwy with $realm->contribution.
        $contribution = min(max($contribution, 0), 0.10);

        if(in_array($resourceType, ['lumber','ore','food']))
        {
            $contributed = $dominion->{'resource_'.$resourceType} * $contribution;
        }

        $contributed = min($dominion->{'resource_'.$resourceType}, $contributed);

        return $contributed;
    }

    /**
     * Returns the Dominion's lumber decay.
     *
     * Lumber decays 1% per hour.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getLumberDecay(Dominion $dominion): float
    {
        $decay = 0;
        $lumberDecay = 0.01;

        $multiplier = 0;
        $decayProtection = 0;
        $lumber = $dominion->resource_lumber - $this->getContribution($dominion, 'lumber');

        # Check for decay protection
        for ($slot = 1; $slot <= 4; $slot++)
        {
            if($decayProtectionPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'decay_protection'))
            {
                $amountPerUnit = $decayProtectionPerk[0];
                $resource = $decayProtectionPerk[1];

                if($resource == 'lumber' and $amountPerUnit > 0)
                {
                    $decayProtection += $dominion->{"military_unit".$slot} * $amountPerUnit;
                }
            }
        }

        $lumber = max(0, $lumber - $decayProtection);

        // Improvement: Granaries
        $multiplier -= $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'granaries');

        // Perk: decay reduction
        if($dominion->race->getPerkMultiplier('lumber_decay'))
        {
            $multiplier += $dominion->race->getPerkMultiplier('lumber_decay');
        }

        $multiplier = min(0, $multiplier);

        $lumberDecay *= (1 + $multiplier);

        $decay += $lumber * $lumberDecay;

        $decay = max(0, $decay);

        return $decay;
    }

    /**
     * Returns the Dominion's net lumber change.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getLumberNetChange(Dominion $dominion): int
    {
        return round($this->getLumberProduction($dominion) - $this->getLumberDecay($dominion));
    }

    //</editor-fold>

    //<editor-fold desc="Mana">

    /**
     * Returns the Dominion's mana production.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getManaProduction(Dominion $dominion): int
    {
        return floor($this->getManaProductionRaw($dominion) * $this->getManaProductionMultiplier($dominion));
    }

    /**
     * Returns the Dominion's raw mana production.
     *
     * Mana is produced by:
     * - Building: Tower (25 per)
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getManaProductionRaw(Dominion $dominion): float
    {
        $mana = 0;

        // Building: Tower
        $mana += ($dominion->building_tower * 25);

        // Building: Ziggurat
        if($dominion->race->getPerkValue('mana_per_ziggurat'))
        {
            $mana += $dominion->building_ziggurat * $dominion->race->getPerkValue('mana_per_ziggurat');
        }

        // Unit Perk Production Bonus
        $mana += $dominion->getUnitPerkProductionBonus('mana_production');

        // Unit Perk: production_from_title
        $mana += $dominion->getUnitPerkProductionBonusFromTitle('mana');

        // Perk: mana draftee production
        $mana += $dominion->military_draftees * $dominion->race->getPerkValue('draftees_produce_mana');

        return max(0,$mana);
    }

    /**
     * Returns the Dominion's mana production multiplier.
     *
     * Mana production is modified by:
     * - Racial Bonus
     * - Tech: Enchanted Lands (+15%)
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getManaProductionMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Improvement: Tower
        #$multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'towers');

        // Spells
        $multiplier += $this->spellCalculator->getPassiveSpellPerkMultiplier($dominion, 'mana_production');

        // Improvement: Spires
        $multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'spires');

        // Racial Bonus
        $multiplier += $dominion->race->getPerkMultiplier('mana_production');

        // Techs
        $multiplier += $dominion->getTechPerkMultiplier('mana_production');

        return (1 + $multiplier);
    }

    /**
     * Returns the Dominion's mana decay.
     *
     * Mana decays 2% per hour.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getManaDecay(Dominion $dominion): float
    {
        $decay = 0;

        $manaDecay = 0.02;

        if($dominion->race->getPerkMultiplier('mana_drain'))
        {
            $manaDecay *= (1 + $dominion->race->getPerkMultiplier('mana_drain'));
        }

        $decayProtection = 0;
        $mana = $dominion->resource_mana;

        # Check for decay protection
        for ($slot = 1; $slot <= 4; $slot++)
        {
            if($decayProtectionPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'decay_protection'))
            {
                $amountPerUnit = $decayProtectionPerk[0];
                $resource = $decayProtectionPerk[1];

                if($resource == 'mana' and $amountPerUnit > 0)
                {
                    $decayProtection += $dominion->{"military_unit".$slot} * $amountPerUnit;
                }
            }
        }

        $mana = max(0, $mana - $decayProtection);

        $decay += ($mana * $manaDecay);

        // Unit Perk Production Bonus (Dimensionalists Units)
        $decay += min($dominion->resource_mana, $dominion->getUnitPerkProductionBonus('mana_drain'));

        // Ruler title: Conjurer

        $decay *= (1 + $dominion->title->getPerkMultiplier('mana_drain') * $dominion->title->getPerkBonus($dominion));

        return $decay;
    }

    /**
     * Returns the Dominion's net mana change.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getManaNetChange(Dominion $dominion): int
    {
        $manaDecay = $this->getManaDecay($dominion);

        return round($this->getManaProduction($dominion) - $this->getManaDecay($dominion));
    }

    //</editor-fold>

    //<editor-fold desc="Ore">

    /**
     * Returns the Dominion's ore production.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getOreProduction(Dominion $dominion): int
    {
        return floor($this->getOreProductionRaw($dominion) * $this->getOreProductionMultiplier($dominion));
    }

    /**
     * Returns the Dominion's raw ore production.
     *
     * Ore is produced by:
     * - Building: Ore Mine (60 per)
     * - Dwarf Unit: Miner (2 per)
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getOreProductionRaw(Dominion $dominion): float
    {
        $ore = 0;

        // Values
        $orePerOreMine = 60;

        // Building: Ore Mine
        $ore += ($dominion->building_ore_mine * $orePerOreMine);

        // Unit Perk Production Bonus (Dwarf Unit: Miner)
        $ore += $dominion->getUnitPerkProductionBonus('ore_production');

        // Unit Perk Production Reduction
        $upkeep = $dominion->getUnitPerkProductionBonus('upkeep_ore');

        // Unit Perk: production_from_title
        $ore += $dominion->getUnitPerkProductionBonusFromTitle('ore');

        return max(0,$ore - $upkeep);
    }

    /**
     * Returns the Dominion's ore production multiplier.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getOreProductionMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Racial Bonus
        $multiplier += $dominion->race->getPerkMultiplier('ore_production');

        // Techs
        $multiplier += $dominion->getTechPerkMultiplier('ore_production');

        // Title
        if(isset($dominion->title))
        {
            $multiplier += $dominion->title->getPerkMultiplier('ore_production') * $dominion->title->getPerkBonus($dominion);
        }

        // Improvement: Refinery
        $multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'refinery');

        // Spells
        $multiplier += $this->spellCalculator->getPassiveSpellPerkMultiplier($dominion, 'ore_production');

        $multiplier = max(-1, $multiplier);

        // Apply Morale multiplier to production multiplier
        return (1 + $multiplier) * $this->militaryCalculator->getMoraleMultiplier($dominion);
    }

    /**
     * Returns the Dominion's gem production.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getGemProduction(Dominion $dominion): int
    {
        return floor($this->getGemProductionRaw($dominion) * $this->getGemProductionMultiplier($dominion));
    }

    /**
     * Returns the Dominion's raw gem production.
     *
     * Gems are produced by:
     * - Building: Gem Mine (15 per)
     * - Dwarf Unit: Miner (0.5 per)
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getGemProductionRaw(Dominion $dominion): float
    {
        $gems = 0;

        // Building: Gem Mine
        $gems += $dominion->building_gem_mine * 15;

        // Unit Perk Production Bonus (Dwarf Unit: Miner)
        $gems += $dominion->getUnitPerkProductionBonus('gem_production');

        // Unit Perk: production_from_title
        $gems += $dominion->getUnitPerkProductionBonusFromTitle('gem');

        return max(0,$gems);
    }

    /**
     * Returns the Dominion's gem production multiplier.
     *
     * Gem production is modified by:
     * - Racial Bonus
     * - Tech: Fruits of Labor (+10%) and Miner's Refining (+5%)
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getGemProductionMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Racial Bonus
        $multiplier += $dominion->race->getPerkMultiplier('gem_production');

        // Techs
        $multiplier += $dominion->getTechPerkMultiplier('gem_production');

        // Title
        if(isset($dominion->title))
        {
            $multiplier += $dominion->title->getPerkMultiplier('gem_production') * $dominion->title->getPerkBonus($dominion);
        }

        $multiplier += $this->spellCalculator->getPassiveSpellPerkMultiplier($dominion, 'gem_production');

        $multiplier = max(-1, $multiplier);

        // Apply Morale multiplier to production multiplier
        return (1 + $multiplier) * $this->militaryCalculator->getMoraleMultiplier($dominion);
    }

    //</editor-fold>

    //<editor-fold desc="Tech">

    /**
     * Returns the Dominion's experience point production.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getTechProduction(Dominion $dominion): int
    {
        return floor($this->getTechProductionRaw($dominion) * $this->getTechProductionMultiplier($dominion));
    }

    /**
     * Returns the Dominion's raw tech production (experience points, XP).
     *
     * Experience points are produced by:
     * - Prestige: Prestige/tick
     *
     * @param Dominion $dominion
     * @return float
     */
     public function getTechProductionRaw(Dominion $dominion): float
     {
         $tech = max(0, $dominion->prestige);

         $tech += $dominion->getUnitPerkProductionBonus('tech_production');

         // Unit Perk: production_from_title
         $tech += $dominion->getUnitPerkProductionBonusFromTitle('tech');

         return max(0,$tech);
     }

    /**
     * Returns the Dominion's experience point production multiplier.
     *
     * Experience point production is modified by:
     * - Racial Bonus
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getTechProductionMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Racial Bonus
        $multiplier += $dominion->race->getPerkMultiplier('tech_production');

        // Title
        if(isset($dominion->title))
        {
            $multiplier += $dominion->title->getPerkMultiplier('tech_production') * $dominion->title->getPerkBonus($dominion);
        }

        // Spell
        $multiplier += $this->spellCalculator->getPassiveSpellPerkMultiplier($dominion, 'tech_production');

        # Observatory
        $multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'observatory');

        return (1 + $multiplier);
    }

    //</editor-fold>

    //<editor-fold desc="Boats">

    /**
     * Returns the Dominion's boat production per hour.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getBoatProduction(Dominion $dominion): float
    {
        return ($this->getBoatProductionRaw($dominion) * $this->getBoatProductionMultiplier($dominion));
    }

    /**
     * Returns the Dominion's raw boat production per hour.
     *
     * Boats are produced by:
     * - Building: Dock (20 per)
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getBoatProductionRaw(Dominion $dominion): float
    {
        $boats = 0;

        // Values
        $docksPerBoatPerTick = 20;

        $boats += ($dominion->building_dock / $docksPerBoatPerTick);

        // Unit Perk: production_from_title
        $boats += $dominion->getUnitPerkProductionBonusFromTitle('boats');

        return max(0,$boats);
    }

    /**
     * Returns the Dominions's boat production multiplier.
     *
     * Boat production is modified by:
     * - Improvement: Harbor
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getBoatProductionMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Spells
        $multiplier += $this->spellCalculator->getPassiveSpellPerkMultiplier($dominion, 'boat_production');

        // Improvement: Harbor
        $multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'harbor');

        // Land improvements
        $multiplier += $this->landImprovementCalculator->getBoatProductionBonus($dominion);

        // Apply Morale multiplier to production multiplier
        return (1 + $multiplier) * $this->militaryCalculator->getMoraleMultiplier($dominion);
    }


        /**
         * Returns the Dominion's wild yeti production per hour.
         *
         * Boats are produced by:
         * - Building: Gryphon Nest (1 per)
         *
         * @param Dominion $dominion
         * @return float
         */
        public function getWildYetiProduction(Dominion $dominion): float
        {
            if(!$dominion->race->getPerkValue('gryphon_nests_generate_wild_yetis'))
            {
                return 0;
            }

            $wildYetis = 0;

            // Values
            $wildYetisPerGryphonNest = 0.1;

            $wildYetis += intval($dominion->building_gryphon_nest * $wildYetisPerGryphonNest);

            return max(0,$wildYetis);
        }

        /**
         * Returns the Dominion's net wild yeti change.
         *
         * @param Dominion $dominion
         * @return int
         */
        public function getWildYetiNetChange(Dominion $dominion): int
        {
            return intval($this->getWildYetiProduction($dominion));
        }

        /**
         * Returns the Dominion's soul production, based on peasants sacrificed.
         *
         * @param Dominion $dominion
         * @return float
         */
        public function getSoulProduction(Dominion $dominion): float
        {
            return $this->populationCalculator->getPeasantsSacrificed($dominion) * 1;
        }

        /**
         * Returns the Dominion's blood production, based on peasants sacrificed.
         *
         * @param Dominion $dominion
         * @return float
         */
        public function getBloodProduction(Dominion $dominion): float
        {
            return $this->populationCalculator->getPeasantsSacrificed($dominion) * 1.5;
        }

        /**
         * Returns the Dominion's max storage for a specific resource.
         *
         * @param Dominion $dominion
         * @return int
         */
        public function getMaxStorage(Dominion $dominion, string $resource): int
        {
            $maxStorageTicks = 96;
            $land = $this->landCalculator->getTotalLand($dominion);

            if($resource == 'platinum')
            {
                $max = $land * 10000 + $dominion->getUnitPerkProductionBonus('platinum_production');
            }
            elseif($resource == 'lumber')
            {
                $max = $maxStorageTicks * ($dominion->land_forest * 50 + $dominion->getUnitPerkProductionBonus('lumber_production'));
                $max = max($max, $land * 100);
            }
            elseif($resource == 'ore')
            {
                $max = $maxStorageTicks * ($dominion->building_ore_mine * 60 + $dominion->getUnitPerkProductionBonus('ore_production'));
                $max = max($max, $land * 100);
            }
            elseif($resource == 'gems' or $resource == 'gem')
            {
                $max = $maxStorageTicks * ($dominion->building_gem_mine * 15 + $dominion->getUnitPerkProductionBonus('gem_production'));
                $max = max($max, $land * 50);
            }

            return $max;

        }



}
