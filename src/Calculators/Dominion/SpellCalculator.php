<?php

namespace OpenDominion\Calculators\Dominion;

use DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenDominion\Helpers\SpellHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Race;
use OpenDominion\Models\DominionSpell;
use OpenDominion\Models\Spell;

class SpellCalculator
{
    /** @var LandCalculator */
    protected $landCalculator;

    /** @var SpellHelper */
    protected $spellHelper;

    /** @var array */
    protected $activeSpells = [];

    /**
     * SpellCalculator constructor.
     *
     * @param LandCalculator $landCalculator
     * @param SpellHelper $spellHelper
     */
    public function __construct()
    {
        $this->landCalculator = app(LandCalculator::class);
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

        // Cost reduction from wizard guilds (2x ratio, max 40%)
        $wizardGuildRatio = ($dominion->building_wizard_guild / $totalLand);
        $spellCostMultiplier = (1 - clamp(2 * $wizardGuildRatio, 0, 0.4));
        $spellCostMultiplier += $dominion->getTechPerkMultiplier('spell_cost');

        if(isset($dominion->title))
        {
            $spellCostMultiplier += $dominion->title->getPerkMultiplier('spell_cost') * $dominion->title->getPerkBonus($dominion);
        }

        return round($baseCost * $spellCostMultiplier);
    }

    /**
     * Returns whether $dominion can currently cast spell $type.
     *
     * Spells require mana and enough wizard strength to be cast.
     *
     * @param Dominion $dominion
     * @param string $spell
     * @return bool
     */
    public function canCast(Dominion $dominion, string $spell): bool
    {
        return (
            ($dominion->resource_mana >= $this->getManaCost($dominion, $spell)) &&
            ($dominion->wizard_strength > 0)
        );
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
        if ($spell->cooldown > 0) {
            $spellLastCast = DB::table('dominion_history')
                ->where('dominion_id', $dominion->id)
                ->where('event', 'cast spell')
                ->where('delta', 'like', "%{$spell->key}%")
                ->orderby('created_at', 'desc')
                ->take(1)
                ->first();
            if ($spellLastCast)
            {
                $hoursSinceCast = now()->startOfHour()->diffInHours(Carbon::parse($spellLastCast->created_at)->startOfHour());
                $hoursUntilRoundStarts = max(0, now()->startOfHour()->diffInHours(Carbon::parse($dominion->round->start_date)->startOfHour()));

                if ($hoursSinceCast < $spell->cooldown)
                {
                    return $spell->cooldown - $hoursSinceCast + $hoursUntilRoundStarts;
                }
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

    /**
     * Returns a list of spells currently affecting $dominion.
     *
     * @param Dominion $dominion
     * @param bool $forceRefresh
     * @return Collection
     */
    public function getPassiveSpellsCast(Dominion $dominion, bool $forceRefresh = false): Collection
    {
        $cacheKey = $dominion->id;

        if (!$forceRefresh && array_has($this->activeSpells, $cacheKey))
        {
            return collect(array_get($this->activeSpells, $cacheKey));
        }

        $data = DB::table('active_spells')
            ->join('dominions', 'dominions.id', '=', 'dominion_id')
            ->join('realms', 'realms.id', '=', 'dominions.realm_id')
            ->where('cast_by_dominion_id', $dominion->id)
            ->where('duration', '>', 0)
            ->orderBy('duration', 'asc')
            ->orderBy('created_at')
            ->get([
                'active_spells.*',
                'dominions.id AS target_dominion_id',
                'dominions.name AS target_dominion_name',
                'realms.number AS target_dominion_realm_number',
            ]);

        array_set($this->activeSpells, $cacheKey, $data->toArray());

        return $data;
    }

    public function getSpellObjectFromKey(string $spellKey): Spell
    {
        return Spell::where('key', $spellKey)->first();
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
        return DominionSpell::where('spell_id',$spell->id)->where('caster_id',$dominion->id)->first() ? true : false;
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
        $isAvailable = true;

        if(count($spell->exclusive_races) > 0 and !in_array($dominion->race->name, $spell->exclusive_races))
        {
            $isAvailable = false;
        }

        if(count($spell->excluded_races) > 0 and in_array($dominion->race->name, $spell->excluded_races))
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

            # Cannot cost more mana than the dominion has
            or $dominion->resource_mana < $this->getManaCost($dominion, $spell->key)

            # Cannot cost more WS than the dominion has
            or ($dominion->wizard_strength - $this->getWizardStrengthCost($spell)) < 0

            # Must be available to the dominion's faction (race)
            or !$this->isSpellAvailableToDominion($dominion, $spell)

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

}
