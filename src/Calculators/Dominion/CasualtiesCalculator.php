<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;

# ODA
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
#use OpenDominion\Calculators\Dominion\RangeCalculator;

class CasualtiesCalculator
{
    /** @var LandCalculator */
    protected $landCalculator;

    /** @var PopulationCalculator */
    private $populationCalculator;

    /** @var SpellCalculator */
    private $spellCalculator;

    /** @var UnitHelper */
    protected $unitHelper;

    /** @var MilitaryCalculator */
    protected $militaryCalculator;

    /** @var RangeCalculator */
    protected $rangeCalculator;

    /**
     * CasualtiesCalculator constructor.
     *
     * @param LandCalculator $landCalculator
     * @param PopulationCalculator $populationCalculator
     * @param SpellCalculator $spellCalculator
     * @param UnitHelper $unitHelper
     * @param MilitaryCalculator $militaryCalculator
     * @param RangeCalculator $rangeCalculator
     */
    public function __construct(
        LandCalculator $landCalculator,
        PopulationCalculator $populationCalculator,
        SpellCalculator $spellCalculator,
        UnitHelper $unitHelper,
        ImprovementCalculator $improvementCalculator,
        MilitaryCalculator $militaryCalculator)
    {
        $this->landCalculator = $landCalculator;
        $this->populationCalculator = $populationCalculator;
        $this->spellCalculator = $spellCalculator;
        $this->unitHelper = $unitHelper;
        $this->populationCalculator = $populationCalculator;
        $this->improvementCalculator = $improvementCalculator;
        $this->militaryCalculator = $militaryCalculator;
    }

    /**
     * Get the offensive casualty multiplier for a dominion for a specific unit
     * slot.
     *
     * @param Dominion $dominion
     * @param Dominion $target
     * @param int $slot
     * @param array $units Units being sent out on invasion
     * @param float $landRatio
     * @param bool $isOverwhelmed
     * @param bool $attackingForceOP
     * @param bool $targetDP
     * @return float
     */
    public function getOffensiveCasualtiesMultiplierForUnitSlot(Dominion $dominion, Dominion $target, int $slot, array $units, float $landRatio, bool $isOverwhelmed, float $attackingForceOP, float $targetDP): float
    {
        $multiplier = 1;

        # CHECK IMMORTALITY

        // Check if unit has fixed casualties first, so we can skip all other checks
        if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'fixed_casualties') !== 0)
        {
            return 1;
        }

        // If you are fighting against a does_not_kill race (Lux)
        # This means that OFFENSIVE CASUALTIES are zero when INVADING a Lux.
        if($target->race->getPerkValue('does_not_kill') == 1)
        {
          $multiplier = 0;
        }

        // Then check immortality, so we can skip the other remaining checks if we indeed have immortal units, since
        // casualties will then always be 0 anyway

        // Immortality never triggers upon being overwhelmed
        if (!$isOverwhelmed)
        {
            // General "Almost never dies" type of immortality.
            if ((bool)$dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal'))
            {
                $multiplier = 0;
            }

            // True immortality: only dies when overwhelmed.
            if ((bool)$dominion->race->getUnitPerkValueForUnitSlot($slot, 'true_immortal'))
            {
                // For now the same as SPUD-style immortal, but separate in code for future usage.
                $multiplier = 0;
            }

            // Range-based immortality
            if (($immortalVsLandRange = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_vs_land_range')) !== 0)
            {
                if ($landRatio >= ($immortalVsLandRange / 100))
                {
                    $multiplier = 0;
                }
            }

            // Race perk-based immortality
            if ($this->isImmortalVersusRacePerk($dominion, $target, $slot))
            {
                $multiplier = 0;
            }
        }
        # END CHECK IMMORTALITY

        # CHECK ONLY DIES VS X RAW POWER
        if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'only_dies_vs_raw_power') !== 0)
        {
            $minPowerToKill = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'only_dies_vs_raw_power');
            $dpFromUnitsThatKill = 0;

            # Get the raw DP of each unit of $target.
            foreach ($target->race->units as $unit)
            {
                # If the raw DP on the unit is enough, add it to $dpFromUnitsThatKill.
                if($this->militaryCalculator->getUnitPowerWithPerks($target, $dominion, $landRatio, $unit, 'defense') >= $minPowerToKill)
                {
                  $dpFromUnitsThatKill += $this->militaryCalculator->getUnitPowerWithPerks($target, $dominion, $landRatio, $unit, 'defense') * $target->{"military_unit" . $unit->slot};
                }
            }

            # How much of the DP is from units that kill?
            $multiplier = $dpFromUnitsThatKill / $this->militaryCalculator->getDefensivePowerRaw($target);

            #dd($multiplier);

            #$multiplier = $dpFromUnitsThatKillRatio;
        }
        # END CHECK ONLY DIES VS X RAW POWER

        # CHECK UNIT AND RACIAL CASUALTY MODIFIERS

        if ($multiplier != 0)
        {

            // Non-Unit bonuses
            #$multiplier = $multiplier; -- Removed to not cause issues with $multiplier set by only_dies_vs_raw_power perk.

            # Shrines
            $multiplier -= $this->getOffensiveCasualtiesReductionFromShrines($dominion);

            # Land-based reductions
            $multiplier -= $this->getCasualtiesReductionFromLand($dominion, $slot, 'offense');
            $multiplier -= $this->getCasualtiesReductionVersusLand($dominion, $target, $slot, 'offense');

            # Orc and Black Orc spell: increases casualties by 10%.
            if ($this->spellCalculator->isSpellActive($dominion, 'bloodrage'))
            {
              $multiplier += 0.10;
            }
            # Norse spell: increases casualties by 15%.
            if ($this->spellCalculator->isSpellActive($dominion, 'fimbulwinter'))
            {
              $multiplier += 0.15;
            }
            # Troll and Lizardfolk spell: decreases casualties by 25%.
            if ($this->spellCalculator->isSpellActive($dominion, 'regeneration'))
            {
              $multiplier -= 0.25;
            }
            # Invasion Spell: Unhealing Wounds
            if ($this->spellCalculator->isSpellActive($dominion, 'unhealing_wounds'))
            {
              $multiplier += 0.50;
            }

            // Techs
            $multiplier -= $dominion->getTechPerkMultiplier('fewer_casualties_offense');

            # Infirmary
            $multiplier -= $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'infirmary');

            // Unit bonuses (multiplicative with non-unit bonuses)

            # Unit Perk: Fewer Casualties
            $multiplier -= ($dominion->race->getUnitPerkValueForUnitSlot($slot, ['fewer_casualties', 'fewer_casualties_offense']) / 100);

            # Unit Perk: Reduce Combat Losses
            $unitsSentPerSlot = [];
            $unitsSentRCLSlot = null;
            $reducedCombatLossesMultiplierAddition = 0;

            // todo: inefficient to do run this code per slot. needs refactoring
            foreach ($dominion->race->units as $unit) {
                $slot = $unit->slot;

                if (!isset($units[$slot])) {
                    continue;
                }
                $unitsSentPerSlot[$slot] = $units[$slot];

                if ($unit->getPerkValue('reduce_combat_losses') !== 0) {
                    $unitsSentRCLSlot = $slot;
                }
            }

            // We have a unit with RCL!
            if ($unitsSentRCLSlot !== null) {
                $totalUnitsSent = array_sum($unitsSentPerSlot);

                $reducedCombatLossesMultiplierAddition += (($unitsSentPerSlot[$unitsSentRCLSlot] / $totalUnitsSent) / 2);
            }

            # Apply RCL do uBM.
            $multiplier -= $reducedCombatLossesMultiplierAddition;

            // Absolute cap at 90% reduction.
            $multiplier = max(0.10, $multiplier);
        }

        # END CHECK UNIT AND RACIAL CASUALTY MODIFIERS

        return $multiplier;
    }

    /**
     * Get the defensive casualty multiplier for a dominion for a specific unit
     * slot.
     *
     * @param Dominion $dominion
     * @param Dominion $attacker
     * @param int|null $slot Null is for non-racial units and thus used as draftees casualties multiplier
     * @return float
     */
    public function getDefensiveCasualtiesMultiplierForUnitSlot(Dominion $dominion, Dominion $attacker, ?int $slot, array $units, float $landRatio): float
    {
        $multiplier = 1;

        // First check immortality, so we can skip the other remaining checks if we indeed have immortal units, since
        // casualties will then always be 0 anyway

        // Only military units with a slot number could be immortal
        if ($slot !== null)
        {
            // Global immortality
            if ((bool)$dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal'))
            {
                if (!$this->spellCalculator->isSpellActive($attacker, 'crusade'))
                {
                    $multiplier = 0;
                }
            }
            if ((bool)$dominion->race->getUnitPerkValueForUnitSlot($slot, 'true_immortal'))
            {
                // Note: true_immortal is used for non-SPUD races to be exempt from Crusade.
                $multiplier = 0;
            }

            // Race perk-based immortality
            if (($multiplier !== 1) && $this->isImmortalVersusRacePerk($dominion, $attacker, $slot))
            {
                $multiplier = 0;
            }

        }

        // If you are fighting against a does_not_kill race (Lux)
        # This means that Defensive CASUALTIES are zero when INVADED BY a Lux.
        if($attacker->race->getPerkValue('does_not_kill') == 1)
        {
          $multiplier = 0;
        }

        # CHECK ONLY DIES VS X RAW POWER
        if(isset($slot))
        {
          if ($dominion->race->getUnitPerkValueForUnitSlot($slot, 'only_dies_vs_raw_power') !== 0)
          {
              $minPowerToKill = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'only_dies_vs_raw_power');
              $opFromUnitsThatKill = 0;

              # Get the raw OP of each unit of $attacker.
              foreach ($attacker->race->units as $unit)
              {
                  if(isset($units[$unit->slot]))
                  {
                    # If the raw OP on the unit is enough, add it to $opFromUnitsThatKill.
                    if($this->militaryCalculator->getUnitPowerWithPerks($attacker, $dominion, $landRatio, $unit, 'offense') >= $minPowerToKill)
                    {
                      $opFromUnitsThatKill += $this->militaryCalculator->getUnitPowerWithPerks($attacker, $dominion, $landRatio, $unit, 'offense') * $units[$unit->slot];
                    }
                  }
              }

              # How much of the DP is from units that kill?
              $opFromUnitsThatKillRatio = $opFromUnitsThatKill / $this->militaryCalculator->getOffensivePowerRaw($attacker, );

              $multiplier = $opFromUnitsThatKillRatio;
          }
        }

        # END CHECK ONLY DIES VS X RAW POWER

        if ($multiplier != 0)
        {
            // Non-unit bonuses (hero, tech, wonders), capped at -80%
            #$multiplier = $multiplier; -- Removed to not cause issues with $multiplier set by only_dies_vs_raw_power perk.

            // Spells
            # Troll and Lizardfolk spell: decreases casualties by 25%.
            if ($this->spellCalculator->isSpellActive($dominion, 'regeneration'))
            {
              $multiplier -= 0.25;
            }
            # Invasion Spell: Unhealing Wounds
            if ($this->spellCalculator->isSpellActive($dominion, 'unhealing_wounds'))
            {
              $multiplier += 0.50;
            }

            # Land-based reductions
            $multiplier -= $this->getCasualtiesReductionFromLand($dominion, $slot, 'defense');
            #$multiplier -= $this->getCasualtiesReductionVersusLand($dominion, $target, $slot, 'defense'); -- Doesn't make sense in this context (attacker has no defensive casualties).

            # Shrines
            $multiplier -= $this->getDefensiveCasualtiesReductionFromShrines($dominion);

            // Techs
            $multiplier -= $dominion->getTechPerkMultiplier('fewer_casualties_defense');

            // Infirmary
            $multiplier -= $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'infirmary');

            # Unit bonuses
            // Unit Perk: Fewer Casualties (only on military units with a slot, draftees don't have this perk)
            if ($slot)
            {
                $multiplier -= ($dominion->race->getUnitPerkValueForUnitSlot($slot, ['fewer_casualties', 'fewer_casualties_defense']) / 100);
            }

            // Unit Perk: Reduce Combat Losses
            $unitsAtHomePerSlot = [];
            $unitsAtHomeRCLSlot = null;
            $reducedCombatLosses = 0;

            // todo: inefficient to do run this code per slot. needs refactoring
            foreach ($dominion->race->units as $unit) {
                $slot = $unit->slot;
                $unitKey = "military_unit{$slot}";

                $unitsAtHomePerSlot[$slot] = $dominion->$unitKey;

                if ($unit->getPerkValue('reduce_combat_losses') !== 0) {
                    $unitsAtHomeRCLSlot = $slot;
                }
            }

            // We have a unit with RCL!
            if ($unitsAtHomeRCLSlot !== null) {
                $totalUnitsAtHome = array_sum($unitsAtHomePerSlot);

                if ($totalUnitsAtHome > 0) {
                    $reducedCombatLosses += (($unitsAtHomePerSlot[$unitsAtHomeRCLSlot] / $totalUnitsAtHome) / 2);
                }
            }

            # Apply RCL do uBM.
            $multiplier -= $reducedCombatLosses;

            // Absolute cap at 90% reduction.
            $multiplier = max(0.10, $multiplier);
        }

        return $multiplier;
    }

    /**
     * Returns the Dominion's offensive casualties reduction from shrines.
     *
     * This number is in the 0 - 0.8 range, where 0 is no casualty reduction
     * (0%) and 0.8 is full (-80%). Used additive in a multiplier formula.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getOffensiveCasualtiesReductionFromShrines(Dominion $dominion): float
    {
        // Values (percentage)
        $casualtyReductionPerShrine = 5;
        $maxCasualtyReductionFromShrines = 75;

        return min(
            (($casualtyReductionPerShrine * $dominion->building_shrine) / $this->landCalculator->getTotalLand($dominion)),
            ($maxCasualtyReductionFromShrines / 100)
        );
    }

    /**
     * Returns the Dominion's defensive casualties reduction from shrines.
     *
     * This number is in the 0 - 0.8 range, where 0 is no casualty reduction
     * (0%) and 0.8 is full (-80%). Used additive in a multiplier formula.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getDefensiveCasualtiesReductionFromShrines(Dominion $dominion): float
    {
        // Values (percentage)
        $casualtyReductionPerShrine = 1;
        $maxCasualtyReductionFromShrines = 15;

        return min(
            (($casualtyReductionPerShrine * $dominion->building_shrine) / $this->landCalculator->getTotalLand($dominion)),
            ($maxCasualtyReductionFromShrines / 100)
        );
    }

    /**
     * Returns the Dominion's casualties by unit type.
     *
     * @param  Dominion $dominion
     * @param int $foodDeficit
     * @return array
     */
    public function getStarvationCasualtiesByUnitType(Dominion $dominion, int $foodDeficit): array
    {
        $units = $this->getStarvationUnitTypes();

        $totalCasualties = $this->getTotalStarvationCasualties($dominion, $foodDeficit);

        if ($totalCasualties === 0) {
            return [];
        }

        $peasantPopPercentage = $dominion->peasants / $this->populationCalculator->getPopulation($dominion);
        $casualties = ['peasants' => min($totalCasualties * $peasantPopPercentage, $dominion->peasants)];
        $casualties += array_fill_keys($units, 0);

        $remainingCasualties = ($totalCasualties - array_sum($casualties));
        $totalMilitaryCasualties = $remainingCasualties;

        foreach($units as $unit) {
            if($remainingCasualties == 0) {
                break;
            }

            $slotTotal = $dominion->{$unit};

            if($slotTotal == 0) {
                continue;
            }

            $slotLostMultiplier = $slotTotal / $totalMilitaryCasualties;

            $slotLost = ceil($slotTotal * $slotLostMultiplier);

            if($slotLost > $remainingCasualties) {
                $slotLost = $remainingCasualties;
            }

            $casualties[$unit] += $slotLost;
            $remainingCasualties -= $slotLost;
        }

        if ($remainingCasualties > 0) {
            $casualties['peasants'] = (int)min(
                ($remainingCasualties + $casualties['peasants']),
                $dominion->peasants
            );
        }

        $casualties = array(
          'peasants' => 0,
          'unit1' => 0,
          'unit2' => 0,
          'unit3' => 0,
          'unit4' => 0,
          'spies' => 0,
          'wizards' => 0,
          'archmage' => 0
        );

      return array_filter($casualties);
    }

    /**
     * Returns the Dominion's number of casualties due to starvation.
     *
     * @param  Dominion $dominion
     * @param int $foodDeficit
     * @return int
     */
    public function getTotalStarvationCasualties(Dominion $dominion, int $foodDeficit): int
    {
        if ($foodDeficit >= 0) {
            return 0;
        }

        $casualties = (int)(abs($foodDeficit) * 2);
        $maxCasualties = $this->populationCalculator->getPopulation($dominion) * 0.02;

        return min($casualties, $maxCasualties);
    }

    /**
     * Returns the unit types that can suffer casualties.
     *
     * @return array
     */
    protected function getStarvationUnitTypes(): array
    {
        return array_merge(
            array_map(
                function ($unit) {
                    return ('military_' . $unit);
                },
                $this->unitHelper->getUnitTypes()
            ),
            ['military_draftees']
        );
    }

    /**
     * @param Dominion $dominion
     * @param Dominion $target
     * @param int $slot
     * @return bool
     */
    protected function isImmortalVersusRacePerk(Dominion $dominion, Dominion $target, int $slot): bool
    {

        # Question: is military_unit$slot of $dominion immortal against $target?

        $raceNotImmortalAgainst = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_except_vs');
        $raceNotImmortalAgainst = strtolower($raceNotImmortalAgainst);
        $raceNotImmortalAgainst = str_replace(' ', '_', $raceNotImmortalAgainst);

        $targetRace = $target->race->name;
        $targetRace = strtolower($targetRace);
        $targetRace = str_replace(' ', '_', $targetRace);

        if($targetRace == $raceNotImmortalAgainst or !$raceNotImmortalAgainst)
        {
          return False;
        }
        else
        {
          return True;
        }
/*
        $raceNameFormatted = strtolower($target->race->name);
        $raceNameFormatted = str_replace(' ', '_', $raceNameFormatted);

        $perkValue = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'immortal_except_vs');

        if(!$perkValue)
        {
            return false;
        }
*/
        return $perkValue !== $raceNameFormatted;
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

      /**
       * @param Dominion $dominion
       * @param Unit $unit
       * @return float
       */
      protected function getCasualtiesReductionVersusLand(Dominion $dominion, Dominion $target, int $slot = NULL, string $powerType): float
      {
        if ($target === null or $slot == NULL)
        {
            return 0;
        }

        $versusLandPerkData = $dominion->race->getUnitPerkValueForUnitSlot($slot, "fewer_casualties_{$powerType}_vs_land", null);

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

}
