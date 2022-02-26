<?php

namespace OpenDominion\Calculators\Dominion;

use DB;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Spell;

use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
#use OpenDominion\Calculators\Dominion\PopulationCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;
use OpenDominion\Calculators\Dominion\SpellDamageCalculator;

class SorceryCalculator
{
    /**
     * SpellCalculator constructor.
     *
     * @param LandCalculator $landCalculator
     * @param SpellHelper $spellHelper
     */
    public function __construct()
    {
        $this->improvementCalculator = app(ImprovementCalculator::class);
        $this->landCalculator = app(LandCalculator::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);
        #$this->populationCalculator = app(PopulationCalculator::class);
        $this->spellCalculator = app(SpellCalculator::class);
        $this->spellDamageCalculator = app(SpellDamageCalculator::class);
    }

    public function getSorcerySpellManaCost(Dominion $caster, Spell $spell, int $wizardStrength): int
    {
        $manaCost = $this->spellCalculator->getManaCost($caster, $spell->key);

        return $manaCost * $wizardStrength;
    }

    public function getSorcerySpellDamage(Dominion $caster, Dominion $target, Spell $spell, string $perkKey, int $wizardStrength, Resource $enhancementResource = null, int $enhancementAmount = 0): float
    {
        $damage = 0;

    }

    public function getSorcerySpellDuration(Dominion $caster, Dominion $target, Spell $spell, int $wizardStrength, Resource $enhancementResource = null, int $enhancementAmount = 0): int
    {
        $duration = $spell->duration;

        $casterWpa = $this->militaryCalculator->getWizardRatio($caster, 'offense');

        if($casterWpa <= 0)
        {
            return 0;
        }

        $targetWpa = max($this->militaryCalculator->getWizardRatio($caster, 'offense'), 0.0001);

        return $duration;
    }

    public function getSorcerySpellDuration(Dominion $caster, Dominion $target, Spell $spell, int $wizardStrength, Resource $enhancementResource = null, int $enhancementAmount = 0): float
    {
        $multiplier = 1;

        

        return $multiplier;
    }


}
