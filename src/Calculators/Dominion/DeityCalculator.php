<?php

namespace OpenDominion\Calculators\Dominion;

use DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenDominion\Helpers\DeityHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Race;
use OpenDominion\Models\DominionDeity;
use OpenDominion\Models\Deity;

class DeityCalculator
{
    /** @var LandCalculator */
    protected $landCalculator;

    /** @var DeityHelper */
    protected $deityHelper;

    /** @var array */
    protected $activeDeitys = [];

    /**
     * DeityCalculator constructor.
     *
     * @param LandCalculator $landCalculator
     * @param DeityHelper $deityHelper
     */
    public function __construct()
    {
        $this->landCalculator = app(LandCalculator::class);
        $this->deityHelper = app(DeityHelper::class);
    }



    /**
     * Returns a list of deitys currently affecting $dominion.
     *
     * @param Dominion $dominion
     * @param bool $forceRefresh
     * @return Collection
     */
    public function getDeity(Dominion $dominion): Collection
    {
        return DominionDeity::where('dominion_id',$dominion->id)->get();
    }

    public function hasDeity(Dominion $dominion, string $deityKey): bool
    {
        $deity = Deity::where('key', $deityKey)->first();
        return DominionDeity::where('deity_id',$deity->id)->where('dominion_id',$dominion->id)->first() ? true : false;
    }

    /**
     * Returns the remaining duration (in ticks) of a deity affecting $dominion.
     *
     * @todo Rename to getDeityRemainingDuration for clarity
     * @param Dominion $dominion
     * @param string $deity
     * @return int|null
     */
    public function getDeityDuration(Dominion $dominion, string $deityKey): ?int
    {
        if (!$this->isDeityActive($dominion, $deityKey))
        {
            return null;
        }

        $deity = Deity::where('key', $deityKey)->first();
        $dominionDeity = DominionDeity::where('deity_id',$deity->id)->where('dominion_id',$dominion->id)->first();

        return $dominionDeity->duration;
    }

    public function isDeityAvailableToDominion(Dominion $dominion, Deity $deity): bool
    {
        return $this->isDeityAvailableToRace($dominion->race, $deity);
    }

    public function isDeityAvailableToRace(Race $race, Deity $deity): bool
    {
        $isAvailable = true;

        if(count($deity->exclusive_races) > 0 and !in_array($race->name, $deity->exclusive_races))
        {
            $isAvailable = false;
        }

        if(count($deity->excluded_races) > 0 and in_array($race->name, $deity->excluded_races))
        {
            $isAvailable = false;
        }

        return $isAvailable;
    }

    public function canSubmitToDeity(Dominion $dominion, Deity $deity): bool
    {
        if($this->hasDeity($dominion))
        {
            return false;
        }

        return true;
    }

}
