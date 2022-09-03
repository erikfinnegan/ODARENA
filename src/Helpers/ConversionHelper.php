<?php

namespace OpenDominion\Helpers;

use OpenDominion\Models\Dominion;

use OpenDominion\Calculators\Dominion\CasualtiesCalculator;

class ConversionHelper
{
    public function __construct()
    {
        $this->casualtiesCalculator = app(CasualtiesCalculator::class);
    }

    public function isSlotConvertible($slot, Dominion $dominion, array $unconvertibleAttributes = [], array $unconvertiblePerks = [], bool $isPsionic = false, Dominion $enemy = null, array $invasion = [], $mode = 'offense'): bool
    {
        if(empty($unconvertibleAttributes))
        {
            $unconvertibleAttributes = $this->getUnconvertibleAttributes($isPsionic);
        }

        if(empty($unconvertiblePerks))
        {
            $unconvertiblePerks = $this->getUnconvertiblePerks($isPsionic);
        }

        if($isPsionic)
        {   
            $unit = $slot;
            if(!in_array($slot, ['draftees',' peasants']))
            {
                $unit = $dominion->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot == $slot);
                })->first();
            }

            if($this->casualtiesCalculator->isUnitImmortal($dominion, $enemy, $unit, $invasion, $mode))
            {
                return false;
            }
        }
    }

    public function getUnconvertibleAttributes(bool $isPsionic = false): array
    {
        
        $unconvertibleAttributes = [
            'ammunition',
            'aspect',
            'equipment',
            'fused',
            'immobile',
            'magical',
            'massive',
            'machine',
            'ship'
          ];

        if($isPsionic)
        {
            unset($unconvertibleAttributes['aspect']);
            unset($unconvertibleAttributes['fused']);
            $unconvertibleAttributes[] = 'mindless';
            $unconvertibleAttributes[] = 'wise';
        }

        return $unconvertibleAttributes;
    }
    
    public function getUnconvertiblePerks(bool $isPsionic = false): array
    {
        $unconvertiblePerks = [
            'fixed_casualties',
            'dies_into',
            'dies_into_spy',
            'dies_into_wizard',
            'dies_into_archmage',
            'dies_into_multiple',
            'dies_into_resource',
            'dies_into_resources',
            'dies_into_multiple_on_offense',
            'dies_into_on_offense',
            'dies_into_multiple_on_victory'
          ];

        return $unconvertiblePerks;
    }

}
