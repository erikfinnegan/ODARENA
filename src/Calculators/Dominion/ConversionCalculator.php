<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Models\Dominion;

use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;

class ConversionCalculator
{
    /** @var MilitaryCalculator */
    protected $militaryCalculator;

    /** @var RangeCalculator */
    protected $rangeCalculator;

    public function __construct()
    {
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->rangeCalculator = app(RangeCalculator::class);
        $this->spellCalculator = app(SpellCalculator::class);
        $this->landCalculator = app(LandCalculator::class);
    }

    public function getConversions(Dominion $attacker, Dominion $defender, array $invasion): array
    {
        #$conversions = [];
        $conversions['attacker'] = array_fill(1, 4, 0);
        $conversions['defender'] = array_fill(1, 4, 0);

        # Land ratio: float
        $landRatio = $invasion['attacker']['landSize'] / $invasion['defender']['landSize'];

        # Attacker's raw OP
        $rawOp = 0;
        foreach($invasion['attacker']['unitsSent'] as $slot => $amount)
        {
            $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                return ($unit->slot === $slot);
            })->first();

            $rawOp += $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense') * $amount;
        }

        # Attacker's raw OP lost
        $rawOpLost = 0;
        foreach($invasion['attacker']['unitsLost'] as $slot => $amount)
        {
            $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                return ($unit->slot === $slot);
            })->first();

            $rawOpLost += $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense') * $amount;
        }

        # Defender's raw DP
        $rawDp = 0;
        foreach($invasion['defender']['unitsDefending'] as $slot => $amount)
        {
            if($slot !== 'draftees')
            {
                $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $rawDp += $this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense') * $amount;
            }
            else
            {
                $rawDp += $defender->military_draftees;
            }
        }

        # Defender's raw DP lost
        $rawDpLost = 0;
        foreach($invasion['defender']['unitsLost'] as $slot => $amount)
        {
            if($slot !== 'draftees')
            {
                $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $rawDpLost += $this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense') * $amount;
            }
            else
            {
                $rawDpLost += $defender->military_draftees;
            }
        }

        $displacedPeasantsConversions = $this->getDisplacedPeasantsConversions($attacker, $defender, $invasion, $rawOp, $rawDp, $landRatio);

        # NYI
        #$casualtiesbasedConversionsOnOffense = $this->getCasualtiesBasedConversions($attacker, $defender, $invasion, $rawOp, $rawDp, $landRatio, 'offense');
        #$casualtiesbasedConversionsOnDefense = $this->getCasualtiesBasedConversions($defender, $attacker, $invasion, $rawOp, $rawDp, $landRatio, 'defense');

        #$strengthBasedConversionsOnOffense = $this->getStrengthBasedConversions($attacker, $defender, $invasion, $rawOp, $rawDp, $landRatio, 'offense');
        #$strengthBasedConversionsOnDefense = $this->getStrengthBasedConversions($defender, $attacker, $invasion, $rawOp, $rawDp, $landRatio, 'defense');

        $strengthValueConversionsOnOffense = $this->getValueBasedConversions($attacker, $defender, $invasion, $rawOp, $rawDp, $landRatio, 'offense', $rawDpLost, 0);
        $strengthValueConversionsOnDefense = $this->getValueBasedConversions($defender, $attacker, $invasion, $rawOp, $rawDp, $landRatio, 'defense', 0, $rawOpLost);

        foreach($conversions['attacker'] as $slot => $amount)
        {
            $conversions['attacker'][$slot] += $displacedPeasantsConversions[$slot];
            $conversions['attacker'][$slot] += $strengthValueConversionsOnOffense[$slot];

            # NYI
            #$conversions['attacker'][$slot] += $casualtiesbasedConversionsOnOffense[$slot];
            #$conversions['defender'][$slot] += $casualtiesbasedConversionsOnDefense[$slot];

            #$conversions['attacker'][$slot] += $strengthBasedConversionsOnOffense[$slot];
            #$conversions['defender'][$slot] += $strengthBasedConversionsOnDefense[$slot];
        }

        foreach($conversions['defender'] as $slot => $amount)
        {
            $conversions['defender'][$slot] += $strengthValueConversionsOnDefense[$slot];

            # NYI
            #$conversions['attacker'][$slot] += $casualtiesbasedConversionsOnOffense[$slot];
            #$conversions['defender'][$slot] += $casualtiesbasedConversionsOnDefense[$slot];

            #$conversions['attacker'][$slot] += $strengthBasedConversionsOnOffense[$slot];
            #$conversions['defender'][$slot] += $strengthBasedConversionsOnDefense[$slot];

        }

        return $conversions;
    }

    /*
    *   Calculate how many peasants were displaced.
    */
    public function getDisplacedPeasantsConversions(Dominion $attacker, Dominion $defender, array $invasion, int $rawOp, int $rawDp, float $landRatio): array
    {
        $convertedUnits = array_fill(1, 4, 0);
        $convertingUnits = array_fill(1, 4, 0);
        $landConquered = array_sum($invasion['attacker']['landConquered']);
        $displacedPeasants = intval(($defender->peasants / $invasion['defender']['landSize']) * $landConquered);

        # Check that unitsSent contains displaced_peasants_conversion perk
        foreach($invasion['attacker']['unitsSent'] as $slot => $amount)
        {
            if($displacedPeasantsConversionPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'displaced_peasants_conversion'))
            {
                $convertingUnits[$slot] += $amount;

                # Deduct lost units.
                if(isset($unitsLost[$slot]))
                {
                    $convertingUnits -= $unitsLost[$slot];
                }
            }
        }

        # Calculate contribution (unit raw OP / total raw OP)
        foreach($convertingUnits as $slot => $amount)
        {
            $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                return ($unit->slot === $slot);
            })->first();

            $unitRawOp = $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense');

            $sentUnitsOpRatio[$slot] = ($unitRawOp * $amount) / $rawOp;
         }

         # Populate $convertedUnits
         foreach($sentUnitsOpRatio as $slot => $ratio)
         {
            if($displacedPeasantsConversionPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'displaced_peasants_conversion'))
            {
                $convertedUnits[$displacedPeasantsConversionPerk] += intval($displacedPeasants * $ratio);
            }
         }

         return $convertedUnits;
    }

    public function getValueBasedConversions(Dominion $attacker, Dominion $defender, array $invasion, int $rawOp, int $rawDp, float $landRatio, string $mode, int $rawDpLost, int $rawOpLost): array
    {
        $convertedUnits = array_fill(1, 4, 0);
        $convertingUnits = array_fill(1, 4, 0);

        if($mode === 'offense')
        {
            # Check that unitsSent contains displaced_peasants_conversion perk
            foreach($invasion['attacker']['unitsSent'] as $slot => $amount)
            {
                if($valueConversionPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'value_conversions'))
                {
                    $convertingUnits[$slot] += $amount;

                    # Deduct lost units.
                    if(isset($unitsLost[$slot]))
                    {
                        $convertingUnits -= $unitsLost[$slot];
                    }
                }
            }

            # Calculate contribution (unit raw OP / total raw OP)
            # This determines how many units were killed by each converting unit
            foreach($convertingUnits as $slot => $amount)
            {
                $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $unitRawOp = $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense');

                $convertingUnitsOpRatio[$slot] = ($unitRawOp * $amount) / $rawOp;
             }

             foreach($convertingUnits as $slot => $amount)
             {
                  if($amount > 0)
                  {
                      $valueConversionPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'value_conversions');
                      $conversionMultiplier = (float)$valueConversionPerk[0];
                      $convertedSlot = (int)$valueConversionPerk[1];

                      $convertedUnits[$convertedSlot] += (int)round(($rawDpLost * $convertingUnitsOpRatio[$slot]) * $conversionMultiplier);
                  }
             }

        }

         return $convertedUnits;
    }


}
