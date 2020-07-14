<?php

namespace OpenDominion\Services\Dominion\Actions;

use DB;
use OpenDominion\Calculators\Dominion\BuildingCalculator;
use OpenDominion\Calculators\Dominion\CasualtiesCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\GameEvent;
use OpenDominion\Models\Unit;
use OpenDominion\Services\Dominion\GovernmentService;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Services\Dominion\ProtectionService;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Services\NotificationService;
use OpenDominion\Traits\DominionGuardsTrait;

# ODA
use OpenDominion\Helpers\ImprovementHelper;
use OpenDominion\Helpers\SpellHelper;
use OpenDominion\Services\Dominion\Actions\SpellActionService;
use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Calculators\Dominion\Actions\TrainingCalculator;
use OpenDominion\Services\Dominion\GuardMembershipService;

class SendUnitsActionService
{
    use DominionGuardsTrait;

    /**
     * @var int The minimum morale required to initiate an invasion
     */
    protected const MIN_MORALE = 50;

    /** @var BuildingCalculator */
    protected $buildingCalculator;

    /** @var CasualtiesCalculator */
    protected $casualtiesCalculator;

    /** @var GovernmentService */
    protected $governmentService;

    /** @var LandCalculator */
    protected $landCalculator;

    /** @var MilitaryCalculator */
    protected $militaryCalculator;

    /** @var NotificationService */
    protected $notificationService;

    /** @var ProtectionService */
    protected $protectionService;

    /** @var QueueService */
    protected $queueService;

    /** @var RangeCalculator */
    protected $rangeCalculator;

    /** @var SpellCalculator */
    protected $spellCalculator;

    /** @var ImprovementCalculator */
    protected $improvementCalculator;

    /** @var ImprovementHelper */
    protected $improvementHelper;

    /** @var SpellHelper */
    protected $spellHelper;

    /** @var SpellActionService */
    protected $spellActionService;

    /** @var UnitHelper */
    protected $unitHelper;

    /** @var TrainingCalculator */
    protected $trainingCalculator;

    /** @var GuardMembershipService */
    protected $guardMembershipService;

    // todo: refactor
    /** @var GameEvent */
    protected $invasionEvent;

    /**
     * InvadeActionService constructor.
     *
     * @param BuildingCalculator $buildingCalculator
     * @param CasualtiesCalculator $casualtiesCalculator
     * @param GovernmentService $governmentService
     * @param LandCalculator $landCalculator
     * @param MilitaryCalculator $militaryCalculator
     * @param NotificationService $notificationService
     * @param ProtectionService $protectionService
     * @param QueueService $queueService
     * @param RangeCalculator $rangeCalculator
     * @param SpellCalculator $spellCalculator
     */
    public function __construct(
        BuildingCalculator $buildingCalculator,
        CasualtiesCalculator $casualtiesCalculator,
        GovernmentService $governmentService,
        LandCalculator $landCalculator,
        MilitaryCalculator $militaryCalculator,
        NotificationService $notificationService,
        ProtectionService $protectionService,
        QueueService $queueService,
        RangeCalculator $rangeCalculator,
        SpellCalculator $spellCalculator,
        ImprovementCalculator $improvementCalculator,
        ImprovementHelper $improvementHelper,
        SpellHelper $spellHelper,
        SpellActionService $spellActionService,
        UnitHelper $unitHelper,
        TrainingCalculator $trainingCalculator,
        GuardMembershipService $guardMembershipService
    ) {
        $this->buildingCalculator = $buildingCalculator;
        $this->casualtiesCalculator = $casualtiesCalculator;
        $this->governmentService = $governmentService;
        $this->landCalculator = $landCalculator;
        $this->militaryCalculator = $militaryCalculator;
        $this->notificationService = $notificationService;
        $this->protectionService = $protectionService;
        $this->queueService = $queueService;
        $this->rangeCalculator = $rangeCalculator;
        $this->spellCalculator = $spellCalculator;
        $this->improvementCalculator = $improvementCalculator;
        $this->improvementHelper = $improvementHelper;
        $this->spellHelper = $spellHelper;
        $this->spellActionService = $spellActionService;
        $this->unitHelper = $unitHelper;
        $this->trainingCalculator = $trainingCalculator;
        $this->guardMembershipService = $guardMembershipService;
    }

    /**
     * Invades dominion $target from $dominion.
     *
     * @param Dominion $dominion
     * @param Dominion $target
     * @param array $units
     * @return array
     * @throws GameException
     */
    public function sendUnits(Dominion $dominion, Dominion $target, array $units): array
    {
        $this->guardLockedDominion($dominion);
        $this->guardLockedDominion($target);

        DB::transaction(function () use ($dominion, $target, $units) {
            // Checks
            if ($dominion->round->hasOffensiveActionsDisabled()) {
                throw new GameException('Invasions have been disabled for the remainder of the round.');
            }

            if ($this->protectionService->isUnderProtection($dominion)) {
                throw new GameException('You cannot invade while under protection');
            }

            if ($this->protectionService->isUnderProtection($target)) {
                throw new GameException('You cannot invade dominions which are under protection');
            }

            if (!$this->rangeCalculator->isInRange($dominion, $target)) {
                throw new GameException('You cannot invade dominions outside of your range');
            }

            if ($dominion->round->id !== $target->round->id) {
                throw new GameException('Nice try, but you cannot invade cross-round');
            }

            if ($dominion->realm->id === $target->realm->id) {
                throw new GameException('You may not invade other dominions of the same realm.');
            }

            if ($dominion->id == $target->id)
            {
              throw new GameException('You cannot invade yourself.');
            }

            // Sanitize input
            $units = array_map('intval', array_filter($units));
            $landRatio = $this->rangeCalculator->getDominionRange($dominion, $target) / 100;

            if (!$this->hasAnyOP($dominion, $units)) {
                throw new GameException('You need to send at least some units.');
            }

            if (!$this->allUnitsHaveOP($dominion, $units)) {
                throw new GameException('You cannot send units that have no offensive power.');
            }

            if (!$this->hasEnoughUnitsAtHome($dominion, $units)) {
                throw new GameException('You don\'t have enough units at home to send this many units.');
            }

            if (!$this->hasEnoughBoats($dominion, $units)) {
                throw new GameException('You do not have enough boats to send this many units.');
            }

            if ($dominion->morale < static::MIN_MORALE) {
                throw new GameException('You do not have enough morale to invade.');
            }

            #if (!$this->passes33PercentRule($dominion, $target, $units)) {
            #    throw new GameException('You need to leave at least 1/3 of your total defensive power at home (33% rule).');
            #}

            if (!$this->passes43RatioRule($dominion, $target, $landRatio, $units)) {
                throw new GameException('You are sending out too much OP, based on your new home DP (4:3 rule).');
            }

            foreach($units as $amount)
            {
               if($amount < 0) {
                   throw new GameException('Invasion was canceled due to bad input.');
               }
             }

            if ($dominion->race->getPerkValue('cannot_invade') == 1)
            {
                throw new GameException('Your faction is unable to invade.');
            }

            foreach($units as $amount)
            {
                if($amount < 0)
                {
                    throw new GameException('Invasion was canceled due to bad input.');
                }
            }

            // Spell: Rainy Season (cannot invade)
            if ($this->spellCalculator->isSpellActive($dominion, 'rainy_season'))
            {
                throw new GameException('You cannot invade during the Rainy Season.');
            }

            // Check building_limit
            foreach($units as $unitSlot => $amount)
            {
                $buildingLimit = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot,'building_limit');
                if($buildingLimit)
                {
                    // We have building limit for this unit.
                    $buildingLimitedTo = 'building_'.$buildingLimit[0]; # Land type
                    $unitsPerBuilding = (float)$buildingLimit[1]; # Units per building
                    $improvementToIncrease = $buildingLimit[2]; # Resource that can raise the limit

                    $unitsPerBuilding *= (1 + $this->improvementCalculator->getImprovementMultiplierBonus($dominion, $improvementToIncrease));

                    $amountOfLimitingBuilding = $dominion->{$buildingLimitedTo};

                    $upperLimit = intval($amountOfLimitingBuilding * $unitsPerBuilding);

                    if($amount > $upperLimit)
                    {
                        throw new GameException('You can at most send ' . number_format($upperLimit) . ' ' . str_plural($this->unitHelper->getUnitName($unitSlot, $dominion->race), $upperLimit) . '. To send more, you must build more '. ucwords(str_plural($buildingLimit[0], 2)) .' or improve your ' . ucwords(str_plural($buildingLimit[2], 3)) . '.');
                    }
                }
            }

            // Cannot invade until round has started.
            if(!$dominion->round->hasStarted())
            {
                throw new GameException('You cannot invade until the round has started.');
            }

            // Dimensionalists: must have Portals open.
            if($dominion->race->name == 'Dimensionalists' and !$this->spellCalculator->isSpellActive($dominion, 'portal'))
            {
                throw new GameException('You cannot attack unless a portal is open.');
            }

            // Peacekeepers League: can only invade if recently invaded.
            if($this->guardMembershipService->isRoyalGuardMember($dominion) and !$this->militaryCalculator->isOwnRealmRecentlyInvadedByTarget($dominion, $target))
            {
                throw new GameException('As a member of the Peacekeepers League, you can only invade other dominions if they have recently invaded your realm.');
            }

            $this->sendUnitsAction['unitsSent'] = $units;
            $this->sendUnitsAction['travelTime'] = 4;

            $this->handleInvadingUnits($dominion, $units, $target);

            // todo: move to GameEventService
            $this->invasionEvent = GameEvent::create([
                'round_id' => $dominion->round_id,
                'source_type' => Dominion::class,
                'source_id' => $dominion->id,
                'target_type' => Dominion::class,
                'target_id' => $target->id,
                'type' => 'send_units',
                'data' => $this->sendUnitsAction,
            ]);

            $target->save(['event' => HistoryService::EVENT_ACTION_SEND_UNITS]);
            $dominion->save(['event' => HistoryService::EVENT_ACTION_SEND_UNITS]);
        });

        $message = sprintf(
            'Your troops have been sent out to invade of %s.',
            $target->name,
        );

        return [
            'message' => $message,
            'alert-type' => 'success',
            'redirect' => route('dominion.event', [$this->invasionEvent->id])
        ];
    }

    /**
     * Handles the surviving units returning home.
     *
     * @param Dominion $dominion
     * @param array $units
     * @param array $convertedUnits
     */
    protected function handleInvadingUnits(Dominion $dominion, array $units, Dominion $target): void
    {
        foreach($units as $slot => $invadingAmount)
        {
            $unitKey = 'military_unit'.$slot;
            $this->queueService->queueResources(
                'invading',
                $dominion,
                [$unitKey => $invadingAmount],
                $this->getUnitTravelTimeForSlot($dominion, $slot),
                $target
            );

            $dominion->{'military_unit'.$slot} -= $invadingAmount;
        }

        #$dominion->save();
    }

    /**
     * Check if dominion is sending out at least *some* OP.
     *
     * @param Dominion $dominion
     * @param array $units
     * @return bool
     */
    protected function hasAnyOP(Dominion $dominion, array $units): bool
    {
        return ($this->militaryCalculator->getOffensivePower($dominion, null, null, $units) !== 0.0);
    }

    /**
     * Check if all units being sent have positive OP.
     *
     * @param Dominion $dominion
     * @param array $units
     * @return bool
     */

    protected function allUnitsHaveOP(Dominion $dominion, array $units): bool
    {
        foreach ($dominion->race->units as $unit) {
            if (!isset($units[$unit->slot]) || ((int)$units[$unit->slot] === 0)) {
                continue;
            }

            if ($unit->power_offense === 0.0 and $unit->getPerkValue('sendable_with_zero_op') != 1)
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if dominion has enough units at home to send out.
     *
     * @param Dominion $dominion
     * @param array $units
     * @return bool
     */
    protected function hasEnoughUnitsAtHome(Dominion $dominion, array $units): bool
    {
        foreach ($dominion->race->units as $unit) {
            if (!isset($units[$unit->slot]) || ((int)$units[$unit->slot] === 0)) {
                continue;
            }

            if ($units[$unit->slot] > $dominion->{'military_unit' . $unit->slot}) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if dominion has enough boats to send units out.
     *
     * @param Dominion $dominion
     * @param array $units
     * @return bool
     */
    protected function hasEnoughBoats(Dominion $dominion, array $units): bool
    {
        $unitsThatNeedBoats = 0;

        foreach ($dominion->race->units as $unit) {
            if (!isset($units[$unit->slot]) || ((int)$units[$unit->slot] === 0)) {
                continue;
            }

            if ($unit->need_boat) {
                $unitsThatNeedBoats += (int)$units[$unit->slot];
            }
        }

        return ($dominion->resource_boats >= ceil($unitsThatNeedBoats / $dominion->race->getBoatCapacity()));
    }

    /**
     * Check if an invasion passes the 33%-rule.
     *
     * @param Dominion $dominion
     * @param Dominion $target
     * @param array $units
     * @return bool
     */
    protected function passes33PercentRule(Dominion $dominion, Dominion $target, array $units): bool
    {
        #$attackingForceOP = $this->militaryCalculator->getOffensivePower($dominion, $target, null, $units);
        $attackingForceDP = $this->militaryCalculator->getDefensivePower($dominion, null, null, $units);
        $currentHomeForcesDP = $this->militaryCalculator->getDefensivePower($dominion);

        $unitsReturning = [];
        for ($slot = 1; $slot <= 4; $slot++)
        {
            $unitsReturning[$slot] = $this->queueService->getReturningQueueTotalByResource($dominion, "military_unit{$slot}");
        }

        $returningForcesDP = $this->militaryCalculator->getDefensivePower($dominion, null, null, $unitsReturning);

        $totalDP = $currentHomeForcesDP + $returningForcesDP;

        $newHomeForcesDP = ($currentHomeForcesDP - $attackingForceDP);

        return ($newHomeForcesDP >= $totalDP * (1/3));
    }

    /**
     * Check if an invasion passes the 4:3-rule.
     *
     * @param Dominion $dominion
     * @param array $units
     * @return bool
     */
    protected function passes43RatioRule(Dominion $dominion, Dominion $target, float $landRatio, array $units): bool
    {
        $unitsHome = [
            0 => $dominion->military_draftees,
            1 => $dominion->military_unit1 - (isset($units[1]) ? $units[1] : 0),
            2 => $dominion->military_unit2 - (isset($units[2]) ? $units[2] : 0),
            3 => $dominion->military_unit3 - (isset($units[3]) ? $units[3] : 0),
            4 => $dominion->military_unit4 - (isset($units[4]) ? $units[4] : 0)
        ];
        $attackingForceOP = $this->militaryCalculator->getOffensivePower($dominion, $target, $landRatio, $units);
        $newHomeForcesDP = $this->militaryCalculator->getDefensivePower($dominion, null, null, $unitsHome);

        # 5/4 (5:4) rule, changed to 4/3 (4:3)
        $attackingForceMaxOP = (int)ceil($newHomeForcesDP * (4/3));

        return ($attackingForceOP <= $attackingForceMaxOP);
    }

    /**
     * Returns the amount of hours a military unit (with a specific slot) takes
     * to return home after battle.
     *
     * @param Dominion $dominion
     * @param int $slot
     * @return int
     */
    protected function getUnitTravelTimeForSlot(Dominion $dominion, int $slot): int
    {
        $hours = 4;

        /** @var Unit $unit */
        /*
        $unit = $dominion->race->units->filter(function ($unit) use ($slot) {
            return ($unit->slot === $slot);
        })->first();

        if ($unit->getPerkValue('faster_arrival') !== 0)
        {
            $hours -= (int)$unit->getPerkValue('faster_arrival');
        }

        $hours = max(1, $hours);
        */

        return $hours;
    }

    protected function getDefensivePowerWithTemples(
      Dominion $attacker,
      Dominion $target,
      array $units,
      float $landRatio,
      bool $isAmbush
      ): float
    {
        // Values (percentages)
        $dpReductionPerTemple = 2;
        $templeMaxDpReduction = 40;
        $ignoreDraftees = false;

        $dpMultiplierReduction = min(
            (($dpReductionPerTemple * $attacker->building_temple) / $this->landCalculator->getTotalLand($attacker)),
            ($templeMaxDpReduction / 100)
        );

        // Void: Spell (remove DP reduction from Temples)
        if ($this->spellCalculator->isSpellActive($target, 'voidspell'))
        {
            $dpMultiplierReduction = 0;
        }

        // Dark Elf: Unholy Ghost (ignore draftees)
        // Dragon: Dragon's Roar (alias of Unholy Ghost)
        if ($this->spellCalculator->isSpellActive($attacker, 'unholy_ghost'))
        {
            $ignoreDraftees = true;
            $this->invasionResult['result']['ignoreDraftees'] = $ignoreDraftees;
        }

        return $this->militaryCalculator->getDefensivePower(
                                                            $target,
                                                            $attacker,
                                                            $landRatio,
                                                            null,
                                                            $dpMultiplierReduction,
                                                            $ignoreDraftees,
                                                            $this->isAmbush
                                                          );
    }

}
