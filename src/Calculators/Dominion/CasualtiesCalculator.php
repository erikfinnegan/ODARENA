<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Spell;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;

# ODA
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
#use OpenDominion\Calculators\Dominion\RangeCalculator;

class CasualtiesCalculator
{

    /*
     * CasualtiesCalculator constructor.
     */
    public function __construct()
    {
        $this->landCalculator = app(LandCalculator::class);
        $this->populationCalculator = app(PopulationCalculator::class);
        $this->spellCalculator = app(SpellCalculator::class);
        $this->unitHelper = app(UnitHelper::class);
        $this->populationCalculator = app(PopulationCalculator::class);
        $this->improvementCalculator = app(ImprovementCalculator::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->spellDamageCalculator = app(SpellDamageCalculator::class);
    }

    # These are casualty perks that do not depend on anything else
    public function getBasicCasualtiesPerks(Dominion $dominion, string $mode = 'offensive')
    {
        # Let's be nice
        if($mode == 'offense')
        {
            $mode = 'offensive';
        }

        if($mode == 'defense')
        {
            $mode = 'defensive';
        }

        $perkValue =  0;

        # Title perks
        $perkValue += $dominion->title->getPerkMultiplier('casualties');
        $perkValue += $dominion->title->getPerkMultiplier($mode . '_casualties');

        # Faction perks
        $perkValue += $dominion->race->getPerkMultiplier('casualties');
        $perkValue += $dominion->race->getPerkMultiplier($mode . '_casualties');

        # Advancements
        $perkValue += $dominion->getTechPerkMultiplier('casualties');
        $perkValue += $dominion->getTechPerkMultiplier($mode . '_casualties');

        # Spells
        $perkValue += $dominion->getSpellPerkMultiplier('casualties');
        $perkValue += $dominion->getSpellPerkMultiplier($mode . '_casualties');

        # Improvements
        $perkValue += $dominion->getImprovementPerkMultiplier('casualties');
        $perkValue += $dominion->getImprovementPerkMultiplier($mode . '_casualties');

        # Buildings
        $perkValue += $dominion->getBuildingPerkMultiplier('casualties');
        $perkValue += $dominion->getBuildingPerkMultiplier($mode . '_casualties');

        # Deity
        $perkValue += $dominion->getDeityPerkMultiplier('casualties');
        $perkValue += $dominion->getDeityPerkMultiplier($mode . '_casualties');

        # Festering Wounds
        #if ($this->spellCalculator->isSpellActive($dominion, 'festering_wounds'))
        #{
        #    $festeringWoundsSpell = Spell::where('key', 'festering_wounds')->first();
        #    $spellPerkValues = $festeringWoundsSpell->getActiveSpellPerkValues($festeringWoundsSpell->key, 'casualties');
        #    $multiplier += ($spellPerkValues['casualties'] / 100) * $this->spellDamageCalculator->getDominionHarmfulSpellDamageModifier($dominion, null, $festeringWoundsSpell, null);
        #}

        return $perkValue;
    }

    # These are casualteis perks that do not depend on anything else
    public function getIncreasesCasualtiesPerks(Dominion $dominion, string $mode = 'offensive')
    {
        # Let's be nice
        if($mode == 'offensive')
        {
            $mode = 'offense';
        }

        if($mode == 'defensive')
        {
            $mode = 'defense';
        }

        $perkValue =  0;

        # Title perks
        $perkValue += $dominion->title->getPerkMultiplier('increases_casualties');
        $perkValue += $dominion->title->getPerkMultiplier('increases_casualties_on_' . $mode);

        # Faction perks
        $perkValue += $dominion->race->getPerkMultiplier('increases_casualties');
        $perkValue += $dominion->race->getPerkMultiplier('increases_casualties_on_' . $mode);

        # Advancements
        $perkValue += $dominion->getTechPerkMultiplier('increases_casualties');
        $perkValue += $dominion->getTechPerkMultiplier('increases_casualties_on_' . $mode);

        # Spells
        $perkValue += $dominion->getSpellPerkMultiplier('increases_casualties');
        $perkValue += $dominion->getSpellPerkMultiplier('increases_casualties_on_' . $mode);

        # Improvements
        $perkValue += $dominion->getImprovementPerkMultiplier('increases_casualties');
        $perkValue += $dominion->getImprovementPerkMultiplier('increases_casualties_on_' . $mode);

        # Buildings
        $perkValue += $dominion->getBuildingPerkMultiplier('increases_casualties');
        $perkValue += $dominion->getBuildingPerkMultiplier('increases_casualties_on_' . $mode);

        # Buildings
        $perkValue += $dominion->getDeityPerkMultiplier('increases_casualties');
        $perkValue += $dominion->getDeityPerkMultiplier('increases_casualties_on_' . $mode);


        return $perkValue;
    }

    public function getImmortalityForUnitSlot(Dominion $dominion, Dominion $enemy, $slot, array $units, bool $isOverwhelmed, float $attackingForceOP, float $targetDP, bool $isInvasionSuccessful, string $mode = 'offense')
    {

        if($slot == 'draftees')
        {
            if($dominion->race->getPerkValue('immortal_draftees'))
            {
                return 0;
            }
        }
        elseif($slot == 'peasants')
        {
            if($dominion->race->getPerkValue('immortal_peasants'))
            {
                return 0;
            }
        }
        else
        {
            # Lux does not kill anyone
            if($enemy->race->getPerkValue('does_not_kill'))
            {
                return True;
            }

            # PERK: immortal, spirit_immortal
            if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal') or $dominion->race->getUnitPerkValueForUnitSlot($slot, 'spirit_immortal'))
            {
                if(!$enemy->getSpellPerkValue('can_kill_immortal') and !$enemy->getTechPerkValue('can_kill_immortal'))
                {
                    return True;
                }
            }

            # PERK: true_immortal
            if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'true_immortal'))
            {
                return True;
            }

            # PERK: immortal_from_title
            if($titlePerkData = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_from_title', null))
            {
                $titleKey = $titlePerkData[0];
                $titlePowerRatio = $titlePerkData[1] / 100;

                if($dominion->title->key == $titleKey)
                {
                    return True;
                }
            }

            if($mode == 'offense')
            {
                # PERK: immortal_on_victory
                if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_on_victory') and $isInvasionSuccessful)
                {
                    return True;
                }
            }

            if($mode == 'defense')
            {
                # PERK: spirit_immortal
                if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'spirit_immortal') and $isInvasionSuccessful)
                {
                    return False;
                }

                # Perk: immortal_on_fending_off
                if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_on_fending_off') and !$isInvasionSuccessful)
                {
                    return True;
                }
            }
        }

        return False;

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
                    #echo '<pre>' . $unit->name . ' has enough DP to kill</pre>';
                    $dpFromUnitsThatKill += $this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense') * $defender->{"military_unit" . $unit->slot};
                }
            }

            # How much of the DP is from units that kill?
            $multiplier *= $dpFromUnitsThatKill / $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio);

            #echo "<pre>[only_dies_vs_raw_power] \$multiplier: *=" . $dpFromUnitsThatKill / $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio) . "</pre>";
        }

        #echo "<pre>\$multiplier: $multiplier</pre>";

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
            #$multiplier += $this->getCasualtiesReductionFromLand($defender, $slot, 'defense'); # Not used on defense since it don't make no sense

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
                # PERK: increases_casualties
                if($decreasesCasualties = $enemy->race->getUnitPerkValueForUnitSlot($slot, 'increases_casualties') or $decreasesCasualties = $enemy->race->getUnitPerkValueForUnitSlot($slot, 'increases_casualties_on_defense'))
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

                # PERK: increases_casualties, increases_casualties_on_defense
                if($dominion->race->getUnitPerkValueForUnitSlot($slot, 'increases_casualties') or $dominion->race->getUnitPerkValueForUnitSlot($slot, 'increases_casualties_on_defense'))
                {
                    $perkValue += ($amount / array_sum($units)) / 2;
                }
            }

            # $enemy is the attacker here, so we need to figure out the casualty increases from the invading units
            foreach($units as $slot => $amount)
            {
                # PERK: increases_casualties
                if($enemy->race->getUnitPerkValueForUnitSlot($slot, 'increases_casualties') or $enemy->race->getUnitPerkValueForUnitSlot($slot, 'increases_casualties_on_offense'))
                {
                    $perkValue += ( ($this->militaryCalculator->getOffensivePowerRaw($enemy, $dominion, $landRatio, [$slot => $amount]/*, 0, false, $isAmbush, false*/)) / $rawOpFromSentUnits ) / 2;
                }
            }

        }

        return $perkValue;
    }


}
