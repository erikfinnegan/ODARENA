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
use OpenDominion\Calculators\Dominion\ConversionCalculator;

class InvadeActionService
{
    use DominionGuardsTrait;

    /**
     * @var float Base percentage of boats sunk
     */
    protected const BOATS_SUNK_BASE_PERCENTAGE = 5;

    /**
     * @var float Base percentage of defensive casualties
     */
    protected const CASUALTIES_DEFENSIVE_BASE_PERCENTAGE = 3.825;

    /**
     * @var float Max percentage of defensive casualties
     */
    protected const CASUALTIES_DEFENSIVE_MAX_PERCENTAGE = 6.0;

    /**
     * @var float Base percentage of offensive casualties
     */
    protected const CASUALTIES_OFFENSIVE_BASE_PERCENTAGE = 8.5;

    /**
     * @var int The minimum morale required to initiate an invasion
     */
    protected const MIN_MORALE = 50;

    /**
     * @var float Failing an invasion by this percentage (or more) results in 'being overwhelmed'
     */
    protected const OVERWHELMED_PERCENTAGE = 15.0;

    /**
     * @var int Bonus prestige when invading successfully
     */
    protected const PRESTIGE_CHANGE_ADD = 20;

    /**
     * @var float Base prestige % change for both parties when invading
     */
    protected const PRESTIGE_CHANGE_PERCENTAGE = 8.5;

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

    /** @var ConversionCalculator */
    protected $conversionCalculator;

    // todo: use InvasionRequest class with op, dp, mods etc etc. Since now it's
    // a bit hacky with getting new data between $dominion/$target->save()s

    /** @var array Invasion result array. todo: Should probably be refactored later to its own class */
    protected $invasionResult = [
        'result' => [],
        'attacker' => [
            'unitsLost' => [],
        ],
        'defender' => [
            'unitsLost' => [],
        ],
    ];

    // todo: refactor
    /** @var GameEvent */
    protected $invasionEvent;

    // todo: refactor to use $invasionResult instead
    /** @var int The amount of land lost during the invasion */
    protected $landLost = 0;

    /** @var int The amount of units lost during the invasion */
    protected $unitsLost = 0;

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
        GuardMembershipService $guardMembershipService,
        ConversionCalculator $conversionCalculator
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
        $this->ConversionCalculator = $conversionCalculator;
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
    public function invade(Dominion $dominion, Dominion $target, array $units): array
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

            if (!$this->passes33PercentRule($dominion, $target, $units)) {
                throw new GameException('You need to leave at least 1/3 of your total defensive power at home (33% rule).');
            }

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

            for ($slot = 1; $slot <= 4; $slot++)
            {
                $unit = $target->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                if($this->militaryCalculator->getUnitPowerWithPerks($target, null, null, $unit, 'defense') !== 0.0)
                {
                    $this->invasionResult['defender']['unitsDefending'][$slot] = $target->{'military_unit'.$slot};
                }

            }

            $this->invasionResult['defender']['recentlyInvadedCount'] = $this->militaryCalculator->getRecentlyInvadedCount($target);

            // Handle pre-invasion
            $this->handleBeforeInvasionPerks($dominion);

            // Handle invasion results
            $this->checkInvasionSuccess($dominion, $target, $units);
            $this->checkOverwhelmed();

            if (!isset($this->invasionResult['result']['ignoreDraftees']))
            {
                $this->invasionResult['defender']['unitsDefending']['draftees'] = $target->military_draftees;
            }

            # Only count successful, non-in-realm hits over 75% as victories.
            $countsAsVictory = 0;
            $countsAsFailure = 0;
            $countsAsRaze = 0;
            $countsAsBottomfeed = 0;

            # Successful hits over 75% count as victories
            if($landRatio >= 0.75 and $this->invasionResult['result']['success'])
            {
                $countsAsVictory = 1;
            }

            # Successful hits under 75% count as BFs
            if($landRatio < 0.75 and $this->invasionResult['result']['success'])
            {
                $countsAsBottomfeed = 1;
            }

            # Overwhelmed hits count as failures
            if($this->invasionResult['result']['overwhelmed'])
            {
                $countsAsFailure = 1;
            }

            # Non-overwhelmed unsuccessful hits count as tactical razes
            if(!$this->invasionResult['result']['overwhelmed'] and !$this->invasionResult['result']['success'])
            {
                $countsAsRaze = 1;
            }

            $this->rangeCalculator->checkGuardApplications($dominion, $target);

            $this->handleBoats($dominion, $target, $units);
            $this->handlePrestigeChanges($dominion, $target, $units, $landRatio, $countsAsVictory, $countsAsBottomfeed, $countsAsFailure, $countsAsRaze);
            $this->handleDuringInvasionUnitPerks($dominion, $target, $units);

            $survivingUnits = $this->handleOffensiveCasualties($dominion, $target, $units, $landRatio);
            $totalDefensiveCasualties = $this->handleDefensiveCasualties($dominion, $target, $units, $landRatio);

            if($dominion->race->name === 'Vampires')
            {
                $offensiveConversions = $this->handleVampiricConversionOnOffense($dominion, $target, $units, $landRatio);
            }
            else
            {
                $offensiveConversions = $this->handleOffensiveConversions($dominion, $landRatio, $units, $totalDefensiveCasualties, $target->race->getPerkValue('reduce_conversions'));
            }

            if($target->race->name === 'Vampires')
            {
                $defensiveConversions = $this->handleVampiricConversionOnDefense($target, $dominion, $units, $landRatio);
            }
            else
            {
                $defensiveConversions = $this->handleDefensiveConversions($target, $landRatio, $units, $dominion);
            }

            $this->handleReturningUnits($dominion, $survivingUnits, $offensiveConversions);

            $this->handleMoraleChanges($dominion, $target, $landRatio);
            $this->handleLandGrabs($dominion, $target, $landRatio);
            $this->handleResearchPoints($dominion, $target, $units);

            $this->handleInvasionSpells($dominion, $target);

            # Demon
            $this->handleSoulBloodFoodCollection($dominion, $target, $landRatio);

            # Norse
            $this->handleChampionCreation($dominion, $target, $units, $landRatio, $this->invasionResult['result']['success']);

            # Salvage and Plunder
            $this->handleSalvagingAndPlundering($dominion, $target, $survivingUnits);

            $this->invasionResult['attacker']['unitsSent'] = $units;

            // Stat changes
            if ($this->invasionResult['result']['success'])
            {
                $dominion->stat_total_land_conquered += (int)array_sum($this->invasionResult['attacker']['landConquered']);
                $dominion->stat_total_land_explored += (int)array_sum($this->invasionResult['attacker']['landGenerated']);
                $dominion->stat_attacking_success += $countsAsVictory;
                $dominion->stat_attacking_razes += $countsAsRaze;
                $dominion->stat_attacking_failures += $countsAsFailure;
                $dominion->stat_attacking_bottomfeeds += $countsAsBottomfeed;

                $target->stat_total_land_lost += (int)array_sum($this->invasionResult['attacker']['landConquered']);
                $target->stat_defending_failures += 1;
            }
            else
            {
                $target->stat_defending_success += 1;
                $target->realm->stat_defending_success += 1;
            }

            # Debug before saving:
            #dd($this->invasionResult);

            // todo: move to GameEventService
            $this->invasionEvent = GameEvent::create([
                'round_id' => $dominion->round_id,
                'source_type' => Dominion::class,
                'source_id' => $dominion->id,
                'target_type' => Dominion::class,
                'target_id' => $target->id,
                'type' => 'invasion',
                'data' => $this->invasionResult,
            ]);

            // todo: move to its own method
            // Notification
            if ($this->invasionResult['result']['success']) {
                $this->notificationService->queueNotification('received_invasion', [
                    '_routeParams' => [(string)$this->invasionEvent->id],
                    'attackerDominionId' => $dominion->id,
                    'landLost' => $this->landLost,
                    'unitsLost' => $this->unitsLost,
                ]);
            } else {
                $this->notificationService->queueNotification('repelled_invasion', [
                    '_routeParams' => [(string)$this->invasionEvent->id],
                    'attackerDominionId' => $dominion->id,
                    'attackerWasOverwhelmed' => $this->invasionResult['result']['overwhelmed'],
                    'unitsLost' => $this->unitsLost,
                ]);
            }

            $target->save(['event' => HistoryService::EVENT_ACTION_INVADE]);
            $dominion->save(['event' => HistoryService::EVENT_ACTION_INVADE]);
        });

        $this->notificationService->sendNotifications($target, 'irregular_dominion');

        if ($this->invasionResult['result']['success'])
        {
            $message = sprintf(
                'You are victorious and defeat the forces of %s (#%s), conquering %s new acres of land! During the invasion, your troops also discovered %s acres of land.',
                $target->name,
                $target->realm->number,
                number_format(array_sum($this->invasionResult['attacker']['landConquered'])),
                number_format(array_sum($this->invasionResult['attacker']['landGenerated']))
            );
            $alertType = 'success';
        }
        else
        {
            $message = sprintf(
                'Your army fails to defeat the forces of %s (#%s).',
                $target->name,
                $target->realm->number
            );
            $alertType = 'danger';
        }

        return [
            'message' => $message,
            'alert-type' => $alertType,
            'redirect' => route('dominion.event', [$this->invasionEvent->id])
        ];
    }

    /**
     * Handles prestige changes for both dominions.
     *
     * Prestige gains and losses are based on several factors. The most
     * important one is the range (aka relative land size percentage) of the
     * target compared to the attacker.
     *
     * -   X -  65 equals a very weak target, and the attacker is penalized with a prestige loss, no matter the outcome
     * -  66 -  74 equals a weak target, and incurs no prestige changes for either side, no matter the outcome
     * -  75 - 119 equals an equal target, and gives full prestige changes, depending on if the invasion is successful
     * - 120 - X   equals a strong target, and incurs no prestige changes for either side, no matter the outcome
     *
     * Due to the above, people are encouraged to hit targets in 75-119 range,
     * and are discouraged to hit anything below 66.
     *
     * Failing an attack above 66% range only results in a prestige loss if the
     * attacker is overwhelmed by the target defenses.
     *
     * @param Dominion $dominion
     * @param Dominion $target
     * @param array $units
     */
    protected function handlePrestigeChanges(Dominion $attacker, Dominion $defender, array $units, float $landRatio, int $countsAsVictory, int $countsAsBottomfeed, int $countsAsFailure, int $countsAsRaze): void
    {

        $attackerPrestigeChange = 0;
        $defenderPrestigeChange = 0;

        # Successful hits over 75% give prestige to attacker and remove prestige from defender
        if($countsAsVictory)
        {
            $attackerPrestigeChange += 40;
            $defenderPrestigeChange -= 10;
        }

        # Successful bottomfeeds over 60% give no prestige change.
        if($countsAsBottomfeed and $landRatio >= 0.60)
        {
            $attackerPrestigeChange += 0;
        }

        # Successful bottomfeeds under 60% give negative prestige for attacker.
        if($countsAsBottomfeed and $landRatio < 0.60)
        {
            $attackerPrestigeChange -= 20;
        }

        # Unsuccessful hits give negative prestige.
        if($countsAsFailure)
        {
            $attackerPrestigeChange -= 20;
        }

        # Razes over 75% have no prestige loss for attacker and small gain for defender.
        if($countsAsRaze and $landRatio > 0.75)
        {
            $attackerPrestigeChange += 0;
            $defenderPrestigeChange += 10;
        }

        $attackerPrestigeChange *= max(1, (1 - ($this->invasionResult['defender']['recentlyInvadedCount']/10)));
        $defenderPrestigeChange *= max(1, (1 - ($this->invasionResult['defender']['recentlyInvadedCount']/10)));

        $attackerPrestigeChangeMultiplier = 0;

        // Racial perk
        $attackerPrestigeChangeMultiplier += $attacker->race->getPerkMultiplier('prestige_gains');

        // Unit perk
        $attackerPrestigeChangeMultiplier += $this->militaryCalculator->getPrestigeGainsPerk($attacker, $units);

        // Tech
        $attackerPrestigeChangeMultiplier += $attacker->getTechPerkMultiplier('prestige_gains');

        $attackerPrestigeChange *= (1 + $attackerPrestigeChangeMultiplier);

        // 33% reduction for hitting Barbarians.
        if($defender->race->name === 'Barbarian')
        {
            $attackerPrestigeChange /= 3;
        }

        if ($attackerPrestigeChange !== 0)
        {
            if (!$this->invasionResult['result']['success'])
            {
                // Unsuccessful invasions (bounces) give negative prestige immediately
                $attacker->prestige += $attackerPrestigeChange;
            }
            else
            {
                $slowestTroopsReturnHours = $this->getSlowestUnitReturnHours($attacker, $units);

                $this->queueService->queueResources(
                    'invasion',
                    $attacker,
                    ['prestige' => $attackerPrestigeChange],
                    $slowestTroopsReturnHours
                );
            }

            $this->invasionResult['attacker']['prestigeChange'] = $attackerPrestigeChange;
        }

        if ($defenderPrestigeChange !== 0)
        {
            $defender->prestige += $defenderPrestigeChange;
            $this->invasionResult['defender']['prestigeChange'] = $defenderPrestigeChange;
        }
    }

    /**
     * Handles offensive casualties for the attacking dominion.
     *
     * Offensive casualties are 8.5% of the units needed to break the target,
     * regardless of how many you send.
     *
     * On unsuccessful invasions, offensive casualties are 8.5% of all units
     * you send, doubled if you are overwhelmed.
     *
     * @param Dominion $dominion
     * @param Dominion $target
     * @param array $units
     * @return array All the units that survived and will return home
     */
    protected function handleOffensiveCasualties(Dominion $dominion, Dominion $target, array $units, float $landRatio): array
    {
        $isInvasionSuccessful = $this->invasionResult['result']['success'];
        $isOverwhelmed = $this->invasionResult['result']['overwhelmed'];
        $attackingForceOP = $this->invasionResult['attacker']['op'];
        $targetDP = $this->invasionResult['defender']['dp'];
        $offensiveCasualtiesPercentage = (static::CASUALTIES_OFFENSIVE_BASE_PERCENTAGE / 100);

        # Merfolk: Charybdis' Gape - increase offensive casualties by 50% if target has this spell on.
        if ($this->spellCalculator->isSpellActive($target, 'charybdis_gape'))
        {
            $offensiveCasualtiesPercentage *= 1.50;
        }

        # Demon: Infernal Fury - increase offensive casualties by 20% if target has this spell on.
        if ($this->spellCalculator->isSpellActive($target, 'infernal_fury'))
        {
            $offensiveCasualtiesPercentage *= 1.20;
        }

        $offensiveUnitsLost = [];

        if ($isInvasionSuccessful)
        {
            $totalUnitsSent = array_sum($units);

            $averageOPPerUnitSent = ($attackingForceOP / $totalUnitsSent);
            $OPNeededToBreakTarget = ($targetDP + 1);
            $unitsNeededToBreakTarget = round($OPNeededToBreakTarget / $averageOPPerUnitSent);

            $totalUnitsLeftToKill = ceil($unitsNeededToBreakTarget * $offensiveCasualtiesPercentage);

            foreach ($units as $slot => $amount)
            {
                $slotTotalAmountPercentage = ($amount / $totalUnitsSent);

                if ($slotTotalAmountPercentage === 0)
                {
                    continue;
                }

                $unitsToKill = ceil($unitsNeededToBreakTarget * $offensiveCasualtiesPercentage * $slotTotalAmountPercentage);
                $offensiveUnitsLost[$slot] = $unitsToKill;

                if ($totalUnitsLeftToKill < $unitsToKill)
                {
                    $unitsToKill = $totalUnitsLeftToKill;
                }

                $totalUnitsLeftToKill -= $unitsToKill;

                $fixedCasualtiesPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'fixed_casualties');
                if ($fixedCasualtiesPerk)
                {
                    $fixedCasualtiesRatio = $fixedCasualtiesPerk / 100;
                    $unitsActuallyKilled = (int)ceil($amount * $fixedCasualtiesRatio);
                    $offensiveUnitsLost[$slot] = $unitsActuallyKilled;
                }
            }
        }
        else
        {
            if ($isOverwhelmed)
            {
                $offensiveCasualtiesPercentage *= 2;
            }

            foreach ($units as $slot => $amount) {
                $fixedCasualtiesPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'fixed_casualties');
                if ($fixedCasualtiesPerk) {
                    $fixedCasualtiesRatio = $fixedCasualtiesPerk / 100;
                    $unitsToKill = (int)ceil($amount * $fixedCasualtiesRatio);
                    $offensiveUnitsLost[$slot] = $unitsToKill;
                    continue;
                }

                $unitsToKill = (int)ceil($amount * $offensiveCasualtiesPercentage);
                $offensiveUnitsLost[$slot] = $unitsToKill;
            }
        }

        foreach ($offensiveUnitsLost as $slot => &$amount)
        {
            // Reduce amount of units to kill by further multipliers
            $unitsToKillMultiplier = $this->casualtiesCalculator->getOffensiveCasualtiesMultiplierForUnitSlot($dominion, $target, $slot, $units, $landRatio, $isOverwhelmed, $attackingForceOP, $targetDP, $isInvasionSuccessful, $this->isAmbush);

            $this->invasionResult['attacker']['unitPerks']['offensiveCasualties'][$slot] = $unitsToKillMultiplier;

            if ($unitsToKillMultiplier !== 0)
            {
                $amount = (int)floor($amount * $unitsToKillMultiplier);
            }
            else
            {
                $amount = 0;
            }

            if ($amount > 0)
            {
                // Actually kill the units. RIP in peace, glorious warriors ;_;7
                $dominion->{"military_unit{$slot}"} -= $amount;
                $this->invasionResult['attacker']['unitsLost'][$slot] = $amount;
                $dominion->{'stat_total_unit' . $slot . '_lost'} += $amount;
                $target->{'stat_total_units_killed'} += $amount;
            }
        }
        unset($amount); // Unset var by reference from foreach loop above to prevent unintended side-effects

        $survivingUnits = $units;

        foreach ($units as $slot => $amount) {
            if (isset($offensiveUnitsLost[$slot])) {
                $survivingUnits[$slot] -= $offensiveUnitsLost[$slot];
            }
        }

        #$survivingUnits['attackerUnitsDiedInBattleSlot1'] = $attackerUnitsDiedInBattleSlot1;

        return $survivingUnits;
    }

    /**
     * Handles defensive casualties for the defending dominion.
     *
     * Defensive casualties are base 4.5% across all units that help defending.
     *
     * This scales with relative land size, and invading OP compared to
     * defending OP, up to max 6%.
     *
     * Unsuccessful invasions results in reduced defensive casualties, based on
     * the invading force's OP.
     *
     * Defensive casualties are spread out in ratio between all units that help
     * defend, including draftees. Being recently invaded reduces defensive
     * casualties: 100%, 80%, 60%, 50%, 33%, 0.25%.
     *
     * @param Dominion $dominion
     * @param Dominion $target
     * @return int
     */
    protected function handleDefensiveCasualties(Dominion $dominion, Dominion $target, array $units, float $landRatio): int
    {
        if ($this->invasionResult['result']['overwhelmed'])
        {
            return 0;
        }

        $attackingForceOP = $this->invasionResult['attacker']['op'];
        $targetDP = $this->invasionResult['defender']['dp'];
        $defensiveCasualtiesPercentage = (static::CASUALTIES_DEFENSIVE_BASE_PERCENTAGE / 100);

        // Scale casualties further with invading OP vs target DP
        $defensiveCasualtiesPercentage *= ($attackingForceOP / $targetDP);

        // Reduce casualties if target has been hit recently
        $recentlyInvadedCount = $this->invasionResult['defender']['recentlyInvadedCount'];

        $defensiveCasualtiesPercentage *= max(0.1, (1 - ($this->invasionResult['defender']['recentlyInvadedCount']/10)));

        // Cap max casualties
        $defensiveCasualtiesPercentage = min(
            $defensiveCasualtiesPercentage,
            (static::CASUALTIES_DEFENSIVE_MAX_PERCENTAGE / 100)
        );

        $defensiveUnitsLost = [];

        // Demon: racial spell Infernal Fury increases defensive casualties by 20%.
        $casualtiesMultiplier = 1;

        if ($this->spellCalculator->isSpellActive($dominion, 'infernal_fury') and $landRatio > 75 and $this->invasionResult['result']['success'])
        {
            $casualtiesMultiplier += 0.2;
        }

        $drafteesLost = (int)floor($target->military_draftees * $defensiveCasualtiesPercentage * ($this->casualtiesCalculator->getDefensiveCasualtiesMultiplierForUnitSlot($target, $dominion, null, $units, $landRatio, $this->isAmbush) * $casualtiesMultiplier));

        // Dark Elf: Unholy Ghost
        // alias as Dragon: Dragon's Roar
        if ($this->spellCalculator->isSpellActive($dominion, 'unholy_ghost'))
        {
            $drafteesLost = 0;
        }

        // Undead: Desecration - Triples draftee casualties (capped by target's number of draftees)
        if ($this->spellCalculator->isSpellActive($dominion, 'desecration'))
        {
            $drafteesLost = min($target->military_draftees, $drafteesLost * 3);
        }

        if ($drafteesLost > 0)
        {
            $target->military_draftees -= $drafteesLost;

            $this->unitsLost += $drafteesLost; // todo: refactor
            $this->invasionResult['defender']['unitsLost']['draftees'] = $drafteesLost;
        }

        // Non-draftees
        foreach ($target->race->units as $unit)
        {
            if ($unit->power_defense === 0.0)
            {
                continue;
            }

            $slotLostMultiplier = $this->casualtiesCalculator->getDefensiveCasualtiesMultiplierForUnitSlot($target, $dominion, $unit->slot, $units, $landRatio, $this->isAmbush);
            $slotLost = (int)floor($target->{"military_unit{$unit->slot}"} * $defensiveCasualtiesPercentage * $slotLostMultiplier * $casualtiesMultiplier);
            $this->invasionResult['defender']['unitPerks']['defensiveCasualties'][$unit->slot] = $slotLostMultiplier;

            if ($slotLost > 0)
            {
                $defensiveUnitsLost[$unit->slot] = $slotLost;
                $target->{'stat_total_unit' . $unit->slot . '_lost'} += $slotLost;
                $dominion->{'stat_total_units_killed'} += $slotLost;
                $this->unitsLost += $slotLost; // todo: refactor
            }
        }

        # Look for dies_into amongst the dead defenders.
        foreach($defensiveUnitsLost as $slot => $casualties)
        {
            if($newUnitSlot = $target->race->getUnitPerkValueForUnitSlot($slot, 'dies_into'))
            {
                # Add the unit to queue.
                $target->{'military_unit' . $newUnitSlot} += $casualties;
            }
        }


        foreach ($defensiveUnitsLost as $slot => $amount)
        {
            $target->{"military_unit{$slot}"} -= $amount;

            $this->invasionResult['defender']['unitsLost'][$slot] = $amount;
        }

        return $this->unitsLost;
    }

    /**
     * Handles land grabs and losses upon successful invasion.
     *
     * todo: description
     *
     * @param Dominion $dominion
     * @param Dominion $target
     */
    protected function handleLandGrabs(Dominion $dominion, Dominion $target, float $landRatio): void
    {
        $landRatio = $landRatio * 100;
        $this->invasionResult['attacker']['landSize'] = $this->landCalculator->getTotalLand($dominion);
        $this->invasionResult['defender']['landSize'] = $this->landCalculator->getTotalLand($target);

        $isInvasionSuccessful = $this->invasionResult['result']['success'];

        // Nothing to grab if invasion isn't successful :^)
        if (!$isInvasionSuccessful) {
            return;
        }

        if (!isset($this->invasionResult['attacker']['landConquered']))
        {
            $this->invasionResult['attacker']['landConquered'] = [];
        }

        if (!isset($this->invasionResult['attacker']['landGenerated']))
        {
            $this->invasionResult['attacker']['landGenerated'] = [];
        }

        if (!isset($this->invasionResult['defender']['landLost']))
        {
            $this->invasionResult['defender']['landLost'] = [];
        }

        if (!isset($this->invasionResult['defender']['buildingsLost']))
        {
            $this->invasionResult['defender']['buildingsLost'] = [];
        }

        if (!isset($this->invasionResult['defender']['totalBuildingsLost']))
        {
            $this->invasionResult['defender']['totalBuildingsLost'] = 0;
        }

        $rangeMultiplier = ($landRatio / 100);

        $attackerLandWithRatioModifier = ($this->landCalculator->getTotalLand($dominion));

        if ($landRatio < 55)
        {
            $acresLost = (0.304 * ($rangeMultiplier ** 2) - 0.227 * $rangeMultiplier + 0.048) * $attackerLandWithRatioModifier;
        }
        elseif ($landRatio < 75)
        {
            $acresLost = (0.154 * $rangeMultiplier - 0.069) * $attackerLandWithRatioModifier;
        }
        else
        {
            $acresLost = (0.129 * $rangeMultiplier - 0.048) * $attackerLandWithRatioModifier;
        }

        $acresLost *= 0.75;

        $acresLost = (int)max(floor($acresLost), 10);

        $landLossRatio = ($acresLost / $this->landCalculator->getTotalLand($target));
        $landAndBuildingsLostPerLandType = $this->landCalculator->getLandLostByLandType($target, $landLossRatio);

        $landGainedPerLandType = [];
        foreach ($landAndBuildingsLostPerLandType as $landType => $landAndBuildingsLost)
        {
            $buildingsToDestroy = $landAndBuildingsLost['buildingsToDestroy'];
            $landLost = $landAndBuildingsLost['landLost'];
            $buildingsLostForLandType = $this->buildingCalculator->getBuildingTypesToDestroy($target, $buildingsToDestroy, $landType);

            // Remove land
            $target->{"land_$landType"} -= $landLost;
            $this->invasionResult['defender']['totalBuildingsLost'] += $landLost;

            // Add discounted land for buildings destroyed
            #$target->discounted_land += $buildingsToDestroy;

            // Destroy buildings
            foreach ($buildingsLostForLandType as $buildingType => $buildingsLost)
            {
                $builtBuildingsToDestroy = $buildingsLost['builtBuildingsToDestroy'];
                $resourceName = "building_{$buildingType}";
                $target->$resourceName -= $builtBuildingsToDestroy;

                if (isset($this->invasionResult['defender']['buildingsLost'][$buildingType]))
                {
                    $this->invasionResult['defender']['buildingsLost'][$buildingType] += $buildingsLost;
                }
                else
                {
                    $this->invasionResult['defender']['buildingsLost'][$buildingType] = $buildingsLost;
                }

                $buildingsInQueueToRemove = $buildingsLost['buildingsInQueueToRemove'];

                if ($buildingsInQueueToRemove !== 0)
                {
                    $this->queueService->dequeueResource('construction', $target, $resourceName, $buildingsInQueueToRemove);
                }
            }

            $landConquered = (int)round($landLost);
            $landGenerated = (int)round($landConquered);
            $landGained = ($landConquered + $landGenerated);

            # No generated acres for in-realm invasions.
            if($dominion->realm->id == $target->realm->id)
            {
                $landGenerated = 0;
            }

            # No generated acres for multiple invasions on the same target by
            # the same attacker within a time period defined in the function.
            # Currently three hours, as of writing.
            # $target = defender
            # $dominion = attacker
            if($this->militaryCalculator->getRecentlyInvadedCountByAttacker($target, $dominion) > 0)
            {
                $landGenerated = 0;
            }

            $landGained = ($landConquered + $landGenerated);

            if (!isset($landGainedPerLandType["land_{$landType}"])) {
                $landGainedPerLandType["land_{$landType}"] = 0;
            }
            $landGainedPerLandType["land_{$landType}"] += $landGained;

            # Attacker: Land Conquered
            if(isset($this->invasionResult['attacker']['landConquered'][$landType]))
            {
              $this->invasionResult['attacker']['landConquered'][$landType] += $landConquered;
            }
            else
            {
              $this->invasionResult['attacker']['landConquered'][$landType] = $landConquered;
            }

            # Attacker: Land Generated
            if(isset($this->invasionResult['attacker']['landGenerated'][$landType]))
            {
              $this->invasionResult['attacker']['landGenerated'][$landType] += $landConquered;
            }
            else
            {
              $this->invasionResult['attacker']['landGenerated'][$landType] = $landConquered;
            }

            # Defender: Land Lost
            if(isset($this->invasionResult['defender']['landLost'][$landType]))
            {
              $this->invasionResult['defender']['landLost'][$landType] += $landConquered;
            }
            else
            {
              $this->invasionResult['defender']['landLost'][$landType] = $landConquered;
            }

        }

        // Calculate extra generated land (becomes home land type).
        $landGeneratedMultiplier = 0;
        $homeLandType = 'land_' . $dominion->race->home_land_type;

        // Add 20% to generated if Nomad spell Campaign is enabled.
        if ($this->spellCalculator->isSpellActive($dominion, 'campaign'))
        {
            $landGeneratedMultiplier += 0.25;
        }

        // Improvement: Cartography
        $landGeneratedMultiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'cartography');

        // Resource: XP (max +100% from 1,000,000 XP) – only for factions which cannot take advancements (Troll)
        if($dominion->race->getPerkValue('cannot_tech'))
        {
            $landGeneratedMultiplier += min($dominion->resource_tech, 1000000) / 1000000;
        }

        $extraLandGenerated = round($acresLost * $landGeneratedMultiplier);

        $this->invasionResult['attacker']['extraLandGenerated'][$dominion->race->home_land_type] = $extraLandGenerated;

        if(isset($landGainedPerLandType[$homeLandType]))
        {
          $landGainedPerLandType[$homeLandType] += $extraLandGenerated;
        }
        else
        {
          $landGainedPerLandType[$homeLandType] = $extraLandGenerated;
        }

        $this->landLost = $acresLost;

        $queueData = $landGainedPerLandType;

        // Only gain discounted acres when hitting over 75%.
        /*
        if ($landRatio >= 75)
        {
            $queueData += [
                'discounted_land' => array_sum($landGainedPerLandType)
            ];
        }
        */

        $this->queueService->queueResources(
            'invasion',
            $dominion,
            $queueData
        );
    }

    /**
     * Handles morale changes for attacker.
     *
     * Attacker morale gets reduced by 5%, more so if they attack a target below
     * 75% range (up to 10% reduction at 40% target range).
     *
     * @param Dominion $dominion
     * @param Dominion $target
     */
    protected function handleMoraleChanges(Dominion $dominion, Dominion $target, float $landRatio): void
    {

        $landRatio = $landRatio * 100;
        # For successful invasions...
        if($this->invasionResult['result']['success'])
        {
            # Drop 10% morale for hits under 60%.
            if($landRatio < 60)
            {
                $attackerMoraleChange = -15;
            }
            # No change for hits in lower RG (60-75).
            elseif($landRatio < 75)
            {
                $attackerMoraleChange = 0;
            }
            # Increase 15% for hits 75-85%.
            elseif($landRatio < 85)
            {
                $attackerMoraleChange = 15;
            }
            # Increase 20% for hits 85-100%
            elseif($landRatio < 100)
            {
                $attackerMoraleChange = 20;
            }
            # Increase 25% for hits 100% and up.
            else
            {
                $attackerMoraleChange = 25;
            }
            # Defender gets the inverse of attacker morale change,
            # if it greater than 0.
            if($attackerMoraleChange > 0)
            {
                $defenderMoraleChange = $attackerMoraleChange*-1;
            }
            else
            {
                $defenderMoraleChange = 0;
            }

        }
        # For failed invasions...
        else
        {
            # If overwhelmed, attacker loses 20%, defender gets nothing.
            if($this->invasionResult['result']['overwhelmed'])
            {
                $attackerMoraleChange = -20;
                $defenderMoraleChange = 0;
            }
            # Otherwise, -10% for attacker and +5% for defender
            else
            {
                $attackerMoraleChange = -10;
                $defenderMoraleChange = 10;
            }
        }

        # Change attacker morale.

        // Make sure it doesn't go below 0.
        if(($dominion->morale + $attackerMoraleChange) < 0)
        {
          $attackerMoraleChange = 0;
        }
        $dominion->morale += $attackerMoraleChange;

        # Change defender morale.

        // Make sure it doesn't go below 0.
        if(($target->morale + $defenderMoraleChange) < 0)
        {
          $defenderMoraleChange = 0;
        }
        $target->morale += $defenderMoraleChange;

        $this->invasionResult['attacker']['moraleChange'] = $attackerMoraleChange;
        $this->invasionResult['defender']['moraleChange'] = $defenderMoraleChange;

    }

    /**
     * @param Dominion $dominion -- DEFENDER
     * @param float $landRatio
     * @param array $units
     * @param int $totalDefensiveCasualties
     * @return array
     */
    protected function handleOffensiveConversions(
        Dominion $dominion,
        float $landRatio,
        array $units,
        int $totalDefensiveCasualties,
        int $reduceConversions
    ): array {
        $isInvasionSuccessful = $this->invasionResult['result']['success'];
        $convertedUnits = array_fill(1, 4, 0);

        // Racial: Apply reduce_conversions
        $totalDefensiveCasualties = $totalDefensiveCasualties * (1 - ($reduceConversions / 100));

        # Conversions only for non-overwhelmed invasions with casualties where the attacker is one of these factions
        if
          (
              $this->invasionResult['result']['overwhelmed'] or
              $totalDefensiveCasualties === 0 or
              !in_array($dominion->race->name, ['Lycanthrope','Spirit', 'Undead','Sacred Order','Afflicted'], true)
          )
        {
            return $convertedUnits;
        }

        $conversionBaseMultiplier = 0.06;
        $conversionMultiplier = 0;

        // Calculate conversion bonuses
        # Spell: Parasitic Hunger
        if ($this->spellCalculator->isSpellActive($dominion, 'parasitic_hunger'))
        {
          $conversionMultiplier += 0.50;
        }
        # Tech: up to +15%
        if($dominion->getTechPerkMultiplier('conversions'))
        {
          $conversionMultiplier += $dominion->getTechPerkMultiplier('conversions');
        }
        # Title: Embalmer
        if($dominion->title->getPerkMultiplier('conversions'))
        {
          $conversionMultiplier += $dominion->title->getPerkMultiplier('conversions') * $dominion->title->getPerkBonus($dominion);
        }

        $conversionBaseMultiplier *= (1 + $conversionMultiplier);

        $this->invasionResult['attacker']['conversionAnalysis']['conversionMultiplier'] = $conversionMultiplier;
        $this->invasionResult['attacker']['conversionAnalysis']['conversionBaseMultiplier'] = $conversionBaseMultiplier;

        # Calculate converting units
        $totalConvertingUnits = 0;

        $unitsWithConversionPerk = $dominion->race->units->filter(static function (Unit $unit) use (
            $landRatio,
            $units,
            $dominion
        ) {
            if (!array_key_exists($unit->slot, $units) || ($units[$unit->slot] === 0)) {
                return false;
            }

            $staggeredConversionPerk = $dominion->race->getUnitPerkValueForUnitSlot(
                $unit->slot,
                'staggered_conversion');

            if ($staggeredConversionPerk)
            {
                foreach ($staggeredConversionPerk as $rangeConversionPerk)
                {
                    $range = ((int)$rangeConversionPerk[0]) / 100;
                    if ($range <= $landRatio)
                    {
                        return true;
                    }
                }
                return false;
            }

            return $unit->getPerkValue('conversion');
        });

        foreach ($unitsWithConversionPerk as $unit)
        {
            $totalConvertingUnits += $units[$unit->slot];
        }

        $this->invasionResult['attacker']['conversionAnalysis']['totalConvertingUnits'] = $totalConvertingUnits;
        $this->invasionResult['attacker']['conversionAnalysis']['totalDefensiveCasualties'] = $totalDefensiveCasualties;

        $totalConverts = min($totalConvertingUnits * $conversionBaseMultiplier, $totalDefensiveCasualties * 1.75) * $landRatio;

        if(!$isInvasionSuccessful)
        {
            $totalConverts = min($totalDefensiveCasualties, $totalConverts);
            $totalConverts /= 12;
        }

        foreach ($unitsWithConversionPerk as $unit)
        {
            $conversionPerk = $unit->getPerkValue('conversion');
            $convertingUnitsForSlot = $units[$unit->slot];
            $convertingUnitsRatio = $convertingUnitsForSlot / $totalConvertingUnits;
            $totalConversionsForUnit = floor($totalConverts * $convertingUnitsRatio);

            if (!$conversionPerk)
            {
                $staggeredConversionPerk = $dominion->race->getUnitPerkValueForUnitSlot(
                    $unit->slot,
                    'staggered_conversion'
                );

                foreach ($staggeredConversionPerk as $rangeConversionPerk)
                {
                    $range = ((int)$rangeConversionPerk[0]) / 100;
                    $slots = $rangeConversionPerk[1];

                    if ($range > $landRatio) {
                        continue;
                    }

                    $conversionPerk = $slots;
                }
            }

            $slotsToConvertTo = strlen($conversionPerk);
            $totalConvertsForSlot = floor($totalConversionsForUnit / $slotsToConvertTo);

            foreach (str_split($conversionPerk) as $slot) {
                $convertedUnits[(int)$slot] += (int)$totalConvertsForSlot;
            }
        }

        if (!isset($this->invasionResult['attacker']['conversion']) && array_sum($convertedUnits) > 0)
        {
            $this->invasionResult['attacker']['conversion'] = $convertedUnits;
        }

        $dominion->stat_total_units_converted += array_sum($convertedUnits);

        return $convertedUnits;
    }

    /**
     * @param Dominion $dominion
     * @param float $landRatio
     * @param array $units
     * @param int $totalDefensiveCasualties
     * @return array
     */
    protected function handleDefensiveConversions(
        Dominion $dominion,
        float $landRatio,
        array $units,
        #int $reduceConversions,
        Dominion $attacker
    ): void {

        # Invert the land ratio.
        $landRatio = 1/$landRatio;

        $convertedUnits = array_fill(1, 4, 0);
        $totalOffensiveCasualties = array_sum($this->invasionResult['attacker']['unitsLost']);

        # Remove units with fixed casualties greater than 50%.
        foreach($this->invasionResult['attacker']['unitsLost'] as $unit => $lost)
        {
           if($attacker->race->getUnitPerkValueForUnitSlot($unit, "fixed_casualties") >= 50)
           {
              $totalOffensiveCasualties -= $lost;
           }
        }

        // Racial: Apply reduce_conversions
        $totalOffensiveCasualties = $totalOffensiveCasualties * (1 - ($attacker->race->getPerkMultiplier('reduce_conversions')));

        # Conversions only for non-overwhelmed invasions with casualties and where the attacker is one of these factions
        if
          (
              $this->invasionResult['result']['overwhelmed'] or
              $totalOffensiveCasualties === 0 or
              !in_array($dominion->race->name, ['Lycanthrope', 'Spirit', 'Undead', 'Sacred Order', 'Afflicted'], true)
          )
        {
            return;
        }

        $conversionBaseMultiplier = 0.06;
        $conversionMultiplier = 0;

        // Calculate conversion bonuses
        # Spell: Parasitic Hunger
        if ($this->spellCalculator->isSpellActive($dominion, 'parasitic_hunger'))
        {
          $conversionMultiplier += 0.50;
        }
        # Tech: up to +15%
        if($dominion->getTechPerkMultiplier('conversions'))
        {
          $conversionMultiplier += $dominion->getTechPerkMultiplier('conversions');
        }
        # Title: Embalmer
        if($dominion->title->getPerkMultiplier('conversions'))
        {
          $conversionMultiplier += $dominion->title->getPerkMultiplier('conversions') * $dominion->title->getPerkBonus($dominion);
        }

        $conversionBaseMultiplier *= (1 + $conversionMultiplier);

        $this->invasionResult['defender']['conversionAnalysis']['conversionMultiplier'] = $conversionMultiplier;
        $this->invasionResult['defender']['conversionAnalysis']['conversionBaseMultiplier'] = $conversionBaseMultiplier;

        # Calculate converting units
        $totalConvertingUnits = 0;

        $unitsWithConversionPerk = $dominion->race->units->filter(static function (Unit $unit) use (
            $landRatio,
            $dominion
        ) {
            if (($dominion->{'military_unit'.$unit->slot} === 0))
            {
                return false;
            }

            $staggeredConversionPerk = $dominion->race->getUnitPerkValueForUnitSlot(
                $unit->slot,
                'staggered_conversion');

            if ($staggeredConversionPerk)
            {
                foreach ($staggeredConversionPerk as $rangeConversionPerk)
                {
                    $range = ((int)$rangeConversionPerk[0]) / 100;
                    if ($range <= $landRatio)
                    {
                        return true;
                    }
                }

                return false;
            }

            return $unit->getPerkValue('conversion');
        });

        foreach ($unitsWithConversionPerk as $unit)
        {
            $totalConvertingUnits += $dominion->{'military_unit'.$unit->slot};
        }

        $this->invasionResult['defender']['conversionAnalysis']['totalConvertingUnits'] = $totalConvertingUnits;
        $this->invasionResult['defender']['conversionAnalysis']['totalOffensiveCasualties'] = $totalOffensiveCasualties;

        $totalConverts = min($totalConvertingUnits * $conversionBaseMultiplier, $totalOffensiveCasualties * 1.75) * $landRatio;
        $totalConverts = min($totalOffensiveCasualties, $totalConverts);

        if($this->invasionResult['result']['success'])
        {
            $totalConverts /= 3;
        }

        $this->invasionResult['defender']['totalConverts'] = $totalConverts;


        foreach ($unitsWithConversionPerk as $unit)
        {
            $conversionPerk = $unit->getPerkValue('conversion');
            $convertingUnitsForSlot = $dominion->{'military_unit'.$unit->slot};
            $convertingUnitsRatio = $convertingUnitsForSlot / $totalConvertingUnits;
            $totalConversionsForUnit = floor($totalConverts * $convertingUnitsRatio);

            if (!$conversionPerk) {
                $staggeredConversionPerk = $dominion->race->getUnitPerkValueForUnitSlot(
                    $unit->slot,
                    'staggered_conversion'
                );

                foreach ($staggeredConversionPerk as $rangeConversionPerk) {
                    $range = ((int)$rangeConversionPerk[0]) / 100;
                    $slots = $rangeConversionPerk[1];

                    if ($range > $landRatio) {
                        continue;
                    }

                    $conversionPerk = $slots;
                }
            }

            $slotsToConvertTo = strlen($conversionPerk);
            $totalConvertsForSlot = floor($totalConversionsForUnit / $slotsToConvertTo);

            foreach (str_split($conversionPerk) as $slot) {
                $convertedUnits[(int)$slot] += (int)$totalConvertsForSlot;
            }
        }

        if (!isset($this->invasionResult['defender']['conversion']) && array_sum($convertedUnits) > 0)
        {
            $this->invasionResult['defender']['conversion'] = $convertedUnits;
        }

        $dominion->stat_total_units_converted += array_sum($convertedUnits);

        # Defensive conversions take 6 ticks to appear
        foreach($convertedUnits as $slot => $amount)
        {
            $unitKey = 'military_unit'.$slot;
            $this->queueService->queueResources(
                'training',
                $dominion,
                [$unitKey => $amount],
                6
            );
        }
    }

    /**
     * @param Dominion $attacker
     * @param Dominion $defender
     * @param float $landRatio
     * @param array $units
     * @param int $totalDefensiveCasualties
     * @return array
     */
    protected function handleVampiricConversionOnOffense(Dominion $attacker, Dominion $defender, array $units, float $landRatio): array
    {

        $convertedUnits = array_fill(1, 3, 0);

        if($this->invasionResult['result']['overwhelmed'] or $attacker->race->name !== 'Vampires')
        {
            return $convertedUnits;
        }

        $conversionMultiplier = $this->ConversionCalculator->getConversionMultiplier($attacker, $defender, $units, null);

        $this->invasionResult['attacker']['conversionAnalysis']['conversionMultiplier'] = $conversionMultiplier;

        # Did we send any units with vampiric_conversion?
        $unitWithVampiricConversionPerk = $attacker->race->units->filter(static function (Unit $unit) use ($units)
        {
            if (!array_key_exists($unit->slot, $units) or ($units[$unit->slot] === 0))
            {
                return false;
            }

            return $unit->getPerkValue('vampiric_conversion');
        });

        $totalVampiricConvertingUnits = 0;
        foreach ($unitWithVampiricConversionPerk as $unit)
        {
            $totalVampiricConvertingUnits += $units[$unit->slot];
        }

        $this->invasionResult['attacker']['conversionAnalysis']['totalConvertingUnits'] = $totalVampiricConvertingUnits;

        $unit2Range = $attacker->race->getUnitPerkValueForUnitSlot(4, 'vampiric_conversion');

        foreach($this->invasionResult['defender']['unitsLost'] as $slot => $amountKilled)
        {

            if($slot == 'draftees')
            {
                $unitRawDp = 1;
            }
            else
            {
                $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $unitRawDp = $this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense');

                # Remove units with fixed casualties > 50%.
                if($defender->race->getUnitPerkValueForUnitSlot($slot, "fixed_casualties") >= 50)
                {
                    $amount = 0;
                }
            }

            # If less than unit2 range, it's a unit1.
            if($unitRawDp <= $unit2Range[0])
            {
                $slotConvertedTo = 1;
                $unitsPerConversion = 1;
            }
            # If it's in the unit2 range, it's a unit2.
            elseif($unitRawDp > $unit2Range[0] and $unitRawDp <= $unit2Range[1])
            {
                $slotConvertedTo = 2;
                $unitsPerConversion = 2;
            }
            # If greater than unit2 range, it's a unit3.
            elseif($unitRawDp > $unit2Range[1])
            {
                $slotConvertedTo = 3;
                $unitsPerConversion = 3;
            }

            $unitsConverted = $amountKilled / ($unitsPerConversion / $conversionMultiplier);

            if(!$this->invasionResult['result']['success'])
            {
                $unitsConverted /= 12;
            }

            $convertedUnits[$slotConvertedTo] += intval(min($amountKilled, $unitsConverted));

        }

        if (!isset($this->invasionResult['attacker']['conversion']) && array_sum($convertedUnits) > 0)
        {
            $this->invasionResult['attacker']['conversion'] = $convertedUnits;
        }

        $attacker->stat_total_units_converted += array_sum($convertedUnits);

        return $convertedUnits;
    }

    /**
     * @param Dominion $attacker
     * @param Dominion $defender
     * @param float $landRatio
     * @param array $units
     * @param int $totalDefensiveCasualties
     * @return array
     */
    protected function handleVampiricConversionOnDefense(Dominion $defender, Dominion $attacker, array $units, float $landRatio): void
    {

        $convertedUnits = array_fill(1, 4, 0);

        $conversionMultiplier = $this->ConversionCalculator->getConversionMultiplier($defender, $attacker, $units, null);

        $this->invasionResult['defender']['conversionAnalysis']['conversionMultiplier'] = $conversionMultiplier;

        # Did we have any units with vampiric_conversion?
        $unitWithVampiricConversionPerk = $defender->race->units->filter(static function (Unit $unit) use ($units)
        {
            if (!array_key_exists($unit->slot, $units) or ($units[$unit->slot] === 0))
            {
                return false;
            }

            return $unit->getPerkValue('vampiric_conversion');
        });


        $totalVampiricConvertingUnits = 0;
        foreach ($unitWithVampiricConversionPerk as $unit)
        {
            $totalVampiricConvertingUnits = $defender->{'military_unit'.$unit->slot};
        }

        $this->invasionResult['attacker']['conversionAnalysis']['totalConvertingUnits'] = $totalVampiricConvertingUnits;

        $unit2Range = $defender->race->getUnitPerkValueForUnitSlot(4, 'vampiric_conversion');

        foreach($this->invasionResult['attacker']['unitsLost'] as $slot => $amountKilled)
        {

            $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                return ($unit->slot === $slot);
            })->first();


            $unitRawOp = $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense');

            # Remove units with fixed casualties > 50%.
            if($defender->race->getUnitPerkValueForUnitSlot($slot, "fixed_casualties") >= 50)
            {
                $amount = 0;
            }

            # If less than unit2 range, it's a unit1.
            if($unitRawOp <= $unit2Range[0])
            {
                $slotConvertedTo = 1;
                $unitsPerConversion = 1;
            }
            # If it's in the unit2 range, it's a unit2.
            elseif($unitRawOp > $unit2Range[0] and $unitRawOp <= $unit2Range[1])
            {
                $slotConvertedTo = 2;
                $unitsPerConversion = 2;
            }
            # If greater than unit2 range, it's a unit3.
            elseif($unitRawOp > $unit2Range[1])
            {
                $slotConvertedTo = 3;
                $unitsPerConversion = 3;
            }

            # How many nobles are busy converting this unit?
            $unitsConverted = $amountKilled / ($unitsPerConversion / $conversionMultiplier);

            $unitsConverted /= 3;

            if($this->invasionResult['result']['success'])
            {
                $unitsConverted /= 3;
            }

            $convertedUnits[$slotConvertedTo] += intval(min($amountKilled, $unitsConverted));

        }

        if (!isset($this->invasionResult['defender']['conversion']) && array_sum($convertedUnits) > 0)
        {
            $this->invasionResult['defender']['conversion'] = $convertedUnits;
        }

        $attacker->stat_total_units_converted += array_sum($convertedUnits);

        # Defensive conversions take 6 ticks to appear
        foreach($convertedUnits as $slot => $amount)
        {
            $unitKey = 'military_unit'.$slot;
            $this->queueService->queueResources(
                'training',
                $attacker,
                [$unitKey => $amount],
                6
            );
        }
    }


    /**
     * Handles experience point (research point) generation for attacker.
     *
     * @param Dominion $dominion
     * @param array $units
     */
    protected function handleResearchPoints(Dominion $dominion, Dominion $target, array $units): void
    {

        $researchPointsPerAcre = 0;
        # No RP for in-realm invasions.
        if($dominion->realm->id !== $target->realm->id)
        {
            $researchPointsPerAcre = 25;
        }

        $researchPointsPerAcreMultiplier = 1;

        # Increase RP per acre
        if($dominion->race->getPerkMultiplier('research_points_per_acre'))
        {
            $researchPointsPerAcreMultiplier += $dominion->race->getPerkMultiplier('research_points_per_acre');
        }

        $researchPointsPerAcreMultiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'observatory');

        $isInvasionSuccessful = $this->invasionResult['result']['success'];
        if ($isInvasionSuccessful)
        {
            $landConquered = array_sum($this->invasionResult['attacker']['landConquered']);

            $researchPointsForGeneratedAcres = 1;

            if($this->militaryCalculator->getRecentlyInvadedCountByAttacker($target, $dominion) == 0)
            {
                $researchPointsForGeneratedAcres = 2;
            }


            $researchPointsGained = $landConquered * $researchPointsForGeneratedAcres * $researchPointsPerAcre * $researchPointsPerAcreMultiplier;
            $slowestTroopsReturnHours = $this->getSlowestUnitReturnHours($dominion, $units);

            $this->queueService->queueResources(
                'invasion',
                $dominion,
                ['resource_tech' => $researchPointsGained],
                $slowestTroopsReturnHours
            );

            $this->invasionResult['attacker']['researchPoints'] = $researchPointsGained;
        }
    }

    /**
     *  Handles perks that trigger DURING the battle (before casualties).
     *
     *  Go through every unit slot and look for post-invasion perks:
     *  - burns_peasants_on_attack
     *  - damages_improvements_on_attack
     *  - eats_peasants_on_attack
     *  - eats_draftees_on_attack
     *
     * If a perk is found, see if any of that unit were sent on invasion.
     *
     * If perk is found and units were sent, calculate and take the action.
     *
     * @param Dominion $dominion
     * @param Dominion $target
     * @param array $units
     */
    protected function handleDuringInvasionUnitPerks(Dominion $dominion, Dominion $target, array $units): void
    {
      # Ignore if attacker is overwhelmed.
      if(!$this->invasionResult['result']['overwhelmed'])
      {
        for ($unitSlot = 1; $unitSlot <= 4; $unitSlot++)
        {
          // Firewalker, Artillery, Elementals: burns_peasants
          if ($dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'burns_peasants_on_attack') and isset($units[$unitSlot]))
          {
            $burningUnits = $units[$unitSlot];
            $peasantsBurnedPerUnit = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'burns_peasants_on_attack');

            # If target has less than 1000 peasants, we don't burn any.
            if($target->peasants < 1000)
            {
              $burnedPeasants = 0;
            }
            else
            {
              $burnedPeasants = $burningUnits * $peasantsBurnedPerUnit;
              $burnedPeasants = min(($target->peasants-1000), $burnedPeasants);
            }
            $target->peasants -= $burnedPeasants;
            $this->invasionResult['attacker']['peasants_burned']['peasants'] = $burnedPeasants;
            $this->invasionResult['defender']['peasants_burned']['peasants'] = $burnedPeasants;

          }

          // Artillery: damages_improvements_on_attack
          if ($dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'damages_improvements_on_attack') and isset($units[$unitSlot]))
          {
            $castleToBeDamaged = [];

            $damageReductionFromMasonries = 1 - (($dominion->building_masonry * 0.75) / $this->landCalculator->getTotalLand($dominion));

            $damagingUnits = $units[$unitSlot];
            $damagePerUnit = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'damages_improvements_on_attack');
            $damageDone = $damagingUnits * $damagePerUnit * $damageReductionFromMasonries;


            # Calculate target's total imp points, where imp points > 0.
            foreach ($this->improvementHelper->getImprovementTypes($target) as $type)
            {
              if($target->{'improvement_' . $type} > 0)
              {
                $castleToBeDamaged[$type] = $target->{'improvement_' . $type};
              }
            }
            $castleTotal = array_sum($castleToBeDamaged);

            # Calculate how much of damage should be applied to each type.
            foreach ($castleToBeDamaged as $type => $points)
            {
              # The ratio this improvement type is of the total amount of imp points.
              $typeDamageRatio = $points / $castleTotal;

              # The ratio is applied to the damage done.
              $typeDamageDone = intval($damageDone * $typeDamageRatio);

              # Do the damage.
              $target->{'improvement_' . $type} -= min($target->{'improvement_' . $type}, $typeDamageDone);
            }

            $this->invasionResult['attacker']['improvements_damage']['improvement_points'] = $damageDone;
            $this->invasionResult['defender']['improvements_damage']['improvement_points'] = $damageDone;
          }

          // Troll: eats_peasants_on_attack
          if ($dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'eats_peasants_on_attack') and isset($units[$unitSlot]))
          {
            $eatingUnits = $units[$unitSlot];
            $peasantsEatenPerUnit = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'eats_peasants_on_attack');

            # If target has less than 1000 peasants, we don't eat any.
            if($target->peasants < 1000)
            {
              $eatenPeasants = 0;
            }
            else
            {
              $eatenPeasants = $eatingUnits * $peasantsEatenPerUnit;
              $eatenPeasants = min(($target->peasants-1000), $eatenPeasants);
            }
            $target->peasants -= $eatenPeasants;
            $this->invasionResult['attacker']['peasants_eaten']['peasants'] = $eatenPeasants;
            $this->invasionResult['defender']['peasants_eaten']['peasants'] = $eatenPeasants;
          }

          // Troll: eats_draftees_on_attack
          if ($dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'eats_draftees_on_attack') and isset($units[$unitSlot]))
          {
            $eatingUnits = $units[$unitSlot];
            $drafteesEatenPerUnit = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'eats_draftees_on_attack');

            $eatenDraftees = $eatingUnits * $drafteesEatenPerUnit;
            $eatenDraftees = min(($target->peasants-1000), $eatenDraftees);

            $target->peasants -= $eatenPeasants;
            $this->invasionResult['attacker']['draftees_eaten']['draftees'] = $eatenPeasants;
            $this->invasionResult['defender']['draftees_eaten']['draftees'] = $eatenPeasants;
          }


        }
      }

    }

    /**
     * Handles the surviving units returning home.
     *
     * @param Dominion $dominion
     * @param array $units
     * @param array $convertedUnits
     */
    protected function handleReturningUnits(Dominion $dominion, array $units, array $convertedUnits): void
    {
        $returningUnits = [
          'military_unit1' => 0,
          'military_unit2' => 0,
          'military_unit3' => 0,
          'military_unit4' => 0,
        ];

        for ($i = 1; $i <= 4; $i++)
        {

            $unitKey = "military_unit{$i}";
            $returningUnitKey = $unitKey;
            $returningAmount = 0;

            # See if slot $i has wins_into perk.
            if($this->invasionResult['result']['success'])
            {
                if($dominion->race->getUnitPerkValueForUnitSlot($i, 'wins_into'))
                {
                    $returnsAsSlot = $dominion->race->getUnitPerkValueForUnitSlot($i, 'wins_into');
                    $returningUnitKey = 'military_unit' . $returnsAsSlot;
                }
            }


            if (array_key_exists($i, $units))
            {
                $returningAmount += $units[$i];
                $dominion->$unitKey -= $units[$i];
            }

            if (array_key_exists($i, $convertedUnits))
            {
                $returningAmount += $convertedUnits[$i];
            }

            $returningUnits[$returningUnitKey] += $returningAmount;
        }

        # Look for dies_into amongst the dead attacking units.
        foreach($this->invasionResult['attacker']['unitsLost'] as $slot => $casualties)
        {
          $unitKey = "military_unit{$slot}";
          if($dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into'))
          {
            # Which unit do they die into?
            $newUnitSlot = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into');
            $newUnitKey = "military_unit{$newUnitSlot}";

            $returningUnits[$newUnitKey] += $casualties;
          }
        }

        /*
        $this->queueService->queueResources(
            'invasion',
            $dominion,
            [$unitKey => $returningAmount],
            $this->getUnitReturnHoursForSlot($dominion, $i)
        );
      */
      foreach($returningUnits as $unitKey => $returningAmount)
      {
          $slot = intval(str_replace('military_unit','',$unitKey));

          $this->invasionResult['attacker']['unitsReturning'][$slot] = $returningAmount;

          $this->queueService->queueResources(
              #'returning',
              'invasion',
              $dominion,
              [$unitKey => $returningAmount],
              $this->getUnitReturnHoursForSlot($dominion, $slot)
          );
      }
    }

    /**
     * Handles the surviving units returning home.
     *
     * @param Dominion $dominion
     * @param Dominion $target
     * @param array $units
     */
    protected function handleBoats(Dominion $dominion, Dominion $target, array $units): void
    {
        $unitsTotal = 0;
        $unitsThatSinkBoats = 0;
        $unitsThatNeedsBoatsByReturnHours = [];
        // Calculate boats sent and attacker sinking perk
        foreach ($dominion->race->units as $unit) {
            if (!isset($units[$unit->slot]) || ((int)$units[$unit->slot] === 0)) {
                continue;
            }

            $unitsTotal += (int)$units[$unit->slot];

            if ($unit->getPerkValue('sink_boats_offense') !== 0) {
                $unitsThatSinkBoats += (int)$units[$unit->slot];
            }

            if ($unit->need_boat) {
                $hours = $this->getUnitReturnHoursForSlot($dominion, $unit->slot);

                if (!isset($unitsThatNeedsBoatsByReturnHours[$hours])) {
                    $unitsThatNeedsBoatsByReturnHours[$hours] = 0;
                }

                $unitsThatNeedsBoatsByReturnHours[$hours] += (int)$units[$unit->slot];
            }
        }
        if (!$this->invasionResult['result']['overwhelmed'] && $unitsThatSinkBoats > 0) {
            $defenderBoatsProtected = $this->militaryCalculator->getBoatsProtected($target);
            $defenderBoatsSunkPercentage = (static::BOATS_SUNK_BASE_PERCENTAGE / 100) * ($unitsThatSinkBoats / $unitsTotal);
            $targetQueuedBoats = $this->queueService->getInvasionQueueTotalByResource($target, 'resource_boats');
            $targetBoatTotal = $target->resource_boats + $targetQueuedBoats;
            $defenderBoatsSunk = (int)floor(max(0, $targetBoatTotal - $defenderBoatsProtected) * $defenderBoatsSunkPercentage);
            if ($defenderBoatsSunk > $targetQueuedBoats) {
                $this->queueService->dequeueResource('invasion', $target, 'boats', $targetQueuedBoats);
                $target->resource_boats -= $defenderBoatsSunk - $targetQueuedBoats;
            } else {
                $this->queueService->dequeueResource('invasion', $target, 'boats', $defenderBoatsSunk);
            }
            $this->invasionResult['defender']['boatsLost'] = $defenderBoatsSunk;
        }

        $defendingUnitsTotal = 0;
        $defendingUnitsThatSinkBoats = 0;
        $attackerBoatsLost = 0;
        // Defender sinking perk
        foreach ($target->race->units as $unit) {
            $defendingUnitsTotal += $target->{"military_unit{$unit->slot}"};
            if ($unit->getPerkValue('sink_boats_defense') !== 0) {
                $defendingUnitsThatSinkBoats += $target->{"military_unit{$unit->slot}"};
            }
        }
        if ($defendingUnitsThatSinkBoats > 0) {
            $attackerBoatsSunkPercentage = (static::BOATS_SUNK_BASE_PERCENTAGE / 100) * ($defendingUnitsThatSinkBoats / $defendingUnitsTotal);
        }

        // Queue returning boats
        foreach ($unitsThatNeedsBoatsByReturnHours as $hours => $amountUnits) {
            $boatsByReturnHourGroup = (int)floor($amountUnits / $dominion->race->getBoatCapacity());

            $dominion->resource_boats -= $boatsByReturnHourGroup;

            if ($defendingUnitsThatSinkBoats > 0) {
                $attackerBoatsSunk = (int)ceil($boatsByReturnHourGroup * $attackerBoatsSunkPercentage);
                $attackerBoatsLost += $attackerBoatsSunk;
                $boatsByReturnHourGroup -= $attackerBoatsSunk;
            }

            $this->queueService->queueResources(
                'invasion',
                $dominion,
                ['resource_boats' => $boatsByReturnHourGroup],
                $hours
            );
        }
        if ($attackerBoatsLost > 0) {
            $this->invasionResult['attacker']['boatsLost'] = $attackerBoatsSunk;
        }
    }

    /**
     * Handles spells cast after invasion.
     *
     * @param Dominion $dominion
     * @param Dominion $target (here becomes $defender)
     */
    protected function handleInvasionSpells(Dominion $attacker, Dominion $defender): void
    {

        $isInvasionSpell = True;

        # Attacker spells
        # Spells the attacker casts on the defender during invasion.
        $attackerSpells = $this->spellHelper->getInvasionSpells($attacker, $defender);

        # Defender spells
        # Spells the defender casts on the attacker during invasion.
        $defenderSpells = $this->spellHelper->getInvasionSpells($defender, $attacker);

        foreach($attackerSpells as $attackerSpell)
        {
          # Check each possible spell conditions.
          $spellTypeCheck = False;
          $invasionMustBeSuccessfulCheck = False;
          $opDpRatioCheck = False;

          # 1. Is this spell cast when the attacker is attacking?
          if($attackerSpell['type'] == 'offense')
          {
            $spellTypeCheck = True;
          }

          # 2. Is the spell only cast when the invasion is successful, OR when the invasion is UNsuccessful, OR in any case?
          if(
              ($attackerSpell['invasion_must_be_successful'] == True and $this->invasionResult['result']['success'])
              or ($attackerSpell['invasion_must_be_successful'] == False and !$this->invasionResult['result']['success'])
              or ($attackerSpell['invasion_must_be_successful'] == Null)
              )
          {
            $invasionMustBeSuccessfulCheck = True;
          }

          # 3. Is there an OP/DP ratio requirement?
          $opDpRatio = $this->invasionResult['attacker']['op'] / $this->invasionResult['defender']['dp'];
          if(
              (isset($attackerSpell['op_dp_ratio']) and $opDpRatio >= $attackerSpell['op_dp_ratio'])
              OR $attackerSpell['op_dp_ratio'] == Null)
          {
            $opDpRatioCheck = True;
          }

          # If all checks are True, cast the spell.
          if($spellTypeCheck == True and $invasionMustBeSuccessfulCheck == True and $opDpRatioCheck == True)
          {
            $this->spellActionService->castSpell($attacker, $attackerSpell['key'], $defender, $isInvasionSpell);
          }
        }

        foreach($defenderSpells as $defenderSpell)
        {
          # Check each possible spell conditions.
          $spellTypeCheck = False;
          $invasionMustBeSuccessfulCheck = False;
          $opDpRatioCheck = False;

          # 1. Is this spell cast when the attacker is attacking?
          if($defenderSpell['type'] == 'defense')
          {
            $spellTypeCheck = True;
          }

          # 2. Is the spell only cast when the invasion is successful, OR when the invasion is UNsuccessful, OR in any case?
          if(
              ($defenderSpell['invasion_must_be_successful'] == True and $this->invasionResult['result']['success'])
              or ($defenderSpell['invasion_must_be_successful'] == False and !$this->invasionResult['result']['success'])
              or ($defenderSpell['invasion_must_be_successful'] == Null)
              )
          {
            $invasionMustBeSuccessfulCheck = True;
          }

          # 3. Is there an OP/DP ratio requirement?
          $opDpRatio = $this->invasionResult['attacker']['op'] / $this->invasionResult['defender']['dp'];
          if(
              (isset($defenderSpell['op_dp_ratio']) and $opDpRatio >= $defenderSpell['op_dp_ratio'])
              OR $defenderSpell['op_dp_ratio'] == Null)
          {
            $opDpRatioCheck = True;
          }

          # If all checks are True, cast the spell.
          if($spellTypeCheck == True and $invasionMustBeSuccessfulCheck == True and $opDpRatioCheck == True)
          {
            $this->spellActionService->castSpell($defender, $defenderSpell['key'], $attacker, $isInvasionSpell);
          }

        }

    }

    /**
     * Handles the collection of souls for Demons.
     *
     * @param Dominion $attacker
     * @param Dominion $defender
     */
    protected function handleSoulBloodFoodCollection(Dominion $attacker, Dominion $defender, float $landRatio): void
    {
        $souls = 0;
        $blood = 0;
        $food = 0;

        if($attacker->race->name == 'Demon' or $defender->race->name == 'Demon')
        {
            # Demon attacking non-Demon
            if($attacker->race->name == 'Demon' and $defender->race->name !== 'Demon')
            {
                $unitsKilled = $this->invasionResult['defender']['unitsLost'];
                $dpFromKilledUnits = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, $unitsKilled, 0, false, $this->isAmbush, true);

                $this->invasionResult['attacker']['dpFromKilledUnits'] = $dpFromKilledUnits;

                $blood += $dpFromKilledUnits * 1/3;
                $food += $dpFromKilledUnits * 4;

                $souls += array_sum($this->invasionResult['defender']['unitsLost']);
                $souls *= (1 - $defender->race->getPerkMultiplier('reduced_conversions'));

                $this->invasionResult['attacker']['demonic_collection']['souls'] = $souls;
                $this->invasionResult['attacker']['demonic_collection']['blood'] = $blood;
                $this->invasionResult['attacker']['demonic_collection']['food'] = $food;

                $this->queueService->queueResources(
                    'invasion',
                    $attacker,
                    [
                        'resource_soul' => $souls,
                        'resource_blood' => $blood,
                        'resource_food' => $food,
                    ]
                );
            }
            # Demon defending against non-Demon
            elseif($attacker->race->name !== 'Demon' and $defender->race->name == 'Demon')
            {
                $opFromKilledUnits = $this->militaryCalculator->getOffensivePowerRaw($attacker, $defender, $landRatio, $this->invasionResult['attacker']['unitsLost'], [], false, true);

                foreach($this->invasionResult['attacker']['unitsLost'] as $casualties)
                {
                    $souls += $casualties;
                    $blood += $opFromKilledUnits * 1/3;
                    $food += $casualties * 2;
                }

                $this->invasionResult['defender']['opFromKilledUnits'] = $opFromKilledUnits;

                $souls *= (1 - $attacker->race->getPerkMultiplier('reduced_conversions'));

                $this->invasionResult['defender']['demonic_collection']['souls'] = $souls;
                $this->invasionResult['defender']['demonic_collection']['blood'] = $blood;
                $this->invasionResult['defender']['demonic_collection']['food'] = $food;

                $defender->resource_soul += $souls;
                $defender->resource_blood += $blood;
                $defender->resource_food += $food;
            }
        }
    }

    /**
     * Handles the creation of champions for Norse.
     *
     * @param Dominion $attacker
     * @param Dominion $defender
     */
    protected function handleChampionCreation(Dominion $attacker, Dominion $defender, array $units, float $landRatio, bool $isInvasionSuccessful): void
    {
        $champions = 0;
        if ($attacker->race->name == 'Norse')
        {
            if($landRatio >= 0.75 and $isInvasionSuccessful)
            {
                $champions = $this->invasionResult['attacker']['unitsLost']['1'];

                $this->invasionResult['attacker']['champion']['champions'] = $champions;

                $this->queueService->queueResources(
                    'invasion',
                    $attacker,
                    [
                        'resource_champion' => $champions,
                    ]
                );
            }
        }
    }

    /**
     * Handles the salvaging of lumber, ore, and gem costs of units.
     * Also handles plunders unit perk. Because both use the same queue value.
     *
     * @param Dominion $attacker
     * @param Dominion $defender
     */
    protected function handleSalvagingAndPlundering(Dominion $attacker, Dominion $defender, array $survivingUnits): void
    {

        $result['attacker']['plunder']['platinum'] = 0;
        $result['attacker']['plunder']['mana'] = 0;
        $result['attacker']['plunder']['food'] = 0;
        $result['attacker']['plunder']['ore'] = 0;
        $result['attacker']['plunder']['lumber'] = 0;
        $result['attacker']['plunder']['gems'] = 0;

        $result['attacker']['salvage']['ore'] = 0;
        $result['attacker']['salvage']['lumber'] = 0;
        $result['attacker']['salvage']['gems'] = 0;

        $result['defender']['salvage']['ore'] = 0;
        $result['defender']['salvage']['lumber'] = 0;
        $result['defender']['salvage']['gems'] = 0;

        # Defender: Salvaging
        if($salvaging = $defender->race->getPerkMultiplier('salvaging'))
        {
            $unitCosts = $this->trainingCalculator->getTrainingCostsPerUnit($defender);
            foreach($this->invasionResult['defender']['unitsLost'] as $slot => $amountLost)
            {
                if($slot !== 'draftees')
                {
                    $unitType = 'unit'.$slot;
                    $unitOreCost = $unitCosts[$unitType]['ore'];
                    $unitLumberCost = $unitCosts[$unitType]['lumber'];
                    $unitGemCost = $unitCosts[$unitType]['gem'];

                    $result['defender']['salvage']['ore'] += $amountLost * $unitOreCost * $salvaging;
                    $result['defender']['salvage']['lumber'] += $amountLost * $unitLumberCost * $salvaging;
                    $result['defender']['salvage']['gems'] += $amountLost * $unitGemCost * $salvaging;

                    # Update statistics
                    $defender->stat_total_ore_salvaged += $result['defender']['salvage']['ore'];
                    $defender->stat_total_lumber_salvaged += $result['defender']['salvage']['lumber'];
                    $defender->stat_total_gem_salvaged += $result['defender']['salvage']['gems'];
                }
            }
        }

        # Attacker gets no salvage or plunder if attack fails.
        if(!$this->invasionResult['result']['success'])
        {
            return;
        }

        # Attacker: Salvaging
        if($salvaging = $attacker->race->getPerkMultiplier('salvaging'))
        {
            $unitCosts = $this->trainingCalculator->getTrainingCostsPerUnit($attacker);
            foreach($this->invasionResult['attacker']['unitsLost'] as $slot => $amountLost)
            {
                $unitType = 'unit'.$slot;
                $unitOreCost = $unitCosts[$unitType]['ore'];
                $unitLumberCost = $unitCosts[$unitType]['lumber'];
                $unitGemCost = $unitCosts[$unitType]['gem'];

                $result['attacker']['salvage']['ore'] += $amountLost * $unitOreCost * $salvaging;
                $result['attacker']['salvage']['lumber'] += $amountLost * $unitLumberCost * $salvaging;
                $result['attacker']['salvage']['gems'] += $amountLost * $unitGemCost * $salvaging;

                # Update statistics
                $attacker->stat_total_ore_salvaged += $result['attacker']['salvage']['ore'];
                $attacker->stat_total_lumber_salvaged += $result['attacker']['salvage']['lumber'];
                $attacker->stat_total_gem_salvaged += $result['attacker']['salvage']['gems'];
            }
        }

        # Attacker: Plundering
        foreach($survivingUnits as $slot => $amount)
        {
            if($plunderPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot,'plunders'))
            {
                foreach($plunderPerk as $plunder)
                {
                    $resourceToPlunder = $plunder[0];
                    $amountPlunderedPerUnit = $plunder[1];
                    $amountToPlunder = intval(min($defender->{'resource_'.$resourceToPlunder}, $amount * $amountPlunderedPerUnit));
                    $result['attacker']['plunder'][$resourceToPlunder] += $amountToPlunder;
                    #echo '<pre>You plunder ' . $amountToPlunder . ' ' . $resourceToPlunder. '. The target has ' . $defender->{'resource_'.$resourceToPlunder} . ' ' . $resourceToPlunder. '</pre>';
                }
            }
        }

        # Remove plundered resources from defender.
        foreach($result['attacker']['plunder'] as $resource => $amount)
        {
            $result['attacker']['plunder'][$resource] = min($amount, $defender->{'resource_'.$resource});
            $defender->{'resource_'.$resource} -= $result['attacker']['plunder'][$resource];
        }

        # Add salvaged resources to defender.
        foreach($result['defender']['salvage'] as $resource => $amount)
        {
            $defender->{'resource_'.$resource} += $amount;
        }

        # Queue plundered and salvaged resources to attacker.
        foreach($result['attacker']['plunder'] as $resource => $amount)
        {
            # If the resource is ore, lumber, or gems, also check for salvaged resources.
            if(in_array($resource, ['ore', 'lumber', 'gems']))
            {
                $amount += $result['attacker']['salvage'][$resource];
            }

            $this->queueService->queueResources(
                'invasion',
                $attacker,
                [
                    'resource_'.$resource => $amount
                ]
            );

            # Update statistics
            $attacker->{'stat_total_' . $resource . '_plundered'} += $amount;

        }

        $this->invasionResult['attacker']['salvage'] = $result['attacker']['salvage'];
        $this->invasionResult['attacker']['plunder'] = $result['attacker']['plunder'];
        $this->invasionResult['defender']['salvage'] = $result['defender']['salvage'];
    }



    /**
     * Check for events that take place before the invasion:
     *  Beastfolk Ambush
     *
     * @param Dominion $dominion
     * @return void
     */
    protected function handleBeforeInvasionPerks(Dominion $attacker): void
    {
        # Check for Ambush
        $this->isAmbush = false;
        if ($this->spellCalculator->isSpellActive($attacker, 'ambush'))
        {
            $this->isAmbush = true;
        }


        $this->invasionResult['result']['isAmbush'] = $this->isAmbush;
    }

    /**
     * Check whether the invasion is successful.
     *
     * @param Dominion $dominion
     * @param Dominion $target
     * @param array $units
     * @return void
     */
    protected function checkInvasionSuccess(Dominion $dominion, Dominion $target, array $units): void
    {
        $landRatio = $this->rangeCalculator->getDominionRange($dominion, $target) / 100;
        $attackingForceOP = $this->militaryCalculator->getOffensivePower($dominion, $target, $landRatio, $units);
        $targetDP = $this->getDefensivePowerWithTemples($dominion, $target, $units, $landRatio, $this->isAmbush);
        $this->invasionResult['attacker']['op'] = $attackingForceOP;
        $this->invasionResult['defender']['dp'] = $targetDP;
        $this->invasionResult['result']['success'] = ($attackingForceOP > $targetDP);
    }

    /**
     * Check whether the attackers got overwhelmed by the target's defending army.
     *
     * Overwhelmed attackers have increased casualties, while the defending
     * party has reduced casualties.
     *
     */
    protected function checkOverwhelmed(): void
    {
        // Never overwhelm on successful invasions
        $this->invasionResult['result']['overwhelmed'] = false;

        if ($this->invasionResult['result']['success'])
        {
            return;
        }

        $attackingForceOP = $this->invasionResult['attacker']['op'];
        $targetDP = $this->invasionResult['defender']['dp'];

        $this->invasionResult['result']['overwhelmed'] = ((1 - $attackingForceOP / $targetDP) >= (static::OVERWHELMED_PERCENTAGE / 100));
    }

/*
    protected function passesOpAtLeast50percentOfDpRule(): bool
    {
        if($this->invasionResult['result']['success']) {
            return true;
        }

        return $this->invasionResult['attacker']['op'] / $this->invasionResult['defender']['dp'] > 0.5;
    }
*/

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
            $unitsReturning[$slot] = $this->queueService->getInvasionQueueTotalByResource($dominion, "military_unit{$slot}");
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
    protected function getUnitReturnHoursForSlot(Dominion $dominion, int $slot): int
    {
        $hours = 12;

        /** @var Unit $unit */
        $unit = $dominion->race->units->filter(function ($unit) use ($slot) {
            return ($unit->slot === $slot);
        })->first();

        if ($unit->getPerkValue('faster_return') !== 0)
        {
            $hours -= (int)$unit->getPerkValue('faster_return');
        }

        return $hours;
    }

    /**
     * Gets the amount of hours for the slowest unit from an array of units
     * takes to return home.
     *
     * Primarily used to bring prestige home earlier if you send only 9hr
     * attackers. (Land always takes 12 hrs)
     *
     * @param Dominion $dominion
     * @param array $units
     * @return int
     */
    protected function getSlowestUnitReturnHours(Dominion $dominion, array $units): int
    {
        $hours = 12;

        foreach ($units as $slot => $amount) {
            if ($amount === 0) {
                continue;
            }

            $hoursForUnit = $this->getUnitReturnHoursForSlot($dominion, $slot);

            if ($hoursForUnit < $hours) {
                $hours = $hoursForUnit;
            }
        }

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
                                                            $this->isAmbush,
                                                            false,
                                                            $units # Becomes $invadingUnits
                                                          );
    }

}
