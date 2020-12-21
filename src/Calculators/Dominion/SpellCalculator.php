<?php

namespace OpenDominion\Calculators\Dominion;

use DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenDominion\Helpers\SpellHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Race;
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
            if ($spellLastCast) {
                $hoursSinceCast = now()->startOfHour()->diffInHours(Carbon::parse($spellLastCast->created_at)->startOfHour());
                if ($hoursSinceCast < $spell->cooldown) {
                    return $spell->cooldown - $hoursSinceCast;
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
    public function getActiveSpells(Dominion $dominion, bool $forceRefresh = false): Collection
    {
        $cacheKey = $dominion->id;

        if (!$forceRefresh && array_has($this->activeSpells, $cacheKey))
        {
            return collect(array_get($this->activeSpells, $cacheKey));
        }

        $data = DB::table('active_spells')
            ->join('dominions', 'dominions.id', '=', 'cast_by_dominion_id')
            ->join('realms', 'realms.id', '=', 'dominions.realm_id')
            ->where('dominion_id', $dominion->id)
            ->where('duration', '>', 0)
            ->orderBy('duration', 'desc')
            ->orderBy('created_at')
            ->get([
                'active_spells.*',
                'dominions.name AS cast_by_dominion_name',
                'realms.number AS cast_by_dominion_realm_number',
            ]);

        array_set($this->activeSpells, $cacheKey, $data->toArray());

        return $data;
    }

    /**
     * Returns whether a particular spell is affecting $dominion right now.
     *
     * @param Dominion $dominion
     * @param string $spell
     * @return bool
     */
    public function isSpellActive(Dominion $dominion, string $spell): bool
    {
        return $this->getActiveSpells($dominion)->contains(function ($value) use ($spell) {
            return ($value->spell === $spell);
        });
    }


    /**
     * Returns the cast of a spell.
     *
     * @param Dominion $dominion
     * @param string $spell
     * @return Dominion
     */
    public function getCaster(Dominion $dominion, string $spell): Dominion
    {
        $spell = $this->getActiveSpells($dominion)->filter(function ($value) use ($spell) {
            return ($value->spell === $spell);
        })->first();

        return Dominion::findOrFail($spell->cast_by_dominion_id);
    }


    /**
     * Returns the remaining duration (in ticks) of a spell affecting $dominion.
     *
     * @todo Rename to getSpellRemainingDuration for clarity
     * @param Dominion $dominion
     * @param string $spell
     * @return int|null
     */
    public function getSpellDuration(Dominion $dominion, string $spell): ?int
    {
        if (!$this->isSpellActive($dominion, $spell)) {
            return null;
        }

        $spell = $this->getActiveSpells($dominion)->filter(function ($value) use ($spell) {
            return ($value->spell === $spell);
        })->first();

        return $spell->duration;
    }


    public function getPassiveSpellPerkValues(Dominion $dominion, string $perkString): array
    {

        $perkValuesFromSpells = [];

        # Get all active spells.
        $activeSpells = $this->getActiveSpells($dominion);

        # Check each spell for the $perk
        foreach($activeSpells as $activeSpell)
        {
            $spell = Spell::where('key', $activeSpell->spell)->first();

            # Does the spell have the perk we're after?
            foreach ($spell->perks as $spellPerk)
            {

                # If it does, update $perkValue.
                if($spellPerk->key === $perkString)
                {
                    $perkValuesFromSpells[] = (float)$spellPerk->pivot->value;
                }
            }
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
        if(!$this->isOnCooldown($dominion, $spell) and $dominion->resource_mana >= $this->getManaCost($dominion, $spell->key))
        {
            return true;
        }
        return false;
    }

}
