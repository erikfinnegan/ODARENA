<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Spell;

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

    public function getInvasionCasualtiesRatioForUnit(Dominion $dominion, Unit $unit, Dominion $enemy = null, array $invasionResult = [], string $mode = 'offense'): float
    {

        $ratios = [
            'offense' => 0.10,
            'defense' => 0.05
        ];

        # Get the base ratio (5% on defense, 10% on offense)
        $baseRatio = $ratios[$mode];

        # Double if mode is offensive and invasionResulßt is Overwhelmed
        if($mode == 'offense' and $invasionResult['result']['overwhelmed'])
        {
            $baseRatio *= 2;
        }

        # The mode as seen by the enemy
        $enemyMode = 'offense';

        if($mode == 'offense')
        {
            $enemyMode = 'defense';
        }

        $landRatio = $this->rangeCalculator->getDominionRange($dominion, $enemy) / 100;

        $multiplier = 1;

        $multiplier += $this->getBasicCasualtiesPerkMultipliers($dominion, $mode);
        $multiplier += $this->getCasualtiesPerkMultipliersForThisUnit($dominion, $unit, $invasionResult, $mode);

        $multiplier *= $this->getCasualtiesPerkMultipliersFromEnemy($enemy, $dominion, $mode);

        $multiplier = min(2, max(0.10, $multiplier));

        $ratio = $baseRatio * $multiplier;

        return $ratio;
    }

    public function getInvasionCasualties(Dominion $dominion, array $units, Dominion $enemy = null, array $enemyUnits = [], array $invasionResult = [], string $mode = 'offense'): array
    {
        $casualties = [];

        foreach($units as $slot => $amountSent)
        {
            $casualties[$slot] = 0;

            $unit = $selectedDominion->race->units->filter(function ($unit) use ($unitSlot) {
                return ($unit->slot === $unitSlot);
            })->first();

            if(!$this->isUnitImmortal($dominion, $enemy, $unit, $invasionResult, $mode))
            {
                $casualties[$slot] += $amountSent * $this->getInvasionCasualtiesRatioForUnit($dominion, $unit, $enemy, $enemyUnits, $invasionResult);
            }
        }

        return $casualties;
    }

    public function isUnitImmortal(Dominion $dominion, Dominion $enemy = null, Unit $unit, array $invasionResult = [], string $mode = 'offense')
    {

        if($slot == 'draftees')
        {
            if($dominion->race->getPerkValue('immortal_draftees'))
            {
                return True;
            }
        }
        elseif($slot == 'peasants')
        {
            if($dominion->race->getPerkValue('immortal_peasants'))
            {
                return True;
            }
        }
        elseif($slot == 'spies')
        {
            if($dominion->race->getPerkValue('immortal_spies'))
            {
                return True;
            }
        }
        elseif($slot == 'wizards')
        {
            if($dominion->race->getPerkValue('immortal_wizards'))
            {
                return True;
            }
        }
        elseif($slot == 'archmages')
        {
            return True;
        }
        elseif(in_array($slot, [1,2,3,4]))
        {
            $slot = (int)$slot;

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
                if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_on_victory') and $invasionResult['result']['success'])
                {
                    return True;
                }
            }

            if($mode == 'defense')
            {
                # Perk: immortal_on_fending_off
                if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_on_fending_off') and !$invasionResult['result']['success'])
                {
                    return True;
                }
            }
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

    # These are casualty perk multipliers received from the enemy.
    # So in this context, $dominion is the enemy and $enemy is the dominion whose enemy the $dominion is. Crystal clear.
    public function getCasualtiesPerkMultipliersFromEnemy(Dominion $dominion, Dominion $enemy, string $mode = 'offense')
    {

        $multiplier = 1;

        # Title perks
        $multiplier += $dominion->title->getPerkMultiplier('increases_enemy_casualties');
        $multiplier += $dominion->title->getPerkMultiplier('increases_enemy_casualties_on_' . $mode);

        # Faction perks
        $multiplier += $dominion->race->getPerkMultiplier('increases_enemy_casualties');
        $multiplier += $dominion->race->getPerkMultiplier('increases_enemy_casualties_on_' . $mode);

        # Advancements
        $multiplier += $dominion->getTechPerkMultiplier('increases_enemy_casualties');
        $multiplier += $dominion->getTechPerkMultiplier('increases_enemy_casualties_on_' . $mode);

        # Spells
        $multiplier += $dominion->getSpellPerkMultiplier('increases_enemy_casualties');
        $multiplier += $dominion->getSpellPerkMultiplier('increases_enemy_casualties_on_' . $mode);

        # Improvements
        $multiplier += $dominion->getImprovementPerkMultiplier('increases_enemy_casualties');
        $multiplier += $dominion->getImprovementPerkMultiplier('increases_enemy_casualties_on_' . $mode);

        # Buildings
        $multiplier += $dominion->getBuildingPerkMultiplier('increases_enemy_casualties');
        $multiplier += $dominion->getBuildingPerkMultiplier('increases_enemy_casualties_on_' . $mode);

        # Deity
        $multiplier += $dominion->getDeityPerkMultiplier('increases_enemy_casualties');
        $multiplier += $dominion->getDeityPerkMultiplier('increases_enemy_casualties_on_' . $mode);

        return $perkValue;
    }

    public function getCasualtiesPerkMultipliersForThisUnit(Dominion $dominion, Unit $unit, array $invasionResult = [], $mode = 'offense')
    {
        $multiplier = 0;

        $multiplier += $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, 'casualties');
        $multiplier += $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, ('casualties_on_' . $mode));

        if($wpaPerk = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, 'casualties_from_wizard_ratio'))
        {
            $multiplier += ($this->militaryCalculator->getWizardRatio($dominion, $mode) * $wpaPerk) / 100;
        }

        if($spaPerk = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, 'casualties_from_spy_ratio'))
        {
            $multiplier += ($this->militaryCalculator->getSpyRatio($dominion, $mode) * $spaPerk) / 100;
        }

        return $multiplier;
    }


    # Round 52: Version 1.1
    public function getOffensiveCasualtiesMultiplierForUnitSlot(Dominion $attacker, Dominion $defender, int $slot, array $units, int $landRatio, bool $isOverwhelmed, float $attackingForceOP, float $targetDP, bool $isInvasionSuccessful): float
    {
        #echo "<pre>Checking attacker's slot {$slot}.</pre>";
        if($this->getImmortalityForUnitSlot($attacker, $defender, $slot, $units, $isOverwhelmed, $attackingForceOP, $targetDP, $isInvasionSuccessful, 'offense'))
        {
            return 0;
        }

        $multiplier = 1.0;

        # PERK: only_dies_vs_raw_power
        if ($minPowerToKill = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'only_dies_vs_raw_power'))
        {
            $dpFromUnitsThatKill = 0;

            # Get the raw DP of each unit of $target.
            foreach ($defender->race->units as $unit)
            {
                # If the raw DP on the unit is enough, add it to $dpFromUnitsThatKill.
                if($this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense') >= $minPowerToKill)
                {
                    echo '<pre>' . $unit->name . ' has enough DP to kill</pre>';
                    $dpFromUnitsThatKill += $this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense') * $defender->{"military_unit" . $unit->slot};
                }
            }

            # How much of the DP is from units that kill?
            $multiplier *= $dpFromUnitsThatKill / $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio);

            echo "<pre>[only_dies_vs_raw_power] \$multiplier: *=" . $dpFromUnitsThatKill / $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio) . "</pre>";
        }

        echo "<pre>\$multiplier: $multiplier</pre>";

        if((float)$multiplier === 0.0)
        {
            return $multiplier;
        }

        # PERK: fewer_casualties_from_title
        if($titlePerkData = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'fewer_casualties_from_title', null))
        {
            $titleKey = $titlePerkData[0];
            $titlePowerRatio = $titlePerkData[1] / 100;

            if($attacker->title->key == $titleKey)
            {
                $multiplier -= $titlePowerRatio;
            }
        }

        $multiplier += $attacker->race->getUnitPerkValueForUnitSlot($slot, 'casualties') / 100;
        $multiplier += $attacker->race->getUnitPerkValueForUnitSlot($slot, 'casualties_on_offense') / 100;

        if($isInvasionSuccessful)
        {
            $multiplier += $attacker->race->getUnitPerkValueForUnitSlot($slot, 'casualties_on_victory') / 100;
        }

        $multiplier += $this->getBasicCasualtiesPerks($attacker, 'offensive');
        $multiplier += $this->getIncreasesCasualtiesPerks($defender, 'defense');

        $multiplier += $this->getCasualtiesReductionVersusLand($attacker, $defender, $slot, 'offense');
        $multiplier += $this->getCasualtiesReductionFromLand($attacker, $slot, 'offense');

        $multiplier += $this->getUnitCasualtiesPerk($attacker, $defender, $units, $landRatio, 'offense');
        $multiplier += $this->getUnitCasualtiesPerk($defender, $attacker, $units, $landRatio, 'defense');

        $multiplier = max(0.10, $multiplier);

        return $multiplier;

    }

    # Round 51: Version 1.1
    public function getDefensiveCasualtiesMultiplierForUnitSlot(Dominion $defender, Dominion $attacker, $slot, array $units, int $landRatio, bool $isOverwhelmed, float $attackingForceOP, float $targetDP, bool $isInvasionSuccessful): float
    {

        $excludedSlots = ['draftees', 'peasants', 'spies', 'wizards', 'archmages'];

        if($this->getImmortalityForUnitSlot($defender, $attacker, $slot, $units, $isOverwhelmed, $attackingForceOP, $targetDP, $isInvasionSuccessful, 'defense'))
        {
            return 0;
        }

        $multiplier = 1;

        if(!in_array($slot, $excludedSlots))
        {
            # PERK: only_dies_vs_raw_power
            if ($minPowerToKill = $defender->race->getUnitPerkValueForUnitSlot($slot, 'only_dies_vs_raw_power'))
            {
                $opFromUnitsThatKill = 0;

                # Get the raw OP of each unit of $attacker.
                foreach ($units as $slot => $amount)
                {
                    if(!in_array($slot, $excludedSlots))
                    {
                        $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                            return ($unit->slot === $slot);
                        })->first();

                        # If the raw OP on the unit is enough, add it to $opFromUnitsThatKill.
                        if($this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense') >= $minPowerToKill)
                        {
                            $opFromUnitsThatKill += $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense') * $amount;
                        }
                    }
                }

                # How much of the OP is from units that kill?
                $multiplier *= $opFromUnitsThatKill / $this->militaryCalculator->getOffensivePowerRaw($attacker, $defender, $landRatio, $units);
            }

            # PERK: fewer_casualties_from_title
            if($titlePerkData = $defender->race->getUnitPerkValueForUnitSlot($slot, 'fewer_casualties_from_title', null))
            {
                $titleKey = $titlePerkData[0];
                $titlePowerRatio = $titlePerkData[1] / 100;

                if($defender->title->key == $titleKey)
                {
                    $multiplier -= $titlePowerRatio;
                }
            }

            $multiplier += $this->getCasualtiesReductionVersusLand($defender, $attacker, $slot, 'defense');
            $multiplier += $this->getCasualtiesReductionFromLand($defender, $slot, 'defense'); # Not used on defense since it don't make no sense

            $multiplier += $defender->race->getUnitPerkValueForUnitSlot($slot, 'casualties') / 100;
            $multiplier += $defender->race->getUnitPerkValueForUnitSlot($slot, 'casualties_on_defense') / 100;
        }

        if($multiplier === 0)
        {
            return $multiplier;
        }

        $multiplier += $this->getBasicCasualtiesPerks($defender, 'defensive');
        $multiplier += $this->getIncreasesCasualtiesPerks($attacker, 'offense');

        $multiplier += $this->getUnitCasualtiesPerk($defender, $attacker, $units, $landRatio, 'defense');
        $multiplier += $this->getUnitCasualtiesPerk($attacker, $defender, $units, $landRatio, 'offense');

        $multiplier = max(0.10, $multiplier);

        return $multiplier;

    }

    public function getCasualtiesPerkMultipliersFromLand(Dominion $dominion, Dominion $enemy = null, Unit $unit, string $mode = 'offense')
    {
        $multiplier = 0;

        if($mode == 'offense')
        {
            if($landPerkData = $dominion->race->getUnitPerkValueForUnitSlot($slot, "casualties_on_{$mode}_vs_land"))
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
            if($landPerkData = $dominion->race->getUnitPerkValueForUnitSlot($slot, "casualties_on_{$mode}_from_land"))
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

    /**
     * @param Dominion $dominion
     * @param Unit $unit
     * @return float
     */
    protected function getCasualtiesReductionFromLand(Dominion $dominion, int $slot = NULL, string $powerType): float
    {
        if ($slot == NULL)
        {
            return 0;
        }

        $landPerkData = $dominion->race->getUnitPerkValueForUnitSlot($slot, "fewer_casualties_{$powerType}_from_land", null);

        if (!$landPerkData)
        {
            return 0;
        }

        $landType = $landPerkData[0];
        $ratio = (float)$landPerkData[1];
        $max = (float)$landPerkData[2];

        $totalLand = $this->landCalculator->getTotalLand($dominion);
        $landPercentage = ($dominion->{"land_{$landType}"} / $totalLand) * 100;

        $powerFromLand = $landPercentage / $ratio;
        $powerFromPerk = min($powerFromLand, $max)/100;

        return $powerFromPerk;
    }


    protected function getCasualtiesReductionVersusLand(Dominion $attacker, Dominion $target, int $slot = NULL, string $powerType): float
    {
        if ($target === null or $slot == NULL)
        {
            return 0;
        }

        $versusLandPerkData = $attacker->race->getUnitPerkValueForUnitSlot($slot, "fewer_casualties_{$powerType}_vs_land", null);

        if(!$versusLandPerkData)
        {
            return 0;
        }

        $landType = $versusLandPerkData[0];
        $ratio = (float)$versusLandPerkData[1];
        $max = (float)$versusLandPerkData[2];

        $totalLand = $this->landCalculator->getTotalLand($target);
        $landPercentage = ($target->{"land_{$landType}"} / $totalLand) * 100;

        $powerFromLand = $landPercentage / $ratio;

        $powerFromPerk = min($powerFromLand, $max)/100;

        return $powerFromPerk;
    }

    protected function getUnitCasualtiesPerk(Dominion $dominion, Dominion $enemy, array $units, float $landRatio, string $mode): float
    {
        $perkValue = 0;

        if($mode == 'offense')
        {
            $rawOpFromSentUnits = $this->militaryCalculator->getOffensivePowerRaw($dominion, $enemy, $landRatio, $units/*, []*/);
            $rawDpFromHomeUnits = $this->militaryCalculator->getDefensivePowerRaw($enemy, $dominion, $landRatio/*, null, 0, false, $isAmbush, false*/);
            $defenderUnitsHome = [
                    1 => $enemy->military_unit1,
                    2 => $enemy->military_unit2,
                    3 => $enemy->military_unit3,
                    4 => $enemy->military_unit4,
                ];

            # $dominion is the attacker here, so we need to figure out the casualty reductions from the invading units
            foreach($units as $slot => $amount)
            {
                # PERK: reduces_casualties, reduces_casualties_on_offense
                if($dominion->race->getUnitPerkValueForUnitSlot($slot, 'reduces_casualties') or $dominion->race->getUnitPerkValueForUnitSlot($slot, 'reduces_casualties_on_offense'))
                {
                    $perkValue -= ($amount / array_sum($units)) / 2;
                }

                # PERK: increases_own_casualties, increases_own_casualties_on_offense
                if($dominion->race->getUnitPerkValueForUnitSlot($slot, 'increases_own_casualties') or $dominion->race->getUnitPerkValueForUnitSlot($slot, 'increases_own_casualties_on_offense'))
                {
                    $perkValue += ($amount / array_sum($units)) / 2;
                }
            }

            # $enemy is the defender here, so we need to figure out the casualty increases from the defending units
            foreach($defenderUnitsHome as $slot => $amount)
            {
                # PERK: increases_enemy_casualties
                if($decreasesCasualties = $enemy->race->getUnitPerkValueForUnitSlot($slot, 'increases_enemy_casualties') or $decreasesCasualties = $enemy->race->getUnitPerkValueForUnitSlot($slot, 'increases_enemy_casualties_on_defense'))
                {
                    $perkValue += ( ($this->militaryCalculator->getDefensivePowerRaw($enemy, $dominion, $landRatio, [$slot => $amount]/*, 0, false, $isAmbush, false*/)) / $rawDpFromHomeUnits ) / 2;
                }
            }

        }

        if($mode == 'defense')
        {
            $rawDpFromHomeUnits = $this->militaryCalculator->getDefensivePowerRaw($dominion, $enemy, $landRatio/*, null, 0, false, $isAmbush, false*/);
            $rawOpFromSentUnits = $this->militaryCalculator->getOffensivePowerRaw($enemy, $dominion, $landRatio, $units/*, []*/);
            $defenderUnitsHome = [
                    1 => $dominion->military_unit1,
                    2 => $dominion->military_unit2,
                    3 => $dominion->military_unit3,
                    4 => $dominion->military_unit4,
                ];

            # $dominion is the defender here, so we need to figure out the casualty reductions from the defending units
            foreach($defenderUnitsHome as $slot => $amount)
            {
                # PERK: reduces_casualties, reduces_casualties_on_defense
                if($dominion->race->getUnitPerkValueForUnitSlot($slot, 'reduces_casualties') or $dominion->race->getUnitPerkValueForUnitSlot($slot, 'reduces_casualties_on_defense'))
                {
                    $perkValue -= ($amount / array_sum($units)) / 2;
                }

                # PERK: increases_own_casualties, increases_own_casualties_on_defense
                if($dominion->race->getUnitPerkValueForUnitSlot($slot, 'increases_own_casualties') or $dominion->race->getUnitPerkValueForUnitSlot($slot, 'increases_own_casualties_on_defense'))
                {
                    $perkValue += ($amount / array_sum($units)) / 2;
                }
            }

            # $enemy is the attacker here, so we need to figure out the casualty increases from the invading units
            foreach($units as $slot => $amount)
            {
                # PERK: increases_enemy_casualties
                if($enemy->race->getUnitPerkValueForUnitSlot($slot, 'increases_enemy_casualties') or $enemy->race->getUnitPerkValueForUnitSlot($slot, 'increases_enemy_casualties_on_offense'))
                {
                    $perkValue += ( ($this->militaryCalculator->getOffensivePowerRaw($enemy, $dominion, $landRatio, [$slot => $amount]/*, 0, false, $isAmbush, false*/)) / $rawOpFromSentUnits ) / 2;
                }
            }

        }

        return $perkValue;
    }


}
