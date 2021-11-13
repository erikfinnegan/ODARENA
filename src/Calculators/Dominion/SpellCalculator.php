<?php

namespace OpenDominion\Calculators\Dominion;

use DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

use OpenDominion\Helpers\SpellHelper;

use OpenDominion\Calculators\Dominion\ResourceCalculator;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\DominionSpell;
use OpenDominion\Models\Race;
use OpenDominion\Models\Spell;

class SpellCalculator
{

    public function __construct()
    {
        $this->landCalculator = app(LandCalculator::class);
        $this->resourceCalculator = app(ResourceCalculator::class);
        $this->spellHelper = app(SpellHelper::class);

    }

    /**
     * Returns the mana cost of a particular spell for $dominion.
     *
     * @param Dominion $dominion
     * @param string $spell
     * @return int
     */
    public function getManaCost(Dominion $dominion, string $spellKey, bool $isInvasionSpell = false): int
    {
        if($isInvasionSpell)
        {
            return 0;
        }

        $spell = Spell::where('key',$spellKey)->first();

        $totalLand = $this->landCalculator->getTotalLand($dominion);

        $baseCost = $totalLand * $spell->cost;

        return round($baseCost * $this->getManaCostMultiplier($dominion));
    }

    public function getManaCostMultiplier(Dominion $dominion): float
    {
        $multiplier = 1;

        $multiplier += $dominion->getBuildingPerkMultiplier('spell_cost');
        $multiplier += $dominion->getTechPerkMultiplier('spell_cost');
        $multiplier += $dominion->getImprovementPerkMultiplier('spell_cost');
        $multiplier += $dominion->getDeityPerkMultiplier('spell_cost');
        $multiplier += $dominion->getSpellPerkMultiplier('spell_cost');

        if(isset($dominion->title))
        {
            $multiplier += $dominion->title->getPerkMultiplier('spell_cost') * $dominion->title->getPerkBonus($dominion);
        }

        return max(0.1, $multiplier);
    }

    /**
     * Returns whether spell $type for $dominion is on cooldown.
     *
     * @param Dominion $dominion
     * @param string $spell
     * @return bool
     */
    public function isOnCooldown(Dominion $dominion, Spell $spell, bool $isInvasionSpell = false): bool
    {
        if ($this->getSpellCooldown($dominion, $spell, $isInvasionSpell) > 0)
        {
            return true;
        }
        return false;
    }

    /**
     * Returns the number of hours before spell $type for $dominion can be cast.
     *
     * @param Dominion $dominion
     * @param string $spell
     * @return bool
     */
    public function getSpellCooldown(Dominion $dominion, Spell $spell, bool $isInvasionSpell = false): int
    {
        if ($spell->cooldown > 0)
        {
            if($dominionSpell = DominionSpell::where('dominion_id', $dominion->id)->where('spell_id', $spell->id)->first())
            {
                return $dominionSpell->cooldown;
            }
        }

        return 0;
    }

    /**
     * Returns a list of spells currently affecting $dominion.
     *
     * @param Dominion $dominion
     * @param bool $forceRefresh
     * @return Collection
     */
    public function getActiveSpells(Dominion $dominion): Collection
    {
        return DominionSpell::where('caster_id',$dominion->id)->get();
    }


    public function getPassiveSpellsCastByDominion(Dominion $caster, string $scope)#: Collection
    {
        if($scope)
        {
            return collect(
                  DominionSpell::query()
                      ->join('spells', 'dominion_spells.spell_id','spells.id')
                      ->where('dominion_spells.caster_id',$caster->id)
                      ->where('spells.scope',$scope)
                      ->get()
            );
        }

        return collect(DominionSpell::where('dominion_id',$dominion->id)->get());
    }

    public function getPassiveSpellsCastOnDominion(Dominion $dominion, string $scope = null)#: Collection
    {
        if($scope)
        {
            return collect(
                  DominionSpell::query()
                      ->join('spells', 'dominion_spells.spell_id','spells.id')
                      ->where('dominion_spells.dominion_id',$dominion->id)
                      ->where('spells.scope',$scope)
                      ->get()
            );
        }

        return collect(DominionSpell::where('dominion_id',$dominion->id)->get());
    }

    /**
     * Returns whether a particular spell is affecting $dominion right now.
     *
     * @param Dominion $dominion
     * @param string $spell
     * @return bool
     */
    public function isSpellActive(Dominion $dominion, string $spellKey): bool
    {
        $spell = Spell::where('key', $spellKey)->first();
        return DominionSpell::where('spell_id',$spell->id)->where('dominion_id',$dominion->id)->where('duration','>',0)->first() ? true : false;
    }

    /**
     * Returns the remaining duration (in ticks) of a spell affecting $dominion.
     *
     * @todo Rename to getSpellRemainingDuration for clarity
     * @param Dominion $dominion
     * @param string $spell
     * @return int|null
     */
    public function getSpellDuration(Dominion $dominion, string $spellKey): ?int
    {
        if (!$this->isSpellActive($dominion, $spellKey))
        {
            return null;
        }

        $spell = Spell::where('key', $spellKey)->first();
        $dominionSpell = DominionSpell::where('spell_id',$spell->id)->where('caster_id',$dominion->id)->first();

        return $dominionSpell->duration;
    }

    public function getPassiveSpellPerkValues(Dominion $dominion, string $perkString): array
    {

        $perkValuesFromSpells = [];

        # Get all active spells.
        $activeSpells = $this->getActiveSpells($dominion);

        # Check each spell for the $perk
        foreach($activeSpells as $spell)
        {
            #$perkValuesFromSpells[] = $spell->getPerkValue($perkString);
        }

        return $perkValuesFromSpells;
    }

    public function getPassiveSpellPerkValue(Dominion $dominion, string $perk): float
    {
        $perkValuesFromSpells = $this->getPassiveSpellPerkValues($dominion, $perk);
        return array_sum($perkValuesFromSpells);
    }

    public function getPassiveSpellPerkMultiplier(Dominion $dominion, string $perk): float
    {
        return $this->getPassiveSpellPerkValue($dominion, $perk) / 100;
    }

    public function isSpellAvailableToDominion(Dominion $dominion, Spell $spell): bool
    {
        return $this->isSpellAvailableToRace($dominion->race, $spell);
    }

    public function isSpellAvailableToRace(Race $race, Spell $spell): bool
    {
        $isAvailable = true;

        if(count($spell->exclusive_races) > 0 and !in_array($race->name, $spell->exclusive_races))
        {
            $isAvailable = false;
        }

        if(count($spell->excluded_races) > 0 and in_array($race->name, $spell->excluded_races))
        {
            $isAvailable = false;
        }

        return $isAvailable;
    }

    public function canCastSpell(Dominion $dominion, Spell $spell): bool
    {
        if($spell->class === 'invasion')
        {
            return true;
        }

        if(
            # Cannot be on cooldown
            $this->isOnCooldown($dominion, $spell)

            # Cannot cast disabled spells
            or $spell->enabled !== 1

            # Cannot cost more mana than the dominion has
            or $this->resourceCalculator->getAmount($dominion, 'mana') < $this->getManaCost($dominion, $spell->key)

            # Cannot cost more WS than the dominion has
            or ($dominion->wizard_strength - $this->getWizardStrengthCost($spell)) < 0

            # Must be available to the dominion's faction (race)
            or !$this->isSpellAvailableToDominion($dominion, $spell)

            # Round must have started for info ops to be castable
            or (!$dominion->round->hasStarted() and $spell->class == 'info')

            # Dominion must not be in protection
            or ($dominion->isUnderProtection() and $spell->scope !== 'self')

            # Must not be a non-information hostile spell within the first day or after offensive actions are disabled
            or ($spell->scope == 'hostile' and $spell->class !== 'info' and ((now()->diffInDays($dominion->round->start_date) < 1) or $dominion->round->hasOffensiveActionsDisabled()))
          )
        {
            return false;
        }
        return true;
    }

    public function getWizardStrengthCost(Spell $spell)
    {

        if($spell->class === 'invasion')
        {
            return 0;
        }

        # Default values
        $scopeCost = [
                'hostile' => 2,
                'friendly' => 2,
                'self' => 2,
            ];
        $classCost = [
                'active' => 3,
                'info' => -1,
                'passive' => 2,
            ];

        $cost = $scopeCost[$spell->scope] + $classCost[$spell->class];

        return $spell->wizard_strength ?? $cost;
    }

    public function getCaster(Dominion $target, string $spellKey): Dominion
    {
        if (!$this->isSpellActive($target, $spellKey))
        {
            return null;
        }

        $spell = Spell::where('key', $spellKey)->first();

        $dominionSpell = DominionSpell::where('spell_id',$spell->id)->where('dominion_id',$target->id)->first();
        return Dominion::findorfail($dominionSpell->caster_id);
    }

    public function getAnnexedDominions(Dominion $legion): Collection
    {
        $spell = Spell::where('key', 'annexation')->first();
        $annexedDominions = collect();

        foreach(DominionSpell::where('caster_id',$legion->id)->where('spell_id', $spell->id)->get() as $dominionSpell)
        {
            $annexedDominions->prepend(Dominion::findorfail($dominionSpell->dominion_id));
        }

        return $annexedDominions;
    }

    public function hasAnnexedDominions(Dominion $legion): bool
    {
        $spell = Spell::where('key', 'annexation')->first();
        return DominionSpell::where('caster_id',$legion->id)->where('spell_id', $spell->id)->first() ? true : false;
    }

    public function isAnnexed(Dominion $dominion): bool
    {
        $spell = Spell::where('key', 'annexation')->first();
        return DominionSpell::where('dominion_id',$dominion->id)->where('spell_id', $spell->id)->first() ? true : false;
    }

    public function getAnnexer(Dominion $dominion): Dominion
    {
        $spell = Spell::where('key', 'annexation')->first();
        $dominionSpell = DominionSpell::where('dominion_id',$dominion->id)->where('spell_id', $spell->id)->first();
        return Dominion::findorfail($dominionSpell->caster_id);
    }

    public function getTicksRemainingOfAnnexation(Dominion $legion, Dominion $dominion): int
    {
        $spell = Spell::where('key', 'annexation')->first();
        return DominionSpell::where('caster_id',$legion->id)->where('dominion_id',$dominion->id)->where('spell_id', $spell->id)->first()->duration;

    }

}
