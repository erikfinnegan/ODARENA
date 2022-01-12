<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Spell;
use OpenDominion\Models\Unit;

use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;

class CasualtiesCalculator2
{

    /*
     * CasualtiesCalculator constructor.
     */
    public function __construct()
    {
        $this->landCalculator = app(LandCalculator::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->spellDamageCalculator = app(SpellDamageCalculator::class);
        $this->unitHelper = app(UnitHelper::class);
    }

    public function getInvasionCasualtiesRatioForUnit(Dominion $dominion, Unit $unit, Dominion $enemy = null, array $invasionData = [], string $mode = 'offense'): float
    {

        $ratios = [
            'offense' => 0.10,
            'defense' => 0.05
        ];


        # Get the base ratio (5% on defense, 10% on offense)
        $baseRatio = $ratios[$mode];

        if($mode == 'defense')
        {
            $baseRatio *= min(1, $invasionData['land_ratio']/100);
        }

        # Double if mode is offensive and invasionData result is Overwhelmed
        if($mode == 'offense' and $invasionData['result']['overwhelmed'])
        {
            $baseRatio *= 2;
        }

        # If successful on offense, casualties are only incurred on units needed to break.
        elseif($mode == 'offense' and $invasionData['result']['success'])
        {
            $baseRatio /= $invasionData['result']['opDpRatio'];
        }

        # If successful on defense, casualties are only incurred on units needed to fend off.
        elseif($mode == 'defense' and !$invasionData['result']['success'])
        {
            $baseRatio /= $invasionData['result']['opDpRatio'];
        }

        dump('$baseRatio for ' . $dominion->name . ' unit ' . $unit->name . ': ' . $baseRatio . ' (mode: ' . $mode . ')');


        $baseRatio *= $this->getOnlyDiesVsRawPowerPerkMultiplier($dominion, $unit, $enemy, $invasionData, $mode);

        # The mode as seen by the enemy
        $enemyMode = 'offense';

        if($mode == 'offense')
        {
            $enemyMode = 'defense';
        }

        $multiplier = 1;

        #dump('getBasicCasualtiesPerkMultipliers', $this->getBasicCasualtiesPerkMultipliers($dominion, $mode));
        $multiplier += $this->getBasicCasualtiesPerkMultipliers($dominion, $mode);

        #dump('getCasualtiesPerkMultipliersForThisUnit', $this->getCasualtiesPerkMultipliersForThisUnit($dominion, $enemy, $unit, $invasionData, $mode));
        $multiplier += $this->getCasualtiesPerkMultipliersForThisUnit($dominion, $enemy, $unit, $invasionData, $mode);

        #dump('getCasualtiesPerkMultipliersFromUnits', $this->getCasualtiesPerkMultipliersFromUnits($dominion, $enemy, $invasionData, $mode));
        $multiplier += $this->getCasualtiesPerkMultipliersFromUnits($dominion, $enemy, $invasionData, $mode);

        #dump('getCasualtiesPerkMultipliersFromEnemy', $this->getCasualtiesPerkMultipliersFromEnemy($enemy, $dominion, $invasionData, $enemyMode));
        $multiplier *= $this->getCasualtiesPerkMultipliersFromEnemy($enemy, $dominion, $invasionData, $enemyMode);

        $multiplier = min(2, max(0.10, $multiplier));
        #dump('multiplier', $multiplier);

        $ratio = $baseRatio * $multiplier;

        return $ratio;
    }

    public function getInvasionCasualties(Dominion $dominion, array $units, Dominion $enemy, array $invasionData = [], string $mode = 'offense'): array
    {
        #dump('$mode for ' . $dominion->name . ' is ' . $mode);
        $casualties = [];

        foreach($units as $slot => $amountSent)
        {
            $casualties[$slot] = 0;

            $unit = $dominion->race->units->filter(function ($unit) use ($slot) {
                return ($unit->slot === $slot);
            })->first();

            if(!$this->isUnitImmortal($dominion, $enemy, $unit, $invasionData, $mode))
            {
                $casualties[$slot] += (int)round($amountSent * $this->getInvasionCasualtiesRatioForUnit($dominion, $unit, $enemy, $invasionData, $mode));
            }
        }

        return $casualties;
    }

    public function isUnitImmortal(Dominion $dominion, Dominion $enemy, $unit, array $invasionData = [], string $mode = 'offense')
    {

        if(is_a($unit, 'Unit'))
        {
            $slot = (int)$unit->slot;

            # Lux does not kill anyone
            if($enemy->race->getPerkValue('does_not_kill'))
            {
                return True;
            }

            # PERK: immortal
            if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal'))
            {
                return True;
            }

            # PERK: immortal_from_wpa
            if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_from_wpa') and $this->militaryCalculator->getWizardRatio($dominion, $mode) >= $dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_from_wpa'))
            {
                return True;
            }

            # PERK: immortal_from_spa
            if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_from_spa') and $this->militaryCalculator->getSpyRatio($dominion, $mode) >= $dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_from_wpa'))
            {
                return True;
            }

            # PERK: immortal_from_title
            if($titlePerkData = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_from_title', null))
            {
                $titleKey = $titlePerkData[0];

                if($dominion->title->key == $titleKey)
                {
                    return True;
                }
            }

            if($mode == 'offense')
            {
                # PERK: immortal_on_victory
                if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_on_victory') and $invasionData['result']['success'])
                {
                    return True;
                }
            }

            if($mode == 'defense')
            {
                # Perk: immortal_on_fending_off
                if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_on_fending_off') and !$invasionData['result']['success'])
                {
                    return True;
                }
            }
        }
        elseif($unit == 'draftees')
        {
            if($dominion->race->getPerkValue('immortal_draftees'))
            {
                return True;
            }
        }
        elseif($unit == 'peasants')
        {
            if($dominion->race->getPerkValue('immortal_peasants'))
            {
                return True;
            }
        }
        elseif($unit == 'spies')
        {
            if($dominion->race->getPerkValue('immortal_spies'))
            {
                return True;
            }
        }
        elseif($unit == 'wizards')
        {
            if($dominion->race->getPerkValue('immortal_wizards'))
            {
                return True;
            }
        }
        elseif($unit == 'archmages')
        {
            return True;
        }

        return False;

    }

    # These are casualty perks that do not depend on anything else
    public function getBasicCasualtiesPerkMultipliers(Dominion $dominion, string $mode = 'offense')
    {

        $multiplier =  0;

        # Title perks
        $multiplier += $dominion->title->getPerkMultiplier('casualties');
        $multiplier += $dominion->title->getPerkMultiplier('casualties_on_' . $mode);

        # Faction perks
        $multiplier += $dominion->race->getPerkMultiplier('casualties');
        $multiplier += $dominion->race->getPerkMultiplier('casualties_on_' . $mode);

        # Advancements
        $multiplier += $dominion->getTechPerkMultiplier('casualties');
        $multiplier += $dominion->getTechPerkMultiplier('casualties_on_' . $mode);

        # Spells
        $multiplier += $dominion->getSpellPerkMultiplier('casualties');
        $multiplier += $dominion->getSpellPerkMultiplier('casualties_on_' . $mode);

        # Improvements
        $multiplier += $dominion->getImprovementPerkMultiplier('casualties');
        $multiplier += $dominion->getImprovementPerkMultiplier('casualties_on_' . $mode);

        # Buildings
        $multiplier += $dominion->getBuildingPerkMultiplier('casualties');
        $multiplier += $dominion->getBuildingPerkMultiplier('casualties_on_' . $mode);

        # Deity
        $multiplier += $dominion->getDeityPerkMultiplier('casualties');
        $multiplier += $dominion->getDeityPerkMultiplier('casualties_on_' . $mode);

        return $multiplier;
    }

    public function getCasualtiesPerkMultipliersForThisUnit(Dominion $dominion, Dominion $enemy = null, Unit $unit, array $invasionData = [], $mode = 'offense')
    {
        $multiplier = 0;

        $multiplier += $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, 'casualties');
        $multiplier += $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, ('casualties_on_' . $mode));

        $multiplier += $this->getCasualtiesPerkMultipliersFromLand($dominion, $enemy, $unit, $invasionData, $mode);

        if($wpaPerk = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, 'casualties_from_wizard_ratio'))
        {
            $multiplier += ($this->militaryCalculator->getWizardRatio($dominion, $mode) * $wpaPerk) / 100;
        }

        if($spaPerk = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, 'casualties_from_spy_ratio'))
        {
            $multiplier += ($this->militaryCalculator->getSpyRatio($dominion, $mode) * $spaPerk) / 100;
        }

        if($titlePerkData = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, 'casualties_from_title', null))
        {
            $titleKey = $titlePerkData[0];
            $titlePowerRatio = $titlePerkData[1] / 100;

            if($dominion->title->key == $titleKey)
            {
                $multiplier += $titlePowerRatio;
            }
        }

        if($invasionData['result']['success'])
        {
            $multiplier += $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, 'casualties_on_victory') / 100;
        }

        return $multiplier;
    }

    # These are casualty perk multipliers received from the enemy.
    # So in this context, $dominion is the enemy and $enemy is the dominion whose enemy the $dominion is. Crystal clear.
    public function getCasualtiesPerkMultipliersFromEnemy(Dominion $dominion, Dominion $enemy, array $invasionData = [], string $enemyMode = 'defense')
    {

        $multiplier = 1;

        # Title perks
        $multiplier += $dominion->title->getPerkMultiplier('increases_enemy_casualties');
        $multiplier += $dominion->title->getPerkMultiplier('increases_enemy_casualties_on_' . $enemyMode);

        # Faction perks
        $multiplier += $dominion->race->getPerkMultiplier('increases_enemy_casualties');
        $multiplier += $dominion->race->getPerkMultiplier('increases_enemy_casualties_on_' . $enemyMode);

        # Advancements
        $multiplier += $dominion->getTechPerkMultiplier('increases_enemy_casualties');
        $multiplier += $dominion->getTechPerkMultiplier('increases_enemy_casualties_on_' . $enemyMode);

        # Spells: apply spell damage modifier
        $multiplier += $dominion->getSpellPerkMultiplier('increases_enemy_casualties') * $this->spellDamageCalculator->getDominionHarmfulSpellDamageModifier($enemy);
        $multiplier += $dominion->getSpellPerkMultiplier('increases_enemy_casualties_on_' . $enemyMode) * $this->spellDamageCalculator->getDominionHarmfulSpellDamageModifier($enemy);

        # Improvements
        $multiplier += $dominion->getImprovementPerkMultiplier('increases_enemy_casualties');
        $multiplier += $dominion->getImprovementPerkMultiplier('increases_enemy_casualties_on_' . $enemyMode);

        # Buildings
        $multiplier += $dominion->getBuildingPerkMultiplier('increases_enemy_casualties');
        $multiplier += $dominion->getBuildingPerkMultiplier('increases_enemy_casualties_on_' . $enemyMode);

        # Deity
        $multiplier += $dominion->getDeityPerkMultiplier('increases_enemy_casualties');
        $multiplier += $dominion->getDeityPerkMultiplier('increases_enemy_casualties_on_' . $enemyMode);

        foreach ($dominion->race->units as $unit)
        {
            # increases_enemy_casualties, increases_enemy_casualties_on_$enemyMode
            if($dominion->race->getUnitPerkValueForUnitSlot($unit->slot, 'increases_enemy_casualties') or $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, ('increases_enemy_casualties_on_' . $enemyMode)))
            {
                if($enemyMode == 'defense')
                {
                    $multiplier += ($dominion->{'military_unit' . $unit->slot} / array_sum($invasionData['defender']['unitsDefending'])) / 2;
                }

                if($enemyMode == 'offense')
                {
                    $multiplier += ($invasionData['attacker']['unitsSent'][$slot] / array_sum($invasionData['attacker']['unitsSent'])) / 2;
                }

            }
        }


        return min(2, (max(0.10, $multiplier)));
    }

    public function getCasualtiesPerkMultipliersFromLand(Dominion $dominion, Dominion $enemy = null, Unit $unit, array $invasionData = [], string $mode = 'offense')
    {
        $multiplier = 0;

        if($mode == 'offense')
        {
            if($landPerkData = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, "casualties_on_{$mode}_vs_land"))
            {
                $landType = (string)$landPerkData[0];
                $perPercentage = (float)$landPerkData[1];
                $max = (float)$landPerkData[2] / 100;

                # How much land does the enemy have of that land type?
                $enemyLandOfLandType = $enemy->{'land_' . $landType};
                $enemyTotalLand = $this->landCalculator->getTotalLand($enemy);
                $enemyLandTypePercentage = $enemyLandOfLandType / $enemyTotalLand;

                $multiplier += max($max, $enemyLandTypePercentage * $perPercentage);
            }
        }
        elseif($mode == 'defense')
        {
            if($landPerkData = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, "casualties_on_{$mode}_from_land"))
            {
                $landType = (string)$landPerkData[0];
                $perPercentage = (float)$landPerkData[1];
                $max = (float)$landPerkData[2] / 100;

                # How much land does the dominion have of that land type?
                $landOfLandType = $dominion->{'land_' . $landType};
                $totalLand = $this->landCalculator->getTotalLand($dominion);
                $landTypePercentage = $landOfLandType / $totalLand;

                $multiplier += max($max, $enemyLandTypePercentage * $perPercentage);
            }
        }

        return $multiplier;
    }

    public function getCasualtiesPerkMultipliersFromUnits(Dominion $dominion, Dominion $enemy, array $invasionData, string $mode = 'offense')
    {

        $multiplier = 0;

        $unitsSent = $invasionData['attacker']['unitsSent'];
        $unitsDefending = $invasionData['defender']['unitsDefending'];

        $units = $invasionData['attacker']['unitsSent'];

        if($mode == 'defense')
        {
            $units = $unitsDefending;
        }

        #$rawOpFromUnitsSent = $this->militaryCalculator->getOffensivePowerRaw($dominion, $enemy, $invasionData['land_ratio'], $unitsSent);
        #$rawDpFromUnitsDefending = $this->militaryCalculator->getDefensivePowerRaw($enemy, $dominion, $invasionData['land_ratio']);

        foreach($units as $slot => $amount)
        {
            if(in_array($slot, [1,2,3,4]))
            {
                if($dominion->race->getUnitPerkValueForUnitSlot($slot, 'reduces_casualties') or $dominion->race->getUnitPerkValueForUnitSlot($slot, ('reduces_casualties_on_' . $mode)))
                {
                    $multiplier -= ($amount / array_sum($units)) / 2;
                }

                # PERK: increases_own_casualties, increases_own_casualties_on_offense
                if($dominion->race->getUnitPerkValueForUnitSlot($slot, 'increases_own_casualties') or $dominion->race->getUnitPerkValueForUnitSlot($slot, ('increases_own_casualties_on_' . $mode)))
                {
                    $multiplier += ($amount / array_sum($units)) / 2;
                }
            }
        }

        return $multiplier;

    }

    public function getOnlyDiesVsRawPowerPerkMultiplier(Dominion $dominion, Unit $unit, Dominion $enemy, array $invasionData = [], string $mode = 'offense'): float
    {
        $multiplier = 1;

        $attackingUnits = $invasionData['attacker']['unitsSent'];
        $defendingUnits = $invasionData['defender']['unitsDefending'];

        if($mode == 'offense')
        {
            $rawDp = $this->militaryCalculator->getDefensivePowerRaw($enemy, $dominion, $invasionData['land_ratio'], $defendingUnits, 0, false, false, false, $attackingUnits, true);

            if($minPowerToKill = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, 'only_dies_vs_raw_power'))
            {
                $powerRatioFromKillingUnits = 0;

                foreach($defendingUnits as $slot => $amount)
                {
                    # Get the defending units
                    $defendingUnit = $enemy->race->units->filter(function ($defendingUnit) use ($slot) {
                        return ($defendingUnit->slot === $slot);
                    })->first();

                    $defendingUnitDp = $this->militaryCalculator->getUnitPowerWithPerks($enemy, $dominion, $invasionData['land_ratio'], $defendingUnit, 'defense', null, $defendingUnits, $attackingUnits);

                    # See if it has enough DP to kill
                    if($defendingUnitDp >= $minPowerToKill)
                    {
                        # How much of the raw DP came from this unit?
                        $powerRatioFromKillingUnits += ($amount * $this->militaryCalculator->getUnitPowerWithPerks($enemy, $dominion, $invasionData['land_ratio'], $unit, 'defense')) / $rawDp;
                    }
                }

                $multiplier += $powerRatioFromKillingUnits;
            }
        }

        if($mode == 'defense')
        {
            $rawOp = $this->militaryCalculator->getOffensivePowerRaw($enemy, $dominion, $invasionData['land_ratio'], $attackingUnits);

            if($minPowerToKill = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, 'only_dies_vs_raw_power'))
            {
                $powerRatioFromKillingUnits = 0;

                foreach($attackingUnits as $slot => $amount)
                {
                    # Get the defending units
                    $attackingUnit = $enemy->race->units->filter(function ($attackingUnit) use ($slot) {
                        return ($attackingUnit->slot === $slot);
                    })->first();

                    $unitOp = $this->militaryCalculator->getUnitPowerWithPerks($enemy, $dominion, $invasionData['land_ratio'], $attackingUnit, 'defense', null, $attackingUnits, $defendingUnits);

                    # See if it has enough DP to kill
                    if($unitOp >= $minPowerToKill)
                    {
                        # How much of the raw DP came from this unit?
                        $powerRatioFromKillingUnits += ($amount * $unitOp) / $rawDp;
                    }
                }

                $multiplier += $powerRatioFromKillingUnits;
            }
        }

        return max(0, $multiplier);

    }


}
