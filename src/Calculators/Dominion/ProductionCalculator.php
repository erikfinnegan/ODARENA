<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Models\Dominion;
use OpenDominion\Services\Dominion\GuardMembershipService;

// Morale affects production
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\LandImprovementCalculator;

class ProductionCalculator
{
    /** @var ImprovementCalculator */
    protected $improvementCalculator;

    /** @var LandCalculator */
    protected $landCalculator;

    /** @var PopulationCalculator */
    protected $populationCalculator;

    /** @var PrestigeCalculator */
    private $prestigeCalculator;

    /** @var SpellCalculator */
    protected $spellCalculator;

    /** @var GuardMembershipService */
    private $guardMembershipService;

    /** @var MilitaryCalculator */
    protected $militaryCalculator;

    /** @var LandImprovementCalculator */
    protected $landImprovementCalculator;

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
    public function __construct(
        ImprovementCalculator $improvementCalculator,
        LandCalculator $landCalculator,
        PopulationCalculator $populationCalculator,
        PrestigeCalculator $prestigeCalculator,
        SpellCalculator $spellCalculator,
        GuardMembershipService $guardMembershipService,
        MilitaryCalculator $militaryCalculator,
        LandImprovementCalculator $landImprovementCalculator
        )
    {
        $this->improvementCalculator = $improvementCalculator;
        $this->landCalculator = $landCalculator;
        $this->populationCalculator = $populationCalculator;
        $this->prestigeCalculator = $prestigeCalculator;
        $this->spellCalculator = $spellCalculator;
        $this->guardMembershipService = $guardMembershipService;
        $this->militaryCalculator = $militaryCalculator;
        $this->landImprovementCalculator = $landImprovementCalculator;
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

        // Spell: Alchemist Flame
        if ($this->spellCalculator->isSpellActive($dominion, 'alchemist_flame'))
        {
            $platinumPerAlchemy += 30;
        }

        // Building: Alchemy
        $platinum += ($dominion->building_alchemy * $platinumPerAlchemy);

        // Unit Perk: Production Bonus (Cult)
        $platinum += $dominion->getUnitPerkProductionBonus('platinum_production');

        // Unit Perk Production Reduction (Dragon Unit: Mercenary)
        $upkeep = $dominion->getUnitPerkProductionBonus('platinum_upkeep');



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

        // Human: Call To Arms
        if ($this->spellCalculator->isSpellActive($dominion, 'call_to_arms'))
        {
            $multiplier += 0.20;
        }

        // Vampires: Fine Arts
        if ($this->spellCalculator->isSpellActive($dominion, 'fine_arts'))
        {
            $multiplier += 0.05;
        }

        // Invasion Spell: Unhealing Wounds (-5% production)
        if ($this->spellCalculator->isSpellActive($dominion, 'great_fever'))
        {
            $multiplier -= 0.05;
        }

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
        $food += ($dominion->building_dock * 35);

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

        // Racial Spell: Metabolism (Growth) - Double food production
        if ($this->spellCalculator->isSpellActive($dominion, 'metabolism'))
        {
            $food *= 2;
        }

        return max(0,$food);
    }

    /**
     * Returns the Dominion's food production multiplier.
     *
     * Food production is modified by:
     * - Racial Bonus
     * - Spell: Gaia's Blessing (+20%) or Gaia's Watch (+10%)
     * - Improvement: Harbor and Tissue
     * - Tech: Farmer's Growth (+10%)
     * - Prestige (+1% per 100 prestige, multiplicative)
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

        # SPELLS

        // Spell:  Gaia's Blessing (+20%)
        if ($this->spellCalculator->isSpellActive($dominion, 'gaias_blessing'))
        {
            $multiplier += 0.20;
        }

        // Spell: Gaia's Watch (+10%)
        if ($this->spellCalculator->isSpellActive($dominion, 'gaias_watch'))
        {
            $multiplier += 0.10;
        }

        // Spell: Rainy Season (+50%)
        if ($this->spellCalculator->isSpellActive($dominion, 'rainy_season'))
        {
            $multiplier += 0.50;
        }

        // Spell [hostile]: Insect Swarm (-5%)
        if ($this->spellCalculator->isSpellActive($dominion, 'insect_swarm'))
        {
            $multiplier -= 0.05 * (1 - $dominion->race->getPerkMultiplier('damage_from_insect_swarm'));
        }

        // Invasion Spell: Great Fever (-5% food production)
        if ($this->spellCalculator->isSpellActive($dominion, 'great_fever'))
        {
            $multiplier -= 0.05;
        }

        # /SPELLS

        // Improvement: Harbor
        $multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'harbor');

        // Improvement: Tissue (Growth)
        $multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'tissue');

        // Prestige Bonus
        $prestigeMultiplier = $this->prestigeCalculator->getPrestigeMultiplier($dominion);

        // Land improvements
        $multiplier += $this->landImprovementCalculator->getFoodProductionBonus($dominion);

        // Apply Morale multiplier to production multiplier
        return ((1 + $multiplier) * (1 + $prestigeMultiplier)) * $this->militaryCalculator->getMoraleMultiplier($dominion);
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
        $consumption = 0;
        $multiplier = 0;

        $consumers = $this->populationCalculator->getPopulation($dominion);

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
            $multiplier += 0.10;
        }

        // Unit Perk: food_consumption
        $extraFoodEaten = 0;
        for ($unitSlot = 1; $unitSlot <= 4; $unitSlot++)
        {
            if ($dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'food_consumption'))
            {
                $extraFoodUnits = $dominion->{"military_unit".$unitSlot};
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

        return max(0,$lumber);
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

        # SPELLS
        // Spell:  Gaia's Blessing (+20%)
        if ($this->spellCalculator->isSpellActive($dominion, 'gaias_blessing'))
        {
            $multiplier += 0.10;
        }

        // Spell: Rainy Season (+50%)
        if ($this->spellCalculator->isSpellActive($dominion, 'rainy_season'))
        {
            $multiplier += 0.50;
        }

        # /SPELLS

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

        return max(0,$ore);
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

        # SPELLS
        // Spell: Miner's Sight (+10%)
        if ($this->spellCalculator->isSpellActive($dominion, 'miners_sight'))
        {
            $multiplier += 0.10;
        }

        // Spell: Mining Strength (+10%)
        if ($this->spellCalculator->isSpellActive($dominion, 'mining_strength'))
        {
            $multiplier += 0.10;
        }

        if ($this->spellCalculator->isSpellActive($dominion, 'earthquake'))
        {
            $multiplier -= 0.05;
        }

        // Spell: Rainy Season (-100%)
        if ($this->spellCalculator->isSpellActive($dominion, 'rainy_season'))
        {
            $multiplier = -1;
        }

        // Human: Call To Arms
        if ($this->spellCalculator->isSpellActive($dominion, 'call_to_arms'))
        {
            $multiplier += 0.20;
        }




        // Apply Morale multiplier to production multiplier
        return (1 + $multiplier) * $this->militaryCalculator->getMoraleMultiplier($dominion);
    }

    //</editor-fold>

    //<editor-fold desc="Gems">

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
     * - Building: Diamond Mine (15 per)
     * - Dwarf Unit: Miner (0.5 per)
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getGemProductionRaw(Dominion $dominion): float
    {
        $gems = 0;

        // Values
        $gemsPerDiamondMine = 15;

        // Building: Diamond Mine
        $gems += ($dominion->building_diamond_mine * $gemsPerDiamondMine);

        // Unit Perk Production Bonus (Dwarf Unit: Miner)
        $gems += $dominion->getUnitPerkProductionBonus('gem_production');

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

        # SPELLS
        // Spell: Miner's Sight (+5%)
        if ($this->spellCalculator->isSpellActive($dominion, 'miners_sight'))
        {
            $multiplier += 0.05;
        }

        // Vampires: Fine Arts (+5%)
        if ($this->spellCalculator->isSpellActive($dominion, 'fine_arts'))
        {
            $multiplier += 0.05;
        }

        // Spell: Earthquake (-5%)
        if ($this->spellCalculator->isSpellActive($dominion, 'earthquake'))
        {
            $multiplier -= 0.05;
        }

        // Spell: Rainy Season (-100%)
        if ($this->spellCalculator->isSpellActive($dominion, 'rainy_season'))
        {
            $multiplier = -1;
        }

        # /SPELLS

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

        // Spell: Great Flood (-25%)
        if ($this->spellCalculator->isSpellActive($dominion, 'great_flood'))
        {
            $multiplier -= 0.25;
        }

        // Spell: Rainy Season (-100%)
        if ($this->spellCalculator->isSpellActive($dominion, 'rainy_season'))
        {
            $multiplier = -1;
        }

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

            // Yeti: Spell (triples wild yeti production)
            if ($this->spellCalculator->isSpellActive($dominion, 'gryphons_call'))
            {
              $wildYetis = $wildYetis * 4;
            }

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
            $max = 0;
            $land = $this->landCalculator->getTotalLand($dominion);

            if($resource == 'platinum')
            {
                $max = $land * 10000;
            }
            elseif($resource == 'lumber')
            {
                $max = 96 * ($dominion->building_lumberyard * 50 + $dominion->getUnitPerkProductionBonus('lumber_production'));
                $max = max($max, $land * 100);
            }
            elseif($resource == 'ore')
            {
                $max = 96 * ($dominion->building_ore_mine * 60 + $dominion->getUnitPerkProductionBonus('ore_production'));
                $max = max($max, $land * 100);
            }
            elseif($resource == 'gems' or $resource == 'gem')
            {
                $max = 96 * ($dominion->building_diamond_mine * 15 + $dominion->getUnitPerkProductionBonus('gem_production'));
                if($dominion->race->name == 'Myconid')
                {
                  $max += $dominion->getUnitPerkProductionBonus('tech_production') * 10;
                }
                $max = max($max, $land * 50);

            }
            else
            {
              $max = 0;
            }

            return $max;

        }



}
