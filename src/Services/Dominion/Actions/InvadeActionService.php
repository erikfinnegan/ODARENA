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
use OpenDominion\Calculators\Dominion\PopulationCalculator;
use OpenDominion\Helpers\RaceHelper;

class InvadeActionService
{
    use DominionGuardsTrait;

    /**
     * @var float Base percentage of boats sunk
     */
    protected const BOATS_SUNK_BASE_PERCENTAGE = 5.0;

    /**
     * @var float Base percentage of defensive casualties
     */
    protected const CASUALTIES_DEFENSIVE_BASE_PERCENTAGE = 4.0;

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

    /**
     * @var float Percentage of mind controlled units that perish
     */
    protected const MINDCONTROLLED_UNITS_CASUALTIES = 10;

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

    /** @var PopulationCalculator */
    protected $populationCalculator;

    /** @var RaceHelpe */
    protected $raceHelper;

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
        ConversionCalculator $conversionCalculator,
        PopulationCalculator $populationCalculator,
        RaceHelper $raceHelper
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
        $this->conversionCalculator = $conversionCalculator;
        $this->populationCalculator = $populationCalculator;
        $this->raceHelper = $raceHelper;
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
/*
            if (!$this->passes33PercentRule($dominion, $target, $units)) {
                throw new GameException('You need to leave at least 1/3 of your total defensive power at home (33% rule).');
            }
*/
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

            // Qur: Statis cannot be invaded.
            if($this->spellCalculator->isSpellActive($target, 'stasis'))
            {
                throw new GameException('A magical stasis surrounds the Qurrian lands, making it impossible for your units to invade.');
            }

            // Qur: Statis cannot invade.
            if($this->spellCalculator->isSpellActive($dominion, 'stasis'))
            {
                throw new GameException('You cannot invade while you are in stasis.');
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
            $this->invasionResult['attacker']['unitsSent'] = $units;
            // Handle pre-invasion
            $this->handleBeforeInvasionPerks($dominion);
            $this->handleMindControl($target, $dominion, $units);

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

            #$survivingUnits = $this->handleOffensiveCasualties($dominion, $target, $units, $landRatio,  $this->invasionResult['defender']['mindControlledUnits']);
            $this->invasionResult['attacker']['survivingUnits'] = $this->handleOffensiveCasualties($dominion, $target, $units, $landRatio,  $this->invasionResult['defender']['mindControlledUnits']);

            $totalDefensiveCasualties = $this->handleDefensiveCasualties($dominion, $target, $units, $landRatio);

            # Conversions
            if($dominion->race->name === 'Vampires')
            {
                $offensiveConversions = $this->handleVampiricConversionOnOffense($dominion, $target, $units, $landRatio);
            }
            elseif($dominion->race->name === 'Weres' or $dominion->race->name === 'Spirit')
            {
                $offensiveConversions = $this->handleStrengthConversionOnOffense($dominion, $target, $units, $landRatio);
            }
            else
            {
                $offensiveConversions = $this->handleOffensiveConversions($dominion, $target, $landRatio, $units, $totalDefensiveCasualties, $target->race->getPerkValue('reduced_conversions'));
            }

            if($target->race->name === 'Vampires')
            {
                $defensiveConversions = $this->handleVampiricConversionOnDefense($target, $dominion, $units, $landRatio);
            }
            elseif($target->race->name === 'Weres' or $target->race->name === 'Spirit')
            {
                $defensiveConversions = $this->handleStrengthConversionOnDefense($dominion, $target, $landRatio);
            }
            else
            {
                $defensiveConversions = $this->handleDefensiveConversions($target, $landRatio, $units, $dominion);
            }

            # Qur
            $this->handleZealots($dominion, $target, $this->invasionResult['attacker']['survivingUnits']);

            # Cult
            $this->handleMenticide($target, $dominion);

            $this->handleReturningUnits($dominion, $this->invasionResult['attacker']['survivingUnits'], $offensiveConversions, $this->invasionResult['defender']['mindControlledUnits']);

            $this->handleMoraleChanges($dominion, $target, $landRatio);
            $this->handleLandGrabs($dominion, $target, $landRatio, $units);
            $this->handleResearchPoints($dominion, $target, $units);

            # Afflicted
            $this->handleInvasionSpells($dominion, $target);

            # Demon
            $this->handleSoulBloodFoodCollection($dominion, $target, $landRatio);

            # Norse
            $this->handleChampionCreation($dominion, $target, $units, $landRatio, $this->invasionResult['result']['success']);

            # Salvage and Plunder
            $this->handleSalvagingAndPlundering($dominion, $target, $this->invasionResult['attacker']['survivingUnits']);

            # Growth
            $this->handleMetabolism($dominion, $target, $landRatio);

            # Imperial Crypt
            $this->handleCrypt($dominion, $target, $this->invasionResult['attacker']['survivingUnits'], $offensiveConversions, $defensiveConversions);

            // Stat changes
            if ($this->invasionResult['result']['success'])
            {
                $dominion->stat_total_land_conquered += (int)array_sum($this->invasionResult['attacker']['landConquered']);
                $dominion->stat_total_land_discovered += (int)array_sum($this->invasionResult['attacker']['landDiscovered']);
                $dominion->stat_attacking_success += $countsAsVictory;
                $dominion->stat_attacking_bottomfeeds += $countsAsBottomfeed;

                $target->stat_total_land_lost += (int)array_sum($this->invasionResult['attacker']['landConquered']);
                $target->stat_defending_failures += 1;
            }
            else
            {
                $dominion->stat_attacking_razes += $countsAsRaze;
                $dominion->stat_attacking_failures += $countsAsFailure;

                $target->stat_defending_success += 1;
                $target->realm->stat_defending_success += 1;
            }

            # Debug before saving:
            if(request()->getHost() === 'odarena.local')
            {
                #dd($this->invasionResult);
            }

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
                number_format(array_sum($this->invasionResult['attacker']['landDiscovered']) + array_sum($this->invasionResult['attacker']['extraLandDiscovered']))
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
            $attackerPrestigeChange += 60 * $landRatio;
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

        // 1/3 gains for hitting Barbarians.
        if($defender->race->name === 'Barbarian')
        {
            $attackerPrestigeChange /= 3;
        }

        $attackerPrestigeChange = round($attackerPrestigeChange);
        $defenderPrestigeChange = round($defenderPrestigeChange);

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
    protected function handleOffensiveCasualties(Dominion $dominion, Dominion $target, array $units, float $landRatio, array $mindControlledUnits): array
    {
        $isInvasionSuccessful = $this->invasionResult['result']['success'];
        $isOverwhelmed = $this->invasionResult['result']['overwhelmed'];
        $attackingForceOP = $this->invasionResult['attacker']['op'];
        $targetDP = $this->invasionResult['defender']['dp'];
        $offensiveCasualtiesPercentage = (static::CASUALTIES_OFFENSIVE_BASE_PERCENTAGE / 100);

        # Merfolk: Charybdis' Gape - increase offensive casualties by 50% if target has this spell on.
        if ($this->spellCalculator->isSpellActive($target, 'maelstrom'))
        {
            $offensiveCasualtiesPercentage *= 1.50;
        }

        # Demon: Infernal Fury - increase offensive casualties by 20% if target has this spell on.
        if ($this->spellCalculator->isSpellActive($target, 'infernal_fury'))
        {
            $offensiveCasualtiesPercentage *= 1.20;
        }

        # Dark Elf: Enchanted Blades - increase offensive casualties by offensive WPA * 0.05.
        if ($this->spellCalculator->isSpellActive($target, 'enchanted_blades'))
        {
            $offensiveCasualtiesPercentage *= (1 + $this->militaryCalculator->getWizardRatio($dominion, 'offense') * 0.05);
        }

        $offensiveUnitsLost = [];

        if(array_sum($mindControlledUnits) > 0)
        {
            foreach($mindControlledUnits as $slot => $amount)
            {
                $units[$slot] -= $amount;
            }
        }

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
        $defensiveCasualtiesPercentage = (static::CASUALTIES_DEFENSIVE_BASE_PERCENTAGE / 100) * min(1, $landRatio);

        // Reduce casualties if target has been hit recently
        $recentlyInvadedCount = $this->invasionResult['defender']['recentlyInvadedCount'];

        $defensiveCasualtiesPercentage *= max(0.1, (1 - ($this->invasionResult['defender']['recentlyInvadedCount']/10)));

        // Scale casualties further with invading OP vs target DP
        $defensiveCasualtiesPercentage *= ($attackingForceOP / $targetDP);

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

        # Dark Elf: Enchanted Blades - increase offensive casualties by offensive WPA * 0.05.
        if ($this->spellCalculator->isSpellActive($dominion, 'enchanted_blades'))
        {
            $casualtiesMultiplier *= (1 + $this->militaryCalculator->getWizardRatio($dominion, 'offense') * 0.05);
        }

        $drafteesLost = (int)floor($target->military_draftees * $defensiveCasualtiesPercentage * ($this->casualtiesCalculator->getDefensiveCasualtiesMultiplierForUnitSlot($target, $dominion, null, $units, $landRatio, $this->isAmbush, $this->invasionResult['result']['success']) * $casualtiesMultiplier));

        // Afflicted: Desecration - Triples draftee casualties (capped by target's number of draftees)
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

            $slotLostMultiplier = $this->casualtiesCalculator->getDefensiveCasualtiesMultiplierForUnitSlot($target, $dominion, $unit->slot, $units, $landRatio, $this->isAmbush, $this->invasionResult['result']['success']);
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
        $diesIntoNewUnits = array_fill(1,4,0);

        foreach($defensiveUnitsLost as $slot => $casualties)
        {
            if($diesIntoPerk = $target->race->getUnitPerkValueForUnitSlot($slot, 'dies_into'))
            {
                $slot = (int)$diesIntoPerk[0];

                $diesIntoNewUnits[$slot] += intval($casualties);
            }

            if($diesIntoMultiplePerk = $target->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple'))
            {
                $slot = (int)$diesIntoMultiplePerk[0];
                $amount = (int)$diesIntoMultiplePerk[1];

                $diesIntoNewUnits[$slot] += intval($casualties * $amount);
            }
        }

        # Defensive conversions take 1 tick to appear
        foreach($diesIntoNewUnits as $slot => $amount)
        {
            $unitKey = 'military_unit'.$slot;
            $this->queueService->queueResources(
                'training',
                $target,
                [$unitKey => $amount],
                1
            );
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
    protected function handleLandGrabs(Dominion $dominion, Dominion $target, float $landRatio, array $units): void
    {
        // Nothing to grab if invasion isn't successful :^)
        if (!$this->invasionResult['result']['success'])
        {
            return;
        }

        $landRatio = $landRatio * 100;
        $this->invasionResult['attacker']['landSize'] = $this->landCalculator->getTotalLand($dominion);
        $this->invasionResult['defender']['landSize'] = $this->landCalculator->getTotalLand($target);

        # Returns an integer.
        $landConquered = $this->militaryCalculator->getLandConquered($dominion, $target, $landRatio);
        $discoverLand = $this->militaryCalculator->checkDiscoverLand($dominion, $target, $landConquered);
        $extraLandDiscovered = $this->militaryCalculator->getExtraLandDiscovered($dominion, $target, $discoverLand, $landConquered);

        $this->invasionResult['attacker']['landConquered'] = [];
        $this->invasionResult['attacker']['landDiscovered'] = [];
        $this->invasionResult['defender']['landLost'] = [];
        $this->invasionResult['defender']['buildingsLost'] = [];
        $this->invasionResult['defender']['totalBuildingsLost'] = 0;

        $landLossRatio = ($landConquered / $this->landCalculator->getTotalLand($target));
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

            // Destroy buildings
            foreach ($buildingsLostForLandType as $buildingType => $buildingsLost)
            {

                $builtBuildingsToDestroy = $buildingsLost['builtBuildingsToDestroy'];

                # What are the buildings made out of?
                $constructionMaterials = $this->raceHelper->getConstructionMaterials($target->race);
                if (isset($constructionMaterials[1]) and $constructionMaterials[1] === 'lumber' and $this->spellCalculator->isSpellActive($dominion, 'furnace_maws'))
                {
                    # Ensure Dragons account for at least 85% of the raw OP sent.
                    if(isset($units[4]))
                    {
                        $rawOp = 0;
                        foreach($units as $slot => $amount)
                        {
                            $unit = $dominion->race->units->filter(function ($unit) use ($slot) {
                                return ($unit->slot == $slot);
                            })->first();

                            $rawOp += $this->militaryCalculator->getUnitPowerWithPerks($target, $dominion, $landRatio, $unit, 'defense') * $amount;
                        }

                        $dragonOpRatio = ($units[4] * 1000 / $rawOp);

                        if($dragonOpRatio > 0.90)
                        {
                            $this->invasionResult['attacker']['furnace_maws'] = true;
                            $builtBuildingsToDestroy = min($builtBuildingsToDestroy * 1.10, $target->{'building_'.$buildingType});
                        }
                    }
                }

                $resourceName = "building_{$buildingType}";
                $target->$resourceName -= $builtBuildingsToDestroy;

                $this->invasionResult['defender']['buildingsLost'][$buildingType] = $buildingsLost;



                $buildingsInQueueToRemove = $buildingsLost['buildingsInQueueToRemove'];

                if ($buildingsInQueueToRemove !== 0)
                {
                    $this->queueService->dequeueResource('construction', $target, $resourceName, $buildingsInQueueToRemove);
                }
            }

            if (!isset($landGainedPerLandType["land_{$landType}"]))
            {
                $landGainedPerLandType["land_{$landType}"] = 0;
            }

            $landGainedPerLandType["land_{$landType}"] += $landLost;

            $this->invasionResult['attacker']['landConquered'][$landType] = $landLost;


            $landDiscovered = 0;
            if($discoverLand)
            {
                $landDiscovered = $landLost;
                if($target->race->name === 'Barbarian')
                {
                    $landDiscovered = (int)round($landLost/3);
                }

                $this->invasionResult['attacker']['landDiscovered'][$landType] = $landDiscovered;

                $landGainedPerLandType["land_{$landType}"] += $landDiscovered;
            }

            $this->invasionResult['defender']['landLost'][$landType] = $landLost;

        }
        $this->invasionResult['attacker']['extraLandDiscovered'][$dominion->race->home_land_type] = $extraLandDiscovered;

        if(isset($landGainedPerLandType['land_'.$dominion->race->home_land_type]))
        {
            $landGainedPerLandType['land_'.$dominion->race->home_land_type] += $extraLandDiscovered;
        }
        else
        {
            $landGainedPerLandType['land_'.$dominion->race->home_land_type] = $extraLandDiscovered;
        }

        $this->landLost = $landConquered;

        $queueData = $landGainedPerLandType;

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

            if($dominion->race->getPerkValue('morale_on_successful_invasion_from_gryphon_nests'))
            {
                $attackerMoraleChange += intval(($dominion->building_gryphon_nest / $this->landCalculator->getTotalLand($dominion)) * 100);
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
        Dominion $target,
        float $landRatio,
        array $units,
        int $totalDefensiveCasualties,
        int $reduceConversions
    ): array {
        $isInvasionSuccessful = $this->invasionResult['result']['success'];
        $convertedUnits = array_fill(1, 4, 0);

        // Racial: Apply reduced_conversions
        $totalDefensiveCasualties = $totalDefensiveCasualties * (1 - ($reduceConversions / 100));

        # Remove specific attributes.
        $exemptibleUnitAttributes = [
            'ammunition',
            'equipment',
            'magical',
            'massive',
            'machine',
            'ship',
          ];

        foreach($this->invasionResult['defender']['unitsLost'] as $slot => $lost)
        {
            if($slot !== 'draftees')
            {
                $isUnitConvertible = true;

                $unit = $target->race->units->filter(function ($unit) use ($slot) {
                        return ($unit->slot == $slot);
                    })->first();

                $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

                # Is it convertible?
                foreach($exemptibleUnitAttributes as $exemptibleUnitAttribute)
                {
                    if(in_array($exemptibleUnitAttribute, $unitAttributes))
                    {
                        $isUnitConvertible = false;
                        break;
                    }
                }

                #var_dump($unit->name . ' is convertible:', $isUnitConvertible);

                if(!$isUnitConvertible or $target->race->getUnitPerkValueForUnitSlot($slot, "fixed_casualties") >= 50)
                {
                    $totalDefensiveCasualties -= $lost;
                }
            }
        }

        # Conversions only for non-overwhelmed invasions with casualties where the attacker is one of these factions
        if
          (
              $this->invasionResult['result']['overwhelmed'] or
              $totalDefensiveCasualties === 0 or
              !in_array($dominion->race->name, ['Undead', 'Sacred Order', 'Afflicted', 'Cult'], true)
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

        # Remove units with fixed casualties greater than 50% or specific attributes.
        $exemptibleUnitAttributes = [
            'ammunition',
            'equipment',
            'magical',
            'massive',
            'machine',
            'ship',
          ];

        foreach($this->invasionResult['attacker']['unitsLost'] as $slot => $lost)
        {
            $isUnitConvertible = true;

            $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot == $slot);
                })->first();

            $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

            # Is it convertible?
            foreach($exemptibleUnitAttributes as $exemptibleUnitAttribute)
            {
                if(in_array($exemptibleUnitAttribute, $unitAttributes))
                {
                    $isUnitConvertible = false;
                    break;
                }
            }

            if(!$isUnitConvertible or $attacker->race->getUnitPerkValueForUnitSlot($slot, "fixed_casualties") >= 50)
            {
                $totalOffensiveCasualties -= $lost;
            }
        }

        // Racial: Apply reduced_conversions
        $totalOffensiveCasualties = $totalOffensiveCasualties * (1 - ($attacker->race->getPerkMultiplier('reduced_conversions')));

        # Conversions only for non-overwhelmed invasions with casualties and where the attacker is one of these factions
        if
          (
              $this->invasionResult['result']['overwhelmed'] or
              $totalOffensiveCasualties === 0 or
              !in_array($dominion->race->name, ['Undead', 'Sacred Order', 'Afflicted', 'Cult'], true)
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

    protected function handleStrengthConversionOnOffense(
        Dominion $attacker,
        Dominion $defender,
        array $units,
        float $landRatio
    ): array {
        $isInvasionSuccessful = $this->invasionResult['result']['success'];
        $convertedUnits = array_fill(1, 4, 0);
        $sentUnitsOpRatio = array_fill(1, 4, 0.0);

        if(!$this->spellCalculator->isSpellActive($defender, 'feral_hunger'))
        {
            # Calculate the proportion each unit type contributes to the overall OP.

            # First, total raw OP
            $rawOp = 0;
            foreach($units as $slot => $amount)
            {
                $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $rawOp += $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense') * $amount;
            }

            # Then calculate contribution (unit raw OP / total raw OP)
            foreach($units as $slot => $amount)
            {
                $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $unitRawOp = $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense');

                $sentUnitsOpRatio[$slot] = ($unitRawOp * $amount) / $rawOp;
             }

            # Determine available casualties
            $unconvertibleAttributes = [
                'ammunition',
                'equipment',
                'magical',
                'massive',
                'machine',
                'ship',
              ];

            $availableCasualties =
                [
                    'draftees' => ['amount' => 0, 'dp' => 0],
                    '1' => ['amount' => 0, 'dp' => 0],
                    '2' => ['amount' => 0, 'dp' => 0],
                    '3' => ['amount' => 0, 'dp' => 0],
                    '4' => ['amount' => 0, 'dp' => 0],
                ];

            foreach($this->invasionResult['defender']['unitsLost'] as $slot => $amount)
            {
                # Apply reduced conversions
                $amount *= (1 - ($defender->race->getPerkMultiplier('reduced_conversions')));

                # Drop to 1/12 if invasion is not successful
                if(!$this->invasionResult['result']['success'])
                {
                    $amount /= 12;
                }

                # Round it down
                $amount = round($amount);

                if($slot === 'draftees')
                {
                    $availableCasualties[$slot]['amount'] = $amount;

                    if($defender->race->getPerkValue('draftee_dp'))
                    {
                        $availableCasualties[$slot]['dp'] = $defender->race->getPerkValue('draftee_dp');
                    }
                    else
                    {
                        $availableCasualties[$slot]['dp'] = 1;
                    }
                }
                else
                {
                    # Get the $unit
                    $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                            return ($unit->slot == $slot);
                        })->first();

                    # Get the unit attributes
                    $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

                    # Only add unit to available casualties if it has none of the unconvertible unit attributes.
                    if(count(array_intersect($unconvertibleAttributes, $unitAttributes)) === 0)
                    {
                        $availableCasualties[$slot]['amount'] = (int)$amount;

                        # Determine the unit's DP.
                        $availableCasualties[$slot]['dp'] = (float)$this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense');
                    }
                }
            }

            # Loop through all available casualties
            foreach($availableCasualties as $casualty)
            {
                #echo "<pre>***\n";
                #echo "[DEFENDER] Unit amount: " . $casualty['amount'] . ' / Unit raw DP: ' .$casualty['dp']. "\n";
                # For each casualty unit, loop through units sent.
                foreach($units as $unitSentSlot => $sentAmount)
                {
                    #echo "[ATTACKER] Unit slot: $unitSentSlot / Amount sent: $sentAmount / Raw OP ratio: {$sentUnitsOpRatio[$unitSentSlot]}\n";

                    $casualtyAmountAvailableToUnit = round($casualty['amount'] * $sentUnitsOpRatio[$unitSentSlot]);
                    #$casualty['amount'] -= min($casualtyAmountAvailableToUnit, $casualty['amount']);

                    #echo "[ATTACKER] Unit slot $unitSentSlot killed $casualtyAmountAvailableToUnit of this unit.\n";

                    if($strengthConversion = $attacker->race->getUnitPerkValueForUnitSlot($unitSentSlot,'strength_conversion'))
                    {
                        $limit = (float)$strengthConversion[0];
                        $under = (int)$strengthConversion[1];
                        $over = (int)$strengthConversion[2];

                        if($casualty['dp'] <= $limit)
                        {
                            $slotConvertedTo = $under;
                            #echo "[DEFENDER] Unit raw DP is less than or equal to the limit ($limit). So it gets converted to $slotConvertedTo.\n";
                        }
                        else
                        {
                            $slotConvertedTo = $over;
                            #echo "[DEFENDER] Unit raw DP is greater the limit ($limit). So it gets converted to $slotConvertedTo.\n";
                        }

                        $convertedUnits[$slotConvertedTo] += (int)$casualtyAmountAvailableToUnit;
                    }
                }
                #echo "***</pre>";
            }

            if (!isset($this->invasionResult['attacker']['conversion']) && array_sum($convertedUnits) > 0)
            {
                $this->invasionResult['attacker']['conversion'] = $convertedUnits;
            }

            $attacker->stat_total_units_converted += array_sum($convertedUnits);
        }

        return $convertedUnits;
    }

    protected function handleStrengthConversionOnDefense(
        Dominion $attacker,
        Dominion $defender,
        float $landRatio
    ): array {
        $isInvasionSuccessful = $this->invasionResult['result']['success'];
        $convertedUnits = array_fill(1, 4, 0);
        $defendingUnitsDpRatio = array_fill(1, 4, 0.0);
        $defendingUnitsTotal = array_fill(1, 4, 0);

        if(!$this->spellCalculator->isSpellActive($defender, 'feral_hunger'))
        {
            foreach($defendingUnitsTotal as $slot => $amount)
            {
                if(isset($this->invasionResult['defender']['unitsDefending'][$slot]))
                {
                    $defendingUnitsTotal[$slot] += $this->invasionResult['defender']['unitsDefending'][$slot];
                }
            }

            # Calculate the proportion each unit type contributes to the overall OP.

            # First, total raw OP
            $rawDp = 0;
            foreach($defendingUnitsTotal as $slot => $amount)
            {
                $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $rawDp += $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'defense') * $amount;
            }

            # Then calculate contribution (unit raw OP / total raw OP)
            foreach($defendingUnitsTotal as $slot => $amount)
            {
                $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $unitRawDp = $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'defense');

                $defendingUnitsDpRatio[$slot] = ($unitRawDp * $amount) / $rawDp;
             }

            # Determine available casualties
            $unconvertibleAttributes = [
                'ammunition',
                'equipment',
                'magical',
                'massive',
                'machine',
                'ship',
              ];

            $availableCasualties =
                [
                    '1' => ['amount' => 0, 'op' => 0],
                    '2' => ['amount' => 0, 'op' => 0],
                    '3' => ['amount' => 0, 'op' => 0],
                    '4' => ['amount' => 0, 'op' => 0],
                ];

            foreach($this->invasionResult['attacker']['unitsLost'] as $slot => $amount)
            {
                # Apply reduced conversions
                $amount *= (1 - ($attacker->race->getPerkMultiplier('reduced_conversions')));

                # Drop to 1/3 if invasion is successful
                if($this->invasionResult['result']['success'])
                {
                    $amount /= 3;
                }

                # Round it
                $amount = round($amount);

                # Get the $unit
                $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                        return ($unit->slot == $slot);
                    })->first();

                # Get the unit attributes
                $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

                # Only add unit to available casualties if it has none of the unconvertible unit attributes.
                if(count(array_intersect($unconvertibleAttributes, $unitAttributes)) === 0)
                {
                    $availableCasualties[$slot]['amount'] = (int)$amount;

                    # Determine the unit's DP.
                    $availableCasualties[$slot]['op'] = (float)$this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense');
                }
            }

            # Loop through all available casualties
            foreach($availableCasualties as $casualty)
            {
                #echo "<pre>***\n";
                #echo "[ATTACKER] Unit amount: " . $casualty['amount'] . ' / Unit raw OP: ' .$casualty['op']. "\n";
                # For each casualty unit, loop through units sent.
                foreach($defendingUnitsTotal as $unitDefendingSlot => $defendingAmount)
                {
                    #echo "[DEFENDER] Unit slot: $unitDefendingSlot / Amount defending: $defendingAmount / Raw OP ratio: {$defendingUnitsDpRatio[$unitDefendingSlot]}\n";

                    $casualtyAmountAvailableToUnit = round($casualty['amount'] * $defendingUnitsDpRatio[$unitDefendingSlot]);

                    #echo "[DEFENDER] Unit slot $unitDefendingSlot killed $casualtyAmountAvailableToUnit of this unit.\n";

                    if($strengthConversion = $defender->race->getUnitPerkValueForUnitSlot($unitDefendingSlot, 'strength_conversion'))
                    {
                        $limit = (float)$strengthConversion[0];
                        $under = (int)$strengthConversion[1];
                        $over = (int)$strengthConversion[2];

                        if($casualty['op'] <= $limit)
                        {
                            $slotConvertedTo = $under;
                            #echo "[ATTACKER] Unit raw OP is less than or equal to the limit ($limit). So it gets converted to $slotConvertedTo.\n";
                        }
                        else
                        {
                            $slotConvertedTo = $over;
                            #echo "[ATTACKER] Unit raw OP is greater the limit ($limit). So it gets converted to $slotConvertedTo.\n";
                        }

                        #echo "[DEFENDER] New $slotConvertedTo units: $casualtyAmountAvailableToUnit.\n";

                        $convertedUnits[$slotConvertedTo] += (int)$casualtyAmountAvailableToUnit;

                    }
                }
                #echo "***</pre>";
            }

            if (!isset($this->invasionResult['defender']['conversion']) && array_sum($convertedUnits) > 0)
            {
                $this->invasionResult['defender']['conversion'] = $convertedUnits;
            }

            $defender->stat_total_units_converted += array_sum($convertedUnits);

            # Defensive conversions take 6 ticks to appear
            foreach($convertedUnits as $slot => $amount)
            {
                $unitKey = 'military_unit'.$slot;
                $this->queueService->queueResources(
                    'training',
                    $defender,
                    [$unitKey => $amount],
                    6
                );
            }
        }

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
    protected function handleVampiricConversionOnOffense(Dominion $attacker, Dominion $defender, array $units, float $landRatio): array
    {

        $convertedUnits = array_fill(1, 4, 0);

        if($this->invasionResult['result']['overwhelmed'] or $attacker->race->name !== 'Vampires')
        {
            return $convertedUnits;
        }

        $conversionMultiplier = $this->conversionCalculator->getConversionMultiplier($attacker, $defender, $units, null);

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

        $unitsWithVampiricConversionPerk = $attacker->race->units->filter(static function (Unit $unit) use (
            $landRatio,
            $units,
            $attacker
        ) {
            if (!array_key_exists($unit->slot, $units) || ($units[$unit->slot] === 0)) {
                return false;
            }

            return $unit->getPerkValue('vampiric_conversion');
        });

        $totalVampiricConvertingUnits = 0;
        foreach ($unitsWithVampiricConversionPerk as $unit)
        {
            $totalVampiricConvertingUnits += $units[$unit->slot];
        }

        $this->invasionResult['attacker']['conversionAnalysis']['totalConvertingUnits'] = $totalVampiricConvertingUnits;

        # This requires that the thresholds are the same for all units.
        $unit2Range = $attacker->race->getUnitPerkValueForUnitSlot(4, 'vampiric_conversion');

        # Remove specific attributes.
        $exemptibleUnitAttributes = [
            'ammunition',
            'equipment',
            'magical',
            'machine',
            'ship',
          ];

        foreach($this->invasionResult['defender']['unitsLost'] as $slot => $amountKilled)
        {
            $isUnitConvertible = true;

            if($slot == 'draftees')
            {
                $unitRawDp = 1;
            }
            else
            {
                $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

                $unitRawDp = $this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense');

                # Is it convertible?
                foreach($exemptibleUnitAttributes as $exemptibleUnitAttribute)
                {
                    if(in_array($exemptibleUnitAttribute, $unitAttributes))
                    {
                        $isUnitConvertible = false;
                        break;
                    }
                }
            }

            if($isUnitConvertible)
            {
                # If less than unit2 range, it's a unit1.
                if($unitRawDp <= $unit2Range[0])
                {
                    $slotConvertedTo = 1;
                    $unitsPerConversion = 1;
                }
                # If it's in the unit2 range, it's a unit2.
                elseif($unitRawDp > $unit2Range[0] and $unitRawDp < $unit2Range[1])
                {
                    $slotConvertedTo = 2;
                    $unitsPerConversion = 1; # From 2, R29
                }
                # If greater than unit2 range, it's a unit3.
                elseif($unitRawDp >= $unit2Range[1])
                {
                    $slotConvertedTo = 3;
                    $unitsPerConversion = 1; # From 3, R29
                }

                $unitsConverted = min($totalVampiricConvertingUnits, $amountKilled);
                $totalVampiricConvertingUnits -= $unitsConverted;

                if(!$this->invasionResult['result']['success'])
                {
                    $unitsConverted /= 12;
                }

                $convertedUnits[$slotConvertedTo] += intval(min($amountKilled, $unitsConverted));
            }

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

        $conversionMultiplier = $this->conversionCalculator->getConversionMultiplier($defender, $attacker, $units, null);

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

        $unitsWithVampiricConversionPerk = $defender->race->units->filter(static function (Unit $unit) use (
            $landRatio,
            $defender
        ) {
            if (($defender->{'military_unit'.$unit->slot} === 0))
            {
                return false;
            }

            return $unit->getPerkValue('conversion');
        });

        $totalVampiricConvertingUnits = 0;
        foreach ($unitsWithVampiricConversionPerk as $unit)
        {
            $totalVampiricConvertingUnits = $defender->{'military_unit'.$unit->slot};
        }

        $this->invasionResult['attacker']['conversionAnalysis']['totalConvertingUnits'] = $totalVampiricConvertingUnits;

        # This requires that the thresholds are the same for all units.
        $unit2Range = $defender->race->getUnitPerkValueForUnitSlot(4, 'vampiric_conversion');

        # Remove specific attributes.
        $exemptibleUnitAttributes = [
            'ammunition',
            'equipment',
            'magical',
            'machine',
            'ship',
          ];

        foreach($this->invasionResult['attacker']['unitsLost'] as $slot => $amountKilled)
        {

            $isUnitConvertible = true;

            $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                return ($unit->slot === $slot);
            })->first();

            $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

            $unitRawOp = $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense');

            # Is it convertible?
            foreach($exemptibleUnitAttributes as $exemptibleUnitAttribute)
            {
                if(in_array($exemptibleUnitAttribute, $unitAttributes))
                {
                    $isUnitConvertible = false;
                    break;
                }
            }

            if($isUnitConvertible)
            {
                # If less than unit2 range, it's a unit1.
                if($unitRawOp <= $unit2Range[0])
                {
                    $slotConvertedTo = 1;
                    $unitsPerConversion = 1;
                }
                # If it's in the unit2 range, it's a unit2.
                elseif($unitRawOp > $unit2Range[0] and $unitRawOp < $unit2Range[1])
                {
                    $slotConvertedTo = 2;
                    $unitsPerConversion = 1;  # From 2, R29
                }
                # If greater than unit2 range, it's a unit3.
                elseif($unitRawOp >= $unit2Range[1])
                {
                    $slotConvertedTo = 3;
                    $unitsPerConversion = 1; # From 3, R29
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
                $defender,
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


            $researchPointsGained = round($landConquered * $researchPointsForGeneratedAcres * $researchPointsPerAcre * $researchPointsPerAcreMultiplier);
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

    protected function handleMenticide(Dominion $cult, Dominion $attacker)
    {
        if(array_sum($this->invasionResult['defender']['mindControlledUnits']) > 0 and ($this->spellCalculator->isSpellActive($cult, 'menticide')))
        {
            $this->invasionResult['defender']['isMenticide'] = true;
        }
        else
        {
            return;
        }

        $this->invasionResult['defender']['mindControlledUnitsReleased'] = array_fill(1, 4, 0);
        $newThralls = 0;

        foreach($this->invasionResult['defender']['mindControlledUnits'] as $slot => $amount)
        {
            $newThralls += $amount * (1 - (static::MINDCONTROLLED_UNITS_CASUALTIES / 100));
        }

        $this->invasionResult['defender']['menticide']['newThralls'] = $newThralls;
        $cult->military_unit1 += $newThralls;
    }

    /**
     * Handles the surviving units returning home.
     *
     * @param Dominion $dominion
     * @param array $units
     * @param array $convertedUnits
     */
    protected function handleReturningUnits(Dominion $dominion, array $units, array $convertedUnits, array $mindControlledUnits): void
    {
        $returningUnits = [
          'military_unit1' => 0,
          'military_unit2' => 0,
          'military_unit3' => 0,
          'military_unit4' => 0,
        ];

        #echo '<pre>';var_dump($convertedUnits);echo '</pre>';

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

            if(isset($mindControlledUnits[$i]) and $mindControlledUnits[$i] > 0)
            {
                # Release non-menticided mind controlled units, less 10%.
                $returningAmount += $this->invasionResult['defender']['mindControlledUnitsReleased'][$i];
            }

            $returningUnits[$returningUnitKey] += $returningAmount;
        }

        # Look for dies_into amongst the dead attacking units.
        foreach($this->invasionResult['attacker']['unitsLost'] as $slot => $casualties)
        {
            $unitKey = "military_unit{$slot}";

            if($diesIntoPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into'))
            {
                # Which unit do they die into?
                $newUnitSlot = $diesIntoPerk[0];
                $newUnitKey = "military_unit{$newUnitSlot}";

                $returningUnits[$newUnitKey] += $casualties;
            }

            if($diesIntoMultiplePerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple'))
            {
                # Which unit do they die into?
                $newUnitSlot = $diesIntoMultiplePerk[0];
                $newUnitAmount = $diesIntoMultiplePerk[1];

                $newUnitKey = "military_unit{$newUnitSlot}";

                $returningUnits[$newUnitKey] += floor($casualties * $newUnitAmount);
            }

        }

      #echo '<pre>';print_r($returningUnits);echo '</pre>';

      foreach($returningUnits as $unitKey => $returningAmount)
      {

          if($returningAmount > 0)
          {
              $slot = (int)str_replace('military_unit','',$unitKey);

              $returnTicks = $this->getUnitReturnHoursForSlot($dominion, $slot);

              if($this->spellCalculator->isSpellActive($dominion, 'winds_of_fortune'))
              {
                  $returnTicks -= 2;
              }

              # Check for faster_return_if_paired
              if($dominion->race->getUnitPerkValueForUnitSlot($slot, 'faster_return_if_paired'))
              {
                  $fasterReturnIfPairedPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'faster_return_if_paired');
                  $pairedUnitSlot = (int)$fasterReturnIfPairedPerk[0];
                  $pairedUnitKey = 'military_unit'.$pairedUnitSlot;
                  $ticksFaster = (int)$fasterReturnIfPairedPerk[1];

                  # How many of $slot should return faster?
                  $unitsWithFasterReturnTime = min($returningUnits[$pairedUnitKey], $returningAmount);
                  $unitsWithRegularReturnTime = $returningAmount - $unitsWithFasterReturnTime;

                  # Queue faster units
                  $this->queueService->queueResources(
                      'invasion',
                      $dominion,
                      [$unitKey => $unitsWithFasterReturnTime],
                      max(1, $returnTicks - $ticksFaster)
                  );

                  # Queue slower units
                  $this->queueService->queueResources(
                      'invasion',
                      $dominion,
                      [$unitKey => $unitsWithRegularReturnTime],
                      $returnTicks
                  );


              }
              else
              {
                  $this->queueService->queueResources(
                      'invasion',
                      $dominion,
                      [$unitKey => $returningAmount],
                      $returnTicks
                  );
              }

              $this->invasionResult['attacker']['unitsReturning'][$slot] = $returningAmount;
          }

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
                $opFromKilledUnits = $this->militaryCalculator->getOffensivePowerRaw($attacker, $defender, $landRatio, $this->invasionResult['attacker']['unitsLost'], [], [], false, true);

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
            if($landRatio >= 0.75 and $isInvasionSuccessful and isset($this->invasionResult['attacker']['unitsLost']['1']) and $this->invasionResult['attacker']['unitsLost']['1'] > 0)
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

            if($plunderPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot,'plunder'))
            {
                $resourceToPlunder = $plunderPerk[0];
                $amountPlunderedPerUnit = $plunderPerk[1];
                $amountToPlunder = intval(min($defender->{'resource_'.$resourceToPlunder}, $amount * $amountPlunderedPerUnit));
                $result['attacker']['plunder'][$resourceToPlunder] += $amountToPlunder;
                #echo '<pre>You plunder ' . $amountToPlunder . ' ' . $resourceToPlunder. '. The target has ' . $defender->{'resource_'.$resourceToPlunder} . ' ' . $resourceToPlunder. '</pre>';
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

    # Add casualties to the Imperial Crypto.
    protected function handleCrypt(Dominion $attacker, Dominion $defender, array $offensiveConversions, array $defensiveConversions): void
    {

        if($attacker->race->alignment === 'evil' or $defender->race->alignment === 'evil')
        {

            # Units with these attributes do not go in the Crypt.
            $exemptibleUnitAttributes = [
                'ammunition',
                'equipment',
                'magical',
                'machine',
                'ship',
                'massive',
              ];

            # The battlefield:
            # Cap bodies by reduced_conversions perk, and round.
            $defensiveBodies = round(array_sum($this->invasionResult['defender']['unitsLost']) * (1 - $defender->race->getPerkMultiplier('reduced_conversions')));
            $offensiveBodies = round(array_sum($this->invasionResult['attacker']['unitsLost']) * (1 - $attacker->race->getPerkMultiplier('reduced_conversions')));

            # Loop through defensive casualties and remove units that don't qualify.
            foreach($this->invasionResult['defender']['unitsLost'] as $slot => $lost)
            {
                if($slot !== 'draftees')
                {
                    $isUnitConvertible = true;

                    $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                            return ($unit->slot == $slot);
                        })->first();

                    $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

                    # Is it convertible?
                    foreach($exemptibleUnitAttributes as $exemptibleUnitAttribute)
                    {
                        if(in_array($exemptibleUnitAttribute, $unitAttributes))
                        {
                            $isUnitConvertible = false;
                            break;
                        }
                    }

                    if(!$isUnitConvertible or $defender->race->getUnitPerkValueForUnitSlot($slot, "fixed_casualties") >= 50)
                    {
                        $defensiveBodies -= $lost;
                    }
                }
            }

            # Loop through offensive casualties and remove units that don't qualify.
            foreach($this->invasionResult['attacker']['unitsLost'] as $slot => $lost)
            {
                if($slot !== 'draftees')
                {
                    $isUnitConvertible = true;

                    $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                            return ($unit->slot == $slot);
                        })->first();

                    $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

                    # Is it convertible?
                    foreach($exemptibleUnitAttributes as $exemptibleUnitAttribute)
                    {
                        if(in_array($exemptibleUnitAttribute, $unitAttributes))
                        {
                            $isUnitConvertible = false;
                            break;
                        }
                    }

                    if(!$isUnitConvertible or $attacker->race->getUnitPerkValueForUnitSlot($slot, "fixed_casualties") >= 50)
                    {
                        $offensiveBodies -= $lost;
                    }
                }
            }

            # Remove defensive conversions (defender's conversions) from offensive bodies (they are spent)
            if(isset($this->invasionResult['defender']['conversion']))
            {
                $offensiveBodies -= array_sum($this->invasionResult['defender']['conversion']);
            }

            # Remove offensive conversions (attacker's conversions) from defensive bodies (they are spent)
            if(isset($this->invasionResult['attacker']['conversion']))
            {
                $defensiveBodies -= array_sum($this->invasionResult['attacker']['conversion']);
            }

            $toTheCrypt = 0;

            # If defender is empire
            if($defender->race->alignment === 'evil')
            {
                  $whoHasCrypt = 'defender';
                  # If the attack is successful
                  if($this->invasionResult['result']['success'])
                  {
                      # 50% of defensive and 0% of defensive bodies go to the crypt.
                      $defensiveBodies /= 2;
                      $offensiveBodies *= 0;
                  }
                  # If the attack is unsuccessful
                  else
                  {
                      # 100% of defensive and 100% of offensive bodies go to the crypt.
                      $defensiveBodies += 0;
                      $offensiveBodies += 0;
                  }
            }
            # If attacker is empire
            if($attacker->race->alignment === 'evil')
            {
                  $whoHasCrypt = 'attacker';
                  # If the attack is successful
                  if($this->invasionResult['result']['success'])
                  {
                      # 50% of defensive and 100% of defensive bodies go to the crypt.
                      $defensiveBodies /= 2;
                      $offensiveBodies *= 1;
                  }
                  # If the attack is unsuccessful
                  else
                  {
                      # 0% of defensive and 0% of offensive bodies go to the crypt.
                      $defensiveBodies *= 0;
                      $offensiveBodies *= 0;
                  }
            }

            $toTheCrypt = max(0, round($defensiveBodies + $offensiveBodies));

            $this->invasionResult['defender']['crypt']['defensiveBodies'] = $defensiveBodies;
            $this->invasionResult['defender']['crypt']['offensiveBodies'] = $offensiveBodies;
            $this->invasionResult['defender']['crypt']['total'] = $toTheCrypt;

            if($whoHasCrypt == 'defender')
            {
                $defender->realm->fill([
                    'crypt' => ($defender->realm->crypt + $toTheCrypt),
                ])->save();
            }
            elseif($whoHasCrypt == 'attacker')
            {
                $attacker->realm->fill([
                    'crypt' => ($attacker->realm->crypt + $toTheCrypt),
                ])->save();
            }

        }

    }

    /**
     * Handles eating of units by Growth when Metabolism is active.
     *
     * @param Dominion $attacker
     * @param Dominion $defender
     */
    protected function handleMetabolism(Dominion $attacker, Dominion $defender, float $landRatio): void
    {
        $food = 0;

        $uneatableUnitAttributes = [
          'ammunition',
          'equipment',
          'magical',
          'machine',
        ];

        # Eat defensive casualties
        if ($this->spellCalculator->isSpellActive($attacker, 'metabolism'))
        {
                $dpFromEatenUnits = 0;
                $unitsEaten = array_fill(1, 4, 0);

                foreach($this->invasionResult['defender']['unitsLost'] as $slot => $amount)
                {
                    if($slot === 'draftees')
                    {
                        $unitsEaten[$slot] = $amount;
                    }
                    else
                    {
                        # Get the $unit
                        $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                                return ($unit->slot == $slot);
                            })->first();

                        # Get the unit attributes
                        $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

                        if(count(array_intersect($uneatableUnitAttributes, $unitAttributes)) === 0)
                        {
                            $unitsEaten[$slot] = $amount;
                        }
                    }
                }

                $dpFromKilledUnits = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, $unitsEaten, 0, false, $this->isAmbush, true);

                $food += $dpFromEatenUnits * 4 * 2;

                $this->invasionResult['attacker']['metabolism']['unitsEaten'] = $unitsEaten;
                $this->invasionResult['attacker']['metabolism']['dpFromUnitsEaten'] = $dpFromEatenUnits;
                $this->invasionResult['attacker']['metabolism']['food'] = $food;

                $this->queueService->queueResources(
                    'invasion',
                    $attacker,
                    [
                        'resource_food' => $food,
                    ]
                );
          }
          # Eat offensive casualties
          elseif ($this->spellCalculator->isSpellActive($defender, 'metabolism'))
          {
              $unitsKilled = array_fill(1, 4, 0);

              foreach($this->invasionResult['attacker']['unitsLost'] as $slot => $amount)
              {
                  # Get the $unit
                  $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                          return ($unit->slot == $slot);
                      })->first();

                  # Get the unit attributes
                  $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

                  if(count(array_intersect($uneatableUnitAttributes, $unitAttributes)) === 0)
                  {
                      $unitsEaten[$slot] = $amount;
                  }
              }

              $opFromKilledEaten = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, $unitsEaten, 0, false, $this->isAmbush, true);

              $food += $opFromKilledEaten * 4;

              $this->invasionResult['attacker']['metabolism']['unitsEaten'] = $unitsEaten;
              $this->invasionResult['attacker']['metabolism']['opFromUnitsEaten'] = $opFromKilledEaten;
              $this->invasionResult['attacker']['metabolism']['food'] = $food;

              $defender->resource_food += $food;
          }
    }


    /**
     * Handles eating of units by Growth when Metabolism is active.
     *
     * @param Dominion $attacker
     * @param Dominion $defender
     */
    protected function handleZealots(Dominion $attacker, Dominion $defender, array $survivingUnits): void
    {
        $immortalDefenders = array_fill(1, 4, 0);
        $immortalDefendersDeaths = array_fill(1, 4, 0);


        $immortalAttackers = array_fill(1, 4, 0);
        $immortalAttackersDeaths = array_fill(1, 4, 0);

        $zealots = 0;
        $immortalsKilledPerZealot = 2;
        $soulsDestroyedPerZealot = 2;

        if($attacker->race->name === 'Qur' and !$this->invasionResult['result']['overwhelmed'])
        {
            # See if target has any immortal units
            foreach($this->invasionResult['defender']['unitsDefending'] as $slot => $amount)
            {
                if($slot !== 'draftees')
                {
                    $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                        return ($unit->slot == $slot);
                    })->first();

                    if($unit->power_defense !== 0 and ($defender->race->getUnitPerkValueForUnitSlot($slot, 'immortal') or $defender->race->getUnitPerkValueForUnitSlot($slot, 'true_immortal')))
                    {
                        $immortalDefenders[$slot] += $defender->{'military_unit'.$slot};
                    }
                }
            }

            # See if qur sent any Zealots
            foreach($this->invasionResult['attacker']['unitsSent'] as $slot => $amount)
            {
                if($attacker->race->getUnitPerkValueForUnitSlot($slot, 'kills_immortal'))
                {
                    $zealots += $amount;
                }
            }

            $immortalsKilled = min($zealots * $immortalsKilledPerZealot, array_sum($immortalDefenders) * 0.04);

            # Determine ratio of each immortal defender to kill.
            if(array_sum($immortalDefenders) > 0)
            {
                foreach($immortalDefenders as $slot => $amount)
                {
                    $immortalDefendersDeaths[$slot] = floor($immortalsKilled * ($amount / array_sum($immortalDefenders)));
                }

                foreach($immortalDefendersDeaths as $slot => $deaths)
                {
                    if($deaths > 0)
                    {
                          $deaths = intval($deaths);
                          $defender->{"military_unit{$slot}"} -= $deaths;
                          if(isset($this->invasionResult['defender']['unitsLost'][$slot]))
                          {
                              $this->invasionResult['defender']['unitsLost'][$slot] += $deaths;
                          }
                          else
                          {
                              $this->invasionResult['defender']['unitsLost'][$slot] = $deaths;
                          }

                          $defender->{'stat_total_unit' . $slot . '_lost'} += $deaths;
                          $attacker->{'stat_total_units_killed'} += $deaths;
                    }
                }
            }

            // SOULS

            # See if Qur sent any Zealots
            foreach($this->invasionResult['attacker']['unitsSent'] as $slot => $amount)
            {
                if($attacker->race->getUnitPerkValueForUnitSlot($slot, 'destroys_souls'))
                {
                    $zealots += $amount;
                }
            }

            # See if the target has souls.
            if($defender->resource_soul > 0)
            {
                $soulsDestroyed = (int)floor(min($defender->resource_soul * 0.04, ($zealots * $soulsDestroyedPerZealot)));

                $this->invasionResult['attacker']['soulsDestroyed'] = $soulsDestroyed;
                $defender->resource_soul -= $soulsDestroyed;
                $defender->{'stat_total_soul_destroyed'} += $soulsDestroyed;
            }
        }
        elseif($defender->race->name === 'Qur' and !$this->invasionResult['result']['overwhelmed'])
        {

              # See if attacker has any immortal units
              foreach($this->invasionResult['attacker']['unitsSent'] as $slot => $amount)
              {
                  $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                      return ($unit->slot == $slot);
                  })->first();

                  if($attacker->race->getUnitPerkValueForUnitSlot($slot, 'immortal') or $attacker->race->getUnitPerkValueForUnitSlot($slot, 'true_immortal'))
                  {
                      $immortalAttackers[$slot] += $defender->{'military_unit'.$slot};
                  }
              }

              # See if Qur has any Zealots
              foreach($this->invasionResult['defender']['unitsDefending'] as $slot => $amount)
              {
                  if($slot !== 'draftees' and $defender->race->getUnitPerkValueForUnitSlot($slot, 'kills_immortal'))
                  {
                      $zealots += $amount;
                  }
              }

              $immortalsKilled = min($zealots * $immortalsKilledPerZealot, array_sum($immortalAttackers) * 0.04);

              # Determine ratio of each immortal defender to kill.
              if(array_sum($immortalAttackers) > 0)
              {
                  foreach($immortalAttackers as $slot => $amount)
                  {
                      $immortalAttackersDeaths[$slot] = floor($immortalsKilled * ($amount / array_sum($immortalAttackers)));
                  }

                  foreach($immortalAttackersDeaths as $slot => $deaths)
                  {
                      if($deaths > 0)
                      {
                            $deaths = intval($deaths);
                            $defender->{"military_unit{$slot}"} -= $deaths;
                            if(isset($this->invasionResult['attacker']['unitsLost'][$slot]))
                            {
                                $this->invasionResult['attacker']['unitsLost'][$slot] += $deaths;
                            }
                            else
                            {
                                $this->invasionResult['attacker']['unitsLost'][$slot] = $deaths;
                            }

                            $this->invasionResult['attacker']['survivingUnits'][$slot] -= $deaths;

                            $attacker->{'stat_total_unit' . $slot . '_lost'} += $deaths;
                            $defender->{'stat_total_units_killed'} += $deaths;
                      }
                  }
              }

              // SOULS
              $zealots = 0;

              # See if Qur is defending with any Zealots
              foreach($this->invasionResult['defender']['unitsDefending'] as $slot => $amount)
              {
                  if($slot !== 'draftees' and $defender->race->getUnitPerkValueForUnitSlot($slot, 'destroys_souls'))
                  {
                      $zealots += $amount;
                  }
              }

              # See if the target has souls.
              if($attacker->resource_soul > 0)
              {
                  $soulsDestroyed = (int)floor(min($attacker->resource_soul * 0.08, ($zealots * $soulsDestroyedPerZealot)));

                  $this->invasionResult['defender']['soulsDestroyed'] = $soulsDestroyed;
                  $attacker->resource_soul -= $soulsDestroyed;
                  $attacker->{'stat_total_soul_destroyed'} += $soulsDestroyed;
              }
        }
    }

    /**
     * Check for events that take place before the invasion:
     *  Beastfolk Ambush
     *
     * @param Dominion $attacker
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
     * Check for Cultist Mind Control spell:
     *
     * @param Dominion $cult
     * @param Dominion $attacker
     * @param array $units
     * @return void
     */
    protected function handleMindControl(Dominion $cult, Dominion $attacker, array $units): void
    {
        if ($this->spellCalculator->isSpellActive($cult, 'mind_control'))
        {
            $this->invasionResult['defender']['isMindControl'] = true;
        }
        else
        {
            $this->invasionResult['defender']['isMindControl'] = false;
            $this->invasionResult['defender']['mindControlledUnits'] = [];
            return;
        }

        # How many Mystics do we have?
        $availableMystics = $cult->military_unit4;

        # Check invading forces for units that are only SENTIENT
        $mindControlledUnits = [];
        $nonControllableAttributes = [
            'ammunition',
            'equipment',
            'magical',
            'massive',
            'machine',
            'mindless',
            'ship',
            'wise',
          ];
        foreach($units as $slot => $amount)
        {
            $mindControlledUnits[$slot] = 0;

            # Get the $unit
            $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot == $slot);
                })->first();

            # Get the attributes
            $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

            $isUnitControllable = true;
            if(in_array('sentient', $unitAttributes))
            {
                foreach($nonControllableAttributes as $nonControllableAttribute)
                {
                    if(in_array($nonControllableAttribute, $unitAttributes))
                    {
                        $isUnitControllable = false;
                        break;
                    }
                }
            }

            if($isUnitControllable)
            {
                $mindControlledUnits[$slot] = min($amount, $availableMystics);
            }

            $availableMystics -= $mindControlledUnits[$slot];
            $this->invasionResult['defender']['mindControlledUnits'][$slot] = $mindControlledUnits[$slot];
            $this->invasionResult['defender']['mindControlledUnitsReleased'][$slot] = $mindControlledUnits[$slot] * (1 - (static::MINDCONTROLLED_UNITS_CASUALTIES / 100));
        }

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
        $attackingForceOP = $this->militaryCalculator->getOffensivePower($dominion, $target, $landRatio, $units, [], $this->invasionResult['defender']['mindControlledUnits']);
        $targetDP = $this->getDefensivePowerWithTemples($dominion, $target, $units, $landRatio, $this->isAmbush, $this->invasionResult['defender']['mindControlledUnits']);
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
      bool $isAmbush,
      array $mindControlledUnits
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

        return $this->militaryCalculator->getDefensivePower(
                                                            $target,
                                                            $attacker,
                                                            $landRatio,
                                                            null,
                                                            $dpMultiplierReduction,
                                                            $ignoreDraftees,
                                                            $this->isAmbush,
                                                            false,
                                                            $units, # Becomes $invadingUnits
                                                            $mindControlledUnits
                                                          );
    }

}
