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
        $this->unitHelper = app(UnitHelper::class);
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

        # Attacker's raw OP lost, from convertible units
        $rawOpLost = 0;
        foreach($invasion['attacker']['unitsLost'] as $slot => $amount)
        {
            $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                return ($unit->slot === $slot);
            })->first();

            if($this->isSlotConvertible($slot, $defender))
            {
                $rawOpLost += $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense') * $amount;
            }
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

                if($this->isSlotConvertible($slot, $defender))
                {
                  $rawDpLost += $this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense') * $amount;
                }
            }
            else
            {
                $rawDpLost += $defender->military_draftees;
            }
        }

        $displacedPeasantsConversions = $this->getDisplacedPeasantsConversions($attacker, $defender, $invasion, $rawOp, $rawDp, $landRatio);

        $valueConversionsOnOffense = $this->getValueBasedConversions($attacker, $defender, $invasion, $rawOp, $rawDp, $landRatio, 'offense', $rawDpLost, 0);
        $valueConversionsOnDefense = $this->getValueBasedConversions($attacker, $defender, $invasion, $rawOp, $rawDp, $landRatio, 'defense', 0, $rawOpLost);

        $casualtiesBasedConversionsOnOffense = $this->getCasualtiesBasedConversions($attacker, $defender, $invasion, $rawOp, $rawDp, $landRatio, 'offense', $rawDpLost, 0);
        $casualtiesBasedConversionsOnDefense = $this->getCasualtiesBasedConversions($attacker, $defender, $invasion, $rawOp, $rawDp, $landRatio, 'defense', 0, $rawOpLost);

        $strengthBasedConversionsOnOffense = $this->getStrengthBasedConversions($attacker, $defender, $invasion, $rawOp, $rawDp, $landRatio, 'offense');
        $strengthBasedConversionsOnDefense = $this->getStrengthBasedConversions($attacker, $defender, $invasion, $rawOp, $rawDp, $landRatio, 'defense');

        foreach($conversions['attacker'] as $slot => $amount)
        {
            $conversions['attacker'][$slot] += $displacedPeasantsConversions[$slot];

            $conversions['attacker'][$slot] += $valueConversionsOnOffense[$slot];
            $conversions['attacker'][$slot] += $casualtiesBasedConversionsOnOffense[$slot];
            $conversions['attacker'][$slot] += $strengthBasedConversionsOnOffense[$slot];
        }

        foreach($conversions['defender'] as $slot => $amount)
        {
            $conversions['defender'][$slot] += $valueConversionsOnDefense[$slot];
            $conversions['defender'][$slot] += $casualtiesBasedConversionsOnDefense[$slot];
            $conversions['defender'][$slot] += $strengthBasedConversionsOnDefense[$slot];
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

        if(!$invasion['result']['success'] or $defender->race->getPerkValue('can_only_abduct_own'))
        {
            return $convertedUnits;
        }

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
                if($valueConversionPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'value_conversion'))
                {
                    $convertingUnits[$slot] += $amount;

                    # Deduct lost units.
                    if(isset($unitsLost[$slot]))
                    {
                        $convertingUnits -= $unitsLost[$slot];
                    }
                }
            }

            # If invasion is not successful, reduce raw DP lost to 1/12
            if(!$invasion['result']['success'])
            {
                $rawDpLost /= 12;
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
                      $valueConversionPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'value_conversion');
                      $conversionMultiplier = (float)$valueConversionPerk[0];
                      $convertedSlot = (int)$valueConversionPerk[1];

                      $convertedUnits[$convertedSlot] += (int)round(($rawDpLost  * (1 - $defender->race->getPerkMultiplier('reduced_conversions')) * $convertingUnitsOpRatio[$slot]) * $conversionMultiplier);
                  }
             }

             # Cap at 0, in case of weird behaviour from negative OP/DP
             foreach($convertedUnits as $slot => $amount)
             {
                $convertedUnits[$slot] = max(0, $convertedUnits[$slot]);
             }

        }

        elseif($mode === 'defense')
        {
            # Check that unitsDefending contains displaced_peasants_conversion perk
            foreach($invasion['defender']['unitsDefending'] as $slot => $amount)
            {
                if($slot !== 'draftees')
                {
                    if($valueConversionPerk = $defender->race->getUnitPerkValueForUnitSlot($slot, 'value_conversion'))
                    {
                        $convertingUnits[$slot] += $amount;

                        # Deduct lost units.
                        if(isset($unitsLost[$slot]))
                        {
                            $convertingUnits -= $unitsLost[$slot];
                        }
                    }
                }
            }

            # If invasion is successful, reduce raw OP lost to 1/6
            if($invasion['result']['success'])
            {
                $rawDpLost /= 6;
            }

            # Calculate contribution (unit raw DP / total raw DP)
            # This determines how many units were killed by each converting unit
            foreach($convertingUnits as $slot => $amount)
            {
                $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $unitRawDp = $this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense');

                $convertingUnitsDpRatio[$slot] = ($amount * $unitRawDp) / $rawDp;
             }

             foreach($convertingUnits as $slot => $amount)
             {
                  if($amount > 0)
                  {
                      $valueConversionPerk = $defender->race->getUnitPerkValueForUnitSlot($slot, 'value_conversion');
                      $conversionMultiplier = (float)$valueConversionPerk[0];
                      $convertedSlot = (int)$valueConversionPerk[1];

                      $convertedUnits[$convertedSlot] += (int)round(($rawDpLost  * (1 - $attacker->race->getPerkMultiplier('reduced_conversions')) * $convertingUnitsDpRatio[$slot]) * $conversionMultiplier);
                  }
             }

             # Cap at 0, in case of weird behaviour from negative OP/DP
             foreach($convertedUnits as $slot => $amount)
             {
                $convertedUnits[$slot] = max(0, $convertedUnits[$slot]);
             }

        }

        return $convertedUnits;
    }


    public function getCasualtiesBasedConversions(Dominion $attacker, Dominion $defender, array $invasion, int $rawOp, int $rawDp, float $landRatio, string $mode, int $rawDpLost, int $rawOpLost): array
    {
        $convertedUnits = array_fill(1, 4, 0);
        $convertingUnits = array_fill(1, 4, 0);

        if($mode === 'offense')
        {
            $defensiveCasualties = 0;
            foreach($invasion['defender']['unitsLost'] as $slot => $amount)
            {
                if($this->isSlotConvertible($slot, $defender))
                {
                    $defensiveCasualties += $amount;
                }
            }

            # If invasion is not successful, reduce defensive casualties to 1/12
            if(!$invasion['result']['success'])
            {
                $defensiveCasualties /= 12;
            }

            # Check that unitsSent contains displaced_peasants_conversion perk
            foreach($invasion['attacker']['unitsSent'] as $slot => $amount)
            {
                if($valueConversionPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'casualties_conversion'))
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

                $unitRawOp = $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense', null, $invasion['attacker']['unitsSent'], null);

                $convertingUnitsOpRatio[$slot] = ($unitRawOp * $amount) / $rawOp;
             }

             foreach($convertingUnits as $slot => $amount)
             {
                  if($amount > 0)
                  {
                      $valueConversionPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'casualties_conversion');
                      $convertedSlot = (int)$valueConversionPerk[0];

                      $convertedUnits[$convertedSlot] += (int)round($defensiveCasualties * (1 - $defender->race->getPerkMultiplier('reduced_conversions')) * $convertingUnitsOpRatio[$slot]);
                  }
             }

             # Cap at 0, in case of weird behaviour from negative OP/DP
             foreach($convertedUnits as $slot => $amount)
             {
                $convertedUnits[$slot] = max(0, $convertedUnits[$slot]);
             }

        }

        if($mode === 'defense')
        {
            $offensiveCasualties = 0;
            foreach($invasion['attacker']['unitsLost'] as $slot => $amount)
            {
                if($this->isSlotConvertible($slot, $defender))
                {
                    $offensiveCasualties += $amount;
                }
            }

            # If invasion is successful, reduce offensive casualties to 1/6
            if($invasion['result']['success'])
            {
                $offensiveCasualties /= 6;
            }

            # Check that unitsDefending contains casualties_conversion perk
            foreach($invasion['defender']['unitsDefending'] as $slot => $amount)
            {
                if($slot !== 'draftees')
                {
                    if($valueConversionPerk = $defender->race->getUnitPerkValueForUnitSlot($slot, 'casualties_conversion'))
                    {
                        $convertingUnits[$slot] += $amount;

                        # Deduct lost units.
                        if(isset($unitsLost[$slot]))
                        {
                            $convertingUnits -= $unitsLost[$slot];
                        }
                    }
                }

            }

            # Calculate contribution (unit raw DP / total raw DP)
            # This determines how many units were killed by each converting unit
            foreach($convertingUnits as $slot => $amount)
            {
                $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $unitRawDp = $this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense');

                $convertingUnitsDpRatio[$slot] = ($unitRawDp * $amount) / $rawDp;
             }

             foreach($convertingUnits as $slot => $amount)
             {
                  if($amount > 0)
                  {
                      $valueConversionPerk = $defender->race->getUnitPerkValueForUnitSlot($slot, 'casualties_conversion');
                      $convertedSlot = (int)$valueConversionPerk[0];

                      $convertedUnits[$convertedSlot] += (int)round($offensiveCasualties * (1 - $defender->race->getPerkMultiplier('reduced_conversions')) * $convertingUnitsDpRatio[$slot]);
                  }
             }

             # Cap at 0, in case of weird behaviour from negative OP/DP
             foreach($convertedUnits as $slot => $amount)
             {
                $convertedUnits[$slot] = max(0, $convertedUnits[$slot]);
             }

        }

        return $convertedUnits;
    }

    public function getStrengthBasedConversions(Dominion $attacker, Dominion $defender, array $invasion, int $rawOp, int $rawDp, float $landRatio, string $mode): array
    {
        $convertedUnits = array_fill(1, 4, 0);
        $convertingUnits = array_fill(1, 4, 0);

        if($mode === 'offense')
        {
            if($this->spellCalculator->isSpellActive($attacker, 'feral_hunger'))
            {
                return $convertedUnits;
            }

            $availableCasualties =
                [
                    'draftees' => ['amount' => 0, 'dp' => 0],
                           '1' => ['amount' => 0, 'dp' => 0],
                           '2' => ['amount' => 0, 'dp' => 0],
                           '3' => ['amount' => 0, 'dp' => 0],
                           '4' => ['amount' => 0, 'dp' => 0],
                ];

            # Check that unitsSent contains strength_conversion perk
            foreach($invasion['attacker']['unitsSent'] as $slot => $amount)
            {
                if($displacedPeasantsConversionPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'strength_conversion'))
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

            foreach($invasion['defender']['unitsLost'] as $slot => $amount)
            {
                # Apply reduced conversions
                $amount *= (1 - ($defender->race->getPerkMultiplier('reduced_conversions')));

                # Drop to 1/12 if invasion is not successful
                if(!$invasion['result']['success'])
                {
                    $amount /= 12;
                }

                # Round it down
                #$amount = round($amount); -- Moved to later in the code

                if($slot === 'draftees')
                {
                    $availableCasualties[$slot]['amount'] = $amount;

                    if($defender->race->getPerkValue('draftee_dp'))
                    {
                        $availableCasualties[$slot]['dp'] = $defender->race->getPerkValue('draftee_dp');
                    }
                    else
                    {
                        $availableCasualties[$slot]['dp'] = 1;
                    }
                }
                else
                {
                    # Get the $unit
                    $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                            return ($unit->slot == $slot);
                        })->first();

                    if($this->isSlotConvertible($slot, $defender))
                    {
                        $availableCasualties[$slot]['amount'] = $amount;
                        $availableCasualties[$slot]['dp'] = (float)$this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense');
                    }
                }
            }

            # Loop through all available casualties
            foreach($availableCasualties as $casualty)
            {
                #echo "<pre>***\n";
                #echo "[DEFENDER] Unit amount: " . $casualty['amount'] . ' / Unit raw DP: ' .$casualty['dp']. "\n";
                # For each casualty unit, loop through units sent.
                foreach($convertingUnits as $convertingUnitSlot => $sentAmount)
                {
                    #echo "[ATTACKER] Unit slot: $unitSentSlot / Amount sent: $sentAmount / Raw OP ratio: {$sentUnitsOpRatio[$unitSentSlot]}\n";

                    $casualtyAmountAvailableToUnit = $casualty['amount'] * $convertingUnitsOpRatio[$convertingUnitSlot];
                    #$casualty['amount'] -= min($casualtyAmountAvailableToUnit, $casualty['amount']);

                    #echo "[ATTACKER] Unit slot $unitSentSlot killed $casualtyAmountAvailableToUnit of this unit.\n";

                    if($strengthConversion = $attacker->race->getUnitPerkValueForUnitSlot($convertingUnitSlot,'strength_conversion'))
                    {
                        $limit = (float)$strengthConversion[0];
                        $under = (int)$strengthConversion[1];
                        $over = (int)$strengthConversion[2];

                        if($casualty['dp'] <= $limit)
                        {
                            $slotConvertedTo = $under;
                            #echo "[DEFENDER] Unit raw DP is less than or equal to the limit ($limit). So it gets converted to $slotConvertedTo.\n";
                        }
                        else
                        {
                            $slotConvertedTo = $over;
                            #echo "[DEFENDER] Unit raw DP is greater the limit ($limit). So it gets converted to $slotConvertedTo.\n";
                        }

                        $convertedUnits[$slotConvertedTo] += (int)round($casualtyAmountAvailableToUnit);
                    }
                }
                #echo "***</pre>";
            }
        }

        if($mode === 'defense')
        {
            if($this->spellCalculator->isSpellActive($defender, 'feral_hunger'))
            {
                return $convertedUnits;
            }

            $availableCasualties =
                [
                           '1' => ['amount' => 0, 'op' => 0],
                           '2' => ['amount' => 0, 'op' => 0],
                           '3' => ['amount' => 0, 'op' => 0],
                           '4' => ['amount' => 0, 'op' => 0],
                ];

            # Check that unitsSent contains strength_conversion perk
            foreach($invasion['defender']['unitsDefending'] as $slot => $amount)
            {
                if($slot !== 'draftees')
                {
                    if($displacedPeasantsConversionPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'strength_conversion'))
                    {
                        $convertingUnits[$slot] += $amount;

                        # Deduct lost units.
                        if(isset($unitsLost[$slot]))
                        {
                            $convertingUnits -= $unitsLost[$slot];
                        }
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

                $unitRawDp = $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'defense');

                $convertingUnitsDpRatio[$slot] = ($unitRawDp * $amount) / $rawDp;
             }

            foreach($invasion['attacker']['unitsLost'] as $slot => $amount)
            {
                # Apply reduced conversions
                $amount *= (1 - ($attacker->race->getPerkMultiplier('reduced_conversions')));

                # Drop to 1/6 if invasion is successful
                if($invasion['result']['success'])
                {
                    $amount /= 6;
                }

                # Round it
                #$amount = round($amount); -- Moved to later in the code

                # Get the $unit
                $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                        return ($unit->slot == $slot);
                    })->first();

                if($this->isSlotConvertible($slot, $defender))
                {
                    $availableCasualties[$slot]['amount'] = $amount;
                    $availableCasualties[$slot]['op'] = (float)$this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense');
                }
            }

            # Loop through all available casualties
            foreach($availableCasualties as $casualty)
            {
                #echo "<pre>***\n";
                #echo "[ATTACKER] Unit amount: " . $casualty['amount'] . ' / Unit raw OP: ' .$casualty['op']. "\n";
                # For each casualty unit, loop through units sent.
                foreach($convertingUnits as $convertingUnitSlot => $defendingAmount)
                {
                    #echo "[DEFENDER] Unit slot: $convertingUnitSlot / Amount defending: $defendingAmount / Raw OP ratio: {$convertingUnitsDpRatio[$convertingUnitSlot]}\n";

                    $casualtyAmountAvailableToUnit = $casualty['amount'] * $convertingUnitsDpRatio[$convertingUnitSlot];

                    #echo "[DEFENDER] Unit slot $convertingUnitSlot killed $casualtyAmountAvailableToUnit of this unit.\n";

                    if($strengthConversion = $defender->race->getUnitPerkValueForUnitSlot($convertingUnitSlot, 'strength_conversion'))
                    {
                        $limit = (float)$strengthConversion[0];
                        $under = (int)$strengthConversion[1];
                        $over = (int)$strengthConversion[2];

                        if($casualty['op'] <= $limit)
                        {
                            $slotConvertedTo = $under;
                            #echo "[ATTACKER] Unit raw OP is less than or equal to the limit ($limit). So it gets converted to $slotConvertedTo.\n";
                        }
                        else
                        {
                            $slotConvertedTo = $over;
                            #echo "[ATTACKER] Unit raw OP is greater the limit ($limit). So it gets converted to $slotConvertedTo.\n";
                        }

                        #echo "[DEFENDER] New $slotConvertedTo units: " . (int)round($casualtyAmountAvailableToUnit) . ".\n";

                        $convertedUnits[$slotConvertedTo] += (int)round($casualtyAmountAvailableToUnit);

                    }
                }
            #echo "***</pre>";
            }

        }

        return $convertedUnits;

    }

    private function isSlotConvertible($slot, Dominion $dominion, array $unconvertibleAttributes = []): bool
    {
        if(empty($unconvertibleAttributes))
        {
            $unconvertibleAttributes = [
                'ammunition',
                'equipment',
                'magical',
                'massive',
                'machine',
                #'otherworldly',
                'ship',
              ];
        }

        $isConvertible = false;

        if($slot === 'draftees')
        {
            $isConvertible = true;
        }
        else
        {
            # Get the $unit
            $unit = $dominion->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot == $slot);
                })->first();

            # Get the unit attributes
            $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

            if(count(array_intersect($unconvertibleAttributes, $unitAttributes)) === 0)
            {
                $isConvertible = true;
            }
        }

        return $isConvertible;

    }

}
