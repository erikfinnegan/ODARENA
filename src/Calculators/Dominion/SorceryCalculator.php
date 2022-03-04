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

    public function canPerformSorcery(Dominion $caster): bool
    {
        if($caster->wizard_strength >= 4 and $this->militaryCalculator->getWizardRatio($caster, 'offense') >= 0.04)
        {
            return true;
        }

        return false;
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

        $duration *= $this->getSorcerySpellDamageMultiplier($caster, $target, $spell, $wizardStrength, $enhancementResource, $enhancementAmount);

        return $duration;
    }

    public function getSorcerySpellDamageMultiplier(Dominion $caster, Dominion $target, Spell $spell, int $wizardStrength, Resource $enhancementResource = null, int $enhancementAmount = 0, string $perkKey = null): float
    {
        $multiplier = 1;

        $multiplier *= $this->getSorceryWizardStrengthMultiplier($caster, $wizardStrength);
        $multiplier *= $this->getSorceryWizardRatioMultiplier($caster, $target);

        # ENHANCEMENTS ???

        return $multiplier;
    }

    public function getSorceryWizardStrengthMultiplier(Dominion $caster, int $wizardStrength): float
    {
        $multiplier = 1;
        $multiplier += max($wizardStrength, (exp($wizardStrength/120)-1));# (0.05 * ($wizardStrength ** 0.50));

        return $multiplier;
    }

    public function getSorceryWizardRatioMultiplier(Dominion $caster, Dominion $target): float
    {
        $multiplier = 1;
        $casterWpa = $this->militaryCalculator->getWizardRatio($caster, 'offense');
        $targetWpa = $this->militaryCalculator->getWizardRatio($target, 'defense');

        if($casterWpa <= 0)
        {
            return 0;
        }

        $multiplier = clamp(1 + (($casterWpa - $targetWpa) / 10), 0, 2);

        return $multiplier;
    }

}
