<?php

namespace OpenDominion\Calculators\Dominion;

use DB;
use OpenDominion\Models\Dominion;
#use OpenDominion\Models\Race;

use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;

class SpellDamageCalculator
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
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->spellCalculator = app(SpellCalculator::class);
        $this->landCalculator = app(LandCalculator::class);
    }

    /*
    *   The base damage a spell does is determined by comparing the ratios of caster and target.
    *   If the caster has higher ratio than the target, it does more damage.
    *   The multiplier is capped between 0 (if target WPA > caster WPA) and 1 (if delta is greater than 10).
    *
    */
    public function getSpellBaseDamageMultiplier(Dominion $caster, Dominion $target): float
    {

        $casterWpa = $this->militaryCalculator->getWizardRatio($caster, 'offense');
        $targetWpa = $this->militaryCalculator->getWizardRatio($target, 'defense');
        return min(1, max(0, ($casterWpa - $targetWpa) / 10));
    }

    public function getDominionHarmfulSpellDamageModifier(Dominion $target, ?Dominion $caster, ?string $spell, ?string $attribute)
    {

          $modifier = 1;

          // Damage reduction from Spires
          $modifier -= $this->improvementCalculator->getImprovementMultiplierBonus($target, 'spires');

          // Damage reduction from Aura
          $modifier += $this->spellCalculator->getPassiveSpellPerkMultiplier($target, 'damage_from_spells');

          // Damage reduction from Iceshield
          if($spell == 'fireball')
          {
              $modifier += $this->spellCalculator->getPassiveSpellPerkMultiplier($target, 'damage_from_fireballs');
          }

          // Damage reduction from Iceshield
          if($spell == 'lightning_bolt')
          {
              $modifier += $this->spellCalculator->getPassiveSpellPerkMultiplier($target, 'damage_from_lightning_bolts');
          }

          // Damage reduction from Insect Swarm
          if($spell == 'insect_swarm')
          {
              $modifier += $this->spellCalculator->getPassiveSpellPerkMultiplier($target, 'damage_from_insect_swarm');
          }

          if(isset($spell))
          {
              ## Insect Swarm
              if($spell == 'insect_swarm')
              {
                  # General Insect Swarm damage modification.
                  $modifier += $target->race->getPerkMultiplier('damage_from_insect_swarm');
              }

              ## Fireballs: peasants and food
              if($spell == 'fireball')
              {
                  # General fireball damage modification.
                  if($target->race->getPerkMultiplier('damage_from_fireballs'))
                  {
                      $modifier += $target->race->getPerkMultiplier('damage_from_fireballs');
                  }

                  # Forest Havens lower damage to peasants from fireballs.
                  if($attribute == 'peasants')
                  {
                      $modifier -= ($target->building_forest_haven / $this->landCalculator->getTotalLand($target)) * 0.8;
                  }
              }

              ## Lightning Bolts: improvements
              if($spell == 'lightning_bolt')
              {
                  # General fireball damage modification.
                  if($target->race->getPerkMultiplier('damage_from_lightning_bolts'))
                  {
                      $modifier += $target->race->getPerkMultiplier('damage_from_lightning_bolts');
                  }

                  $modifier -= ($target->building_masonry / $this->landCalculator->getTotalLand($target)) * 0.8;
              }

              ## Disband Spies: spies
              if($spell == 'disband_spies')
              {
                  if ($target->race->getPerkValue('immortal_spies'))
                  {
                      $modifier = -1;
                  }
              }

              ## Purification: only effective against Afflicted.
              if($spell == 'purification')
              {
                  if($target->race->name !== 'Afflicted')
                  {
                      $modifier = -1;
                  }
              }

              ## Solar Flare: only effective against Nox.
              if($spell == 'solar_flare')
              {
                  if($target->race->name !== 'Nox')
                  {
                    $modifier = -1;
                  }
              }

              // Cap at -1.
              $modifier = max(-1, $modifier);
          }

          dd($modifier, $this->improvementCalculator->getImprovementMultiplierBonus($target, 'spires'));

          return $modifier;
    }

}
