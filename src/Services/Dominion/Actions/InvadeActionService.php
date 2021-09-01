<?php

namespace OpenDominion\Services\Dominion\Actions;

use DB;
use Log;
use OpenDominion\Exceptions\GameException;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\Building;
use OpenDominion\Models\GameEvent;
use OpenDominion\Models\Unit;

use OpenDominion\Helpers\ImprovementHelper;
use OpenDominion\Helpers\SpellHelper;
use OpenDominion\Helpers\RaceHelper;
use OpenDominion\Helpers\UnitHelper;

use OpenDominion\Calculators\Dominion\BuildingCalculator;
use OpenDominion\Calculators\Dominion\ConversionCalculator;
use OpenDominion\Calculators\Dominion\CasualtiesCalculator;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\PopulationCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;
use OpenDominion\Calculators\Dominion\Actions\TrainingCalculator;

use OpenDominion\Services\NotificationService;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Services\Dominion\ProtectionService;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Services\Dominion\ResourceService;
use OpenDominion\Services\Dominion\StatsService;
use OpenDominion\Services\Dominion\Actions\SpellActionService;

class InvadeActionService
{

    /**
     * @var float Base percentage of defensive casualties
     */
    protected const CASUALTIES_DEFENSIVE_BASE_PERCENTAGE = 5.0;

    /**
     * @var float Max percentage of defensive casualties
     */
    protected const CASUALTIES_DEFENSIVE_MAX_PERCENTAGE = 8.0;

    /**
     * @var float Base percentage of offensive casualties
     */
    protected const CASUALTIES_OFFENSIVE_BASE_PERCENTAGE = 10.0;

    /**
     * @var int The minimum morale required to initiate an invasion
     */
    protected const MIN_MORALE = 50;

    /**
     * @var float Failing an invasion by this percentage (or more) results in 'being overwhelmed'
     */
    protected const OVERWHELMED_PERCENTAGE = 15.0;

    /**
     * @var float Percentage of mind controlled units that perish
     */
    protected const MINDCONTROLLED_UNITS_CASUALTIES = 10;

    /**
     * @var float Percentage of units to be stunned
     */
    protected const STUN_RATIO = 1;

    /**
     * @var float Percentage of units to be stunned
     */
    protected const MINIMUM_DPA = 10;

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

    // todo: refactor to use $invasionResult instead
    /** @var int The amount of land lost during the invasion */
    protected $landLost = 0;

    /** @var int The amount of units lost during the invasion */
    protected $unitsLost = 0;

    public function __construct()
    {
        $this->buildingCalculator = app(BuildingCalculator::class);
        $this->casualtiesCalculator = app(CasualtiesCalculator::class);
        $this->conversionCalculator = app(ConversionCalculator::class);
        $this->improvementCalculator = app(ImprovementCalculator::class);
        $this->improvementHelper = app(ImprovementHelper::class);
        $this->landCalculator = app(LandCalculator::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->notificationService = app(NotificationService::class);
        $this->populationCalculator = app(PopulationCalculator::class);
        $this->protectionService = app(ProtectionService::class);
        $this->statsService = app(StatsService::class);
        $this->queueService = app(QueueService::class);
        $this->rangeCalculator = app(RangeCalculator::class);
        $this->resourceCalculator = app(ResourceCalculator::class);
        $this->resourceService = app(ResourceService::class);
        $this->spellActionService = app(SpellActionService::class);
        $this->spellCalculator = app(SpellCalculator::class);
        $this->spellHelper = app(SpellHelper::class);
        $this->trainingCalculator = app(TrainingCalculator::class);
        $this->raceHelper = app(RaceHelper::class);
        $this->unitHelper = app(UnitHelper::class);
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
            #if ($dominion->round->hasOffensiveActionsDisabled()) {
            #    throw new GameException('Invasions have been disabled for the remainder of the round.');
            #}

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

            # Populate units defending
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

            if (!$this->hasAnyOP($dominion, $units)) {
                throw new GameException('You need to send at least some units.');
            }

            if (!$this->allUnitsHaveOP($dominion, $units, $target, $landRatio)) {
                throw new GameException('You cannot send units that have no offensive power.');
            }

            if (!$this->hasEnoughUnitsAtHome($dominion, $units)) {
                throw new GameException('You don\'t have enough units at home to send this many units.');
            }
            if ($dominion->race->name !== 'Barbarian')
            {
                if ($dominion->morale < static::MIN_MORALE)
                {
                    throw new GameException('You do not have enough morale to invade.');
                }

                if (!$this->passes43RatioRule($dominion, $target, $landRatio, $units))
                {
                    throw new GameException('You are sending out too much OP, based on your new home DP (4:3 rule).');
                }

                if (!$this->passesMinimumDpaCheck($dominion, $target, $landRatio, $units))
                {
                    throw new GameException('You are sending less than the lowest possible DP of the target. Minimum DPA (Defense Per Acre) is ' . static::MINIMUM_DPA . '. Double check your calculations and units sent.');
                }

            }

            foreach($units as $amount)
            {
               if($amount < 0) {
                   throw new GameException('Invasion was canceled due to bad input.');
               }
             }

            if ($dominion->race->getPerkValue('cannot_invade') == 1)
            {
                throw new GameException($dominion->race->name . ' cannot invade other dominions.');
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
            if ($dominion->getSpellPerkValue('cannot_invade'))
            {
                throw new GameException('A spell is preventing from you invading.');
            }

            // Check building_limit
            foreach($units as $unitSlot => $amount)
            {
                if($buildingLimit = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot,'building_limit'))
                {
                    $buildingKeyLimitedTo = $buildingLimit[0]; # Land type
                    $unitsPerBuilding = (float)$buildingLimit[1]; # Units per building
                    $unitsPerBuilding *= (1 + $dominion->getImprovementPerkMultiplier('unit_pairing'));

                    $building = Building::where('key', $buildingKeyLimitedTo)->first();
                    $dominionBuildings = $this->buildingCalculator->getDominionBuildings($dominion);
                    $amountOfLimitingBuilding = $dominionBuildings->where('building_id', $building->id)->first()->owned;

                    $maxSendableOfThisUnit = $amountOfLimitingBuilding * $unitsPerBuilding;

                    if($amount > $maxSendableOfThisUnit)
                    {
                        throw new GameException('You can at most send ' . number_format($upperLimit) . ' ' . str_plural($this->unitHelper->getUnitName($unitSlot, $dominion->race), $upperLimit) . '. To send more, you must build more '. ucwords(str_plural($buildingLimit[0], 2)) .' or invest more in unit pairing improvements.');
                    }
                }
            }

            // Cannot invade until round has started.
            if(!$dominion->round->hasStarted())
            {
                throw new GameException('You cannot invade until the round has started.');
            }

            // Dimensionalists: must have Portals open.
            if($dominion->race->name == 'Dimensionalists' and !$dominion->getSpellPerkValue('opens_portal'))
            {
                throw new GameException('You cannot attack unless a portal is open.');
            }

            // Qur: Statis cannot be invaded.
            if($this->spellCalculator->getPassiveSpellPerkValue($target, 'stasis'))
            {
                throw new GameException('A magical stasis surrounds the Qurrian lands, making it impossible for your units to invade.');
            }

            // Qur: Statis cannot invade.
            if($dominion->getSpellPerkValue('stasis'))
            {
                throw new GameException('You cannot invade while you are in stasis.');
            }

            $this->invasionResult['defender']['recentlyInvadedCount'] = $this->militaryCalculator->getRecentlyInvadedCount($target);
            $this->invasionResult['attacker']['unitsSent'] = $units;
            $this->invasionResult['attacker']['landSize'] = $this->landCalculator->getTotalLand($dominion);
            $this->invasionResult['defender']['landSize'] = $this->landCalculator->getTotalLand($target);

            // Handle pre-invasion
            $this->handleBeforeInvasionPerks($dominion);
            $this->handleMindControl($target, $dominion, $units);

            // Handle invasion results
            $this->checkInvasionSuccess($dominion, $target, $units);
            $this->checkOverwhelmed();

            $this->handleAnnexedDominions($dominion, $target, $units);

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

            $this->handlePrestigeChanges($dominion, $target, $units, $landRatio, $countsAsVictory, $countsAsBottomfeed, $countsAsFailure, $countsAsRaze);
            $this->handleDuringInvasionUnitPerks($dominion, $target, $units);

            #$survivingUnits = $this->handleOffensiveCasualties($dominion, $target, $units, $landRatio,  $this->invasionResult['defender']['mindControlledUnits']);
            $this->invasionResult['attacker']['survivingUnits'] = $this->handleOffensiveCasualties($dominion, $target, $units, $landRatio,  $this->invasionResult['defender']['mindControlledUnits']);

            $totalDefensiveCasualties = $this->handleDefensiveCasualties($dominion, $target, $units, $landRatio);

            $this->handleMoraleChanges($dominion, $target, $landRatio);
            $this->handleLandGrabs($dominion, $target, $landRatio, $units);
            $this->handleResearchPoints($dominion, $target, $units);

            # Qur
            $this->handleZealots($dominion, $target, $this->invasionResult['attacker']['survivingUnits']);

            # Cult
            $this->handleMenticide($target, $dominion);

            # Dwarf
            $this->handleStun($dominion, $target, $units, $landRatio);

            # Conversions
            $offensiveConversions = array_fill(1, 4, 0);
            $defensiveConversions = array_fill(1, 4, 0);
            $conversions = $this->conversionCalculator->getConversions($dominion, $target, $this->invasionResult, $landRatio);

            if(array_sum($conversions['attacker']) > 0)
            {
                $offensiveConversions = $conversions['attacker'];
                $this->invasionResult['attacker']['conversions'] = $offensiveConversions;
                $this->statsService->updateStat($dominion, 'units_converted', array_sum($conversions['attacker']));
            }
            if(array_sum($conversions['defender']) > 0)
            {
                $defensiveConversions = $conversions['defender'];
                $this->invasionResult['defender']['conversions'] = $defensiveConversions;
                $this->statsService->updateStat($target, 'units_converted', array_sum($conversions['defender']));
            }

            $this->handleReturningUnits($dominion, $this->invasionResult['attacker']['survivingUnits'], $offensiveConversions, $this->invasionResult['defender']['mindControlledUnits'], $defensiveConversions);
            $this->handleDefensiveConversions($target, $defensiveConversions);

            # Afflicted
            $this->handleInvasionSpells($dominion, $target);

            # Handle dies_into_resource, dies_into_resources, kills_into_resource, kills_into_resources
            $this->handleResourceConversions($dominion, $target, $landRatio);

            # Demon
            #$this->handleSoulBloodFoodCollection($dominion, $target, $landRatio);

            # Norse
            #$this->handleChampionCreation($dominion, $target, $units, $landRatio);

            # Salvage and Plunder
            $this->handleSalvagingAndPlundering($dominion, $target, $this->invasionResult['attacker']['survivingUnits']);

            # Growth
            $this->handleMetabolism($dominion, $target, $landRatio);

            # Imperial Crypt
            $this->handleCrypt($dominion, $target, $this->invasionResult['attacker']['survivingUnits'], $offensiveConversions, $defensiveConversions);

            // Stat changes
            if ($this->invasionResult['result']['success'])
            {
                $this->statsService->updateStat($dominion, 'land_conquered', (int)array_sum($this->invasionResult['attacker']['landConquered']));
                $this->statsService->updateStat($dominion, 'land_discovered', (int)array_sum($this->invasionResult['attacker']['landDiscovered']));
                $this->statsService->updateStat($dominion, 'invasion_victories', $countsAsVictory);
                $this->statsService->updateStat($dominion, 'invasion_bottomfeeds', $countsAsBottomfeed);

                $this->statsService->updateStat($target, 'land_lost', (int)array_sum($this->invasionResult['attacker']['landConquered']));
                $this->statsService->updateStat($target, 'defense_failures', 1);
            }
            else
            {
                $this->statsService->updateStat($dominion, 'invasion_razes', $countsAsRaze);
                $this->statsService->updateStat($dominion, 'invasion_failures', $countsAsFailure);

                $this->statsService->updateStat($target, 'defense_success', 1);
            }

            # Debug before saving:
            if(request()->getHost() === 'odarena.local')
            {
                #dd($this->invasionResult);
            }

            # LEGION ANNEX SUPPORT EVENTS
            $legion = null;
            if($this->spellCalculator->hasAnnexedDominions($dominion))
            {
                $legion = $dominion;
                $legionString = 'attacker';
                $type = 'invasion_support';
                $targetId = $legion->id;

                if($target->race->name == 'Barbarian')
                {
                    $legion = null;
                }

            }
            elseif($this->spellCalculator->hasAnnexedDominions($target))
            {
                $legion = $target;
                $legionString = 'defender';
                $type = 'defense_support';
                $targetId = $target->id;

                if($dominion->race->name == 'Barbarian')
                {
                    $legion = null;
                }
            }

            if($legion)
            {
                if(isset($this->invasionResult[$legionString]['annexation']) and $this->invasionResult[$legionString]['annexation']['hasAnnexedDominions'] > 0 and $this->invasionResult['result']['opDpRatio'] >= 0.85)
                {
                    foreach($this->invasionResult[$legionString]['annexation']['annexedDominions'] as $annexedDominionId => $annexedDominionData)
                    {
                        # If there are troops to send
                        if(array_sum($this->invasionResult[$legionString]['annexation']['annexedDominions'][$annexedDominionId]['unitsSent']) > 0)
                        {
                            $annexedDominion = Dominion::findorfail($annexedDominionId);

                            $this->invasionEvent = GameEvent::create([
                                'round_id' => $annexedDominion->round_id,
                                'source_type' => Dominion::class,
                                'source_id' => $annexedDominion->id,
                                'target_type' => Dominion::class,
                                'target_id' => $targetId,
                                'type' => $type,
                                'data' => NULL,
                            ]);

                            $annexedDominion->save(['event' => HistoryService::EVENT_ACTION_INVADE_SUPPORT]);
                        }
                    }
                }
            }
            # LIBERATION
            elseif(isset($this->invasionResult['attacker']['liberation']) and $this->invasionResult['attacker']['liberation'])
            {
                $this->spellActionService->breakSpell($target, 'annexation', $this->invasionResult['attacker']['liberation']);
            }

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

    protected function handlePrestigeChanges(Dominion $attacker, Dominion $defender, array $units, float $landRatio, int $countsAsVictory, int $countsAsBottomfeed, int $countsAsFailure, int $countsAsRaze): void
    {

        $attackerPrestigeChange = 0;
        $defenderPrestigeChange = 0;

        # LDA mitigation
        $victoriesRatioMultiplier = 1;
        if($this->statsService->getStat($attacker, 'defense_failures') >= 10)
        {
            $victoriesRatioMultiplier = $this->statsService->getStat($attacker, 'invasion_victories') / ($this->statsService->getStat($attacker, 'invasion_victories') + $this->statsService->getStat($attacker, 'defense_failures'));
        }

        # Successful hits over 75% give prestige to attacker and remove prestige from defender
        if($countsAsVictory)
        {
            $attackerPrestigeChange += 60 * $landRatio * $victoriesRatioMultiplier;
            $defenderPrestigeChange -= 20 * $landRatio;
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

        // Building
        $attackerPrestigeChangeMultiplier += $attacker->getBuildingPerkMultiplier('prestige_gains');

        // Improvements
        $attackerPrestigeChangeMultiplier += $attacker->getImprovementPerkMultiplier('prestige_gains');

        // Spells
        $attackerPrestigeChangeMultiplier += $attacker->getSpellPerkMultiplier('prestige_gains');

        // Title
        $attackerPrestigeChangeMultiplier += $attacker->title->getPerkMultiplier('prestige_gains') * $attacker->title->getPerkBonus($attacker);

        $attackerPrestigeChange *= (1 + $attackerPrestigeChangeMultiplier);

        // 1/3 gains for hitting Barbarians.
        if($defender->race->name === 'Barbarian')
        {
            $attackerPrestigeChange /= 3;
        }

        # Liberation
        if($defender->race->name === 'Barbarian' and $attacker->realm->alignment !== 'evil' and $this->invasionResult['result']['success'] /* and $attackerPrestigeChange > 0 */ and $this->spellCalculator->isAnnexed($defender))
        {
            $this->invasionResult['attacker']['liberation'] = true;
            $attackerPrestigeChange = max(0, $attackerPrestigeChange);
            $attackerPrestigeChange *= 3;
        }

        # Cut in half when hitting abandoned dominions
        if($defender->isAbandoned() and $attackerPrestigeChange > 0)
        {
            $attackerPrestigeChange /= 2;
        }

        $attackerPrestigeChange = round($attackerPrestigeChange);
        $defenderPrestigeChange = round($defenderPrestigeChange);

        if ($attackerPrestigeChange !== 0)
        {
            if (!$this->invasionResult['result']['success'])
            {
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

        $offensiveCasualtiesPercentage *= 1 + $this->spellCalculator->getPassiveSpellPerkMultiplier($target, 'increases_casualties_on_defense');

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
            $unitsToKillMultiplier = $this->casualtiesCalculator->getOffensiveCasualtiesMultiplierForUnitSlot($dominion, $target, $slot, $units, $landRatio, $isOverwhelmed, $attackingForceOP, $targetDP, $isInvasionSuccessful);

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

                $this->statsService->updateStat($dominion, ('unit' . $slot . '_lost'), $amount);
                $this->statsService->updateStat($target, 'units_killed', $amount);

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

        $isInvasionSuccessful = $this->invasionResult['result']['success'];
        $isOverwhelmed = $this->invasionResult['result']['overwhelmed'];

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

        $drafteesLost = (int)floor($target->military_draftees * $defensiveCasualtiesPercentage * $this->casualtiesCalculator->getDefensiveCasualtiesMultiplierForUnitSlot($target, $dominion, 'draftees', $units, $landRatio, $isOverwhelmed, $attackingForceOP, $targetDP, $isInvasionSuccessful));

        // Spell
        $drafteesLost = (int)min($target->military_draftees, $drafteesLost * (1 + $dominion->getSpellPerkMultiplier('increases_enemy_draftee_casualties')));

        if ($drafteesLost > 0)
        {
            $target->military_draftees -= $drafteesLost;
            $this->unitsLost += $drafteesLost;
            $this->invasionResult['defender']['unitsLost']['draftees'] = $drafteesLost;
        }

        if($target->getSpellPerkValue('defensive_power_from_peasants'))
        {
            $peasantsLost = (int)floor($target->military_peasants * $defensiveCasualtiesPercentage * $this->casualtiesCalculator->getDefensiveCasualtiesMultiplierForUnitSlot($target, $dominion, 'peasants', $units, $landRatio, $isOverwhelmed, $attackingForceOP, $targetDP, $isInvasionSuccessful));

            // Spell
            $peasantsLost = min($target->military_draftees, $peasantsLost * (1 + $dominion->getSpellPerkMultiplier('increases_enemy_draftee_casualties')));

            if($peasantsLost > 0)
            {
                $target->peasants -= $peasantsLost;
                $this->unitsLost += $drafteesLost;
                $this->invasionResult['defender']['unitsLost']['peasants'] = $peasantsLost;
            }

        }

        // Non-draftees
        foreach ($target->race->units as $unit)
        {

            if ($this->militaryCalculator->getUnitPowerWithPerks($target, $dominion, $landRatio, $unit, 'defense', null, $units, $this->invasionResult['attacker']['unitsSent']) === 0.0)
            {
                continue;
            }

            $slotLostMultiplier = $this->casualtiesCalculator->getDefensiveCasualtiesMultiplierForUnitSlot($target, $dominion, $unit->slot, $units, $landRatio, $isOverwhelmed, $attackingForceOP, $targetDP, $isInvasionSuccessful);
            $slotLost = (int)floor($target->{"military_unit{$unit->slot}"} * $defensiveCasualtiesPercentage * $slotLostMultiplier);
            $this->invasionResult['defender']['unitPerks']['defensiveCasualties'][$unit->slot] = $slotLostMultiplier;

            if ($slotLost > 0)
            {
                $defensiveUnitsLost[$unit->slot] = $slotLost;
                $this->statsService->updateStat($target, ('unit' . $unit->slot . '_lost'), $slotLost);
                $this->statsService->updateStat($dominion, 'units_killed', $slotLost);
                $this->unitsLost += $slotLost; // todo: refactor
            }
        }

        # Look for dies_into amongst the dead defenders.
        $diesIntoNewUnits = array_fill(1,4,0);
        $diesIntoNewUnitsInstantly = array_fill(1,4,0);

        foreach($defensiveUnitsLost as $slot => $casualties)
        {
            if($diesIntoPerk = $target->race->getUnitPerkValueForUnitSlot($slot, 'dies_into'))
            {
                $slot = (int)$diesIntoPerk[0];

                $diesIntoNewUnits[$slot] += intval($casualties);
            }

            if($diesIntoPerk = $target->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_on_defense'))
            {
                $slot = (int)$diesIntoPerk[0];

                $diesIntoNewUnits[$slot] += intval($casualties);
            }

            if($diesIntoPerk = $target->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_on_defense_instantly'))
            {
                $slot = (int)$diesIntoPerk[0];

                $diesIntoNewUnitsInstantly[$slot] += intval($casualties);
            }

            if($diesIntoMultiplePerk = $target->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple'))
            {
                $slot = (int)$diesIntoMultiplePerk[0];
                $amount = (float)$diesIntoMultiplePerk[1];

                $diesIntoNewUnits[$slot] += intval($casualties * $amount);
            }

            if($diesIntoMultiplePerk = $target->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple_on_defense'))
            {
                $slot = (int)$diesIntoMultiplePerk[0];
                $amount = (float)$diesIntoMultiplePerk[1];

                $diesIntoNewUnits[$slot] += intval($casualties * $amount);
            }

            if($diesIntoMultiplePerk = $target->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple_on_defense_instantly'))
            {
                $slot = (int)$diesIntoMultiplePerk[0];
                $amount = (float)$diesIntoMultiplePerk[1];

                $diesIntoNewUnitsInstantly[$slot] += intval($casualties * $amount);
            }

            if(!$this->invasionResult['result']['success'] and $diesIntoMultiplePerkOnVictory = $target->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple_on_victory'))
            {
                $slot = (int)$diesIntoMultiplePerkOnVictory[0];
                $amount = (float)$diesIntoMultiplePerkOnVictory[1];

                $diesIntoNewUnits[$slot] += intval($casualties * $amount);
            }
        }

        # Dies into units take 1 tick to appear
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

        # Dies into units take 1 tick to appear
        foreach($diesIntoNewUnitsInstantly as $slot => $amount)
        {
            $unitKey = 'military_unit'.$slot;
            $target->{$unitKey} += $amount;
            #$target->fill([
            #    $unitKey => ($dominion->{$unitKey} + $amount)
            #])->save();
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
            $buildingsLostForLandType = $this->buildingCalculator->getBuildingsToDestroy($target, $buildingsToDestroy, $landType);

            // Remove land
            $target->{"land_$landType"} -= $landLost;
            $this->invasionResult['defender']['totalBuildingsLost'] += $landLost;

            // Destroy buildings
            foreach ($buildingsLostForLandType as $buildingType => $buildingsLost)
            {
                $this->buildingCalculator->removeBuildings($target, [$buildingType => $buildingsLost]);

                $builtBuildingsToDestroy = $buildingsLost['builtBuildingsToDestroy'];

                $resourceName = "building_{$buildingType}";

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

            $attackerMoraleChangeMultiplier = 0;
            $attackerMoraleChangeMultiplier += $dominion->getBuildingPerkMultiplier('morale_gains');
            $attackerMoraleChangeMultiplier += $dominion->race->getPerkMultiplier('morale_change_invasion');
            $attackerMoraleChangeMultiplier += $dominion->title->getPerkMultiplier('morale_gains') * $dominion->title->getPerkBonus($dominion);

            $attackerMoraleChange *= (1 + $attackerMoraleChangeMultiplier);


            $defenderMoraleChangeMultiplier = 0;
            $defenderMoraleChangeMultiplier += $dominion->race->getPerkMultiplier('morale_change_invasion');

            $defenderMoraleChange *= (1 + $defenderMoraleChangeMultiplier);

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

        # Round
        $attackerMoraleChange = round($attackerMoraleChange);
        $defenderMoraleChange = round($defenderMoraleChange);

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
        $researchPointsPerAcreMultiplier += $dominion->race->getPerkMultiplier('research_points_per_acre');
        $researchPointsPerAcreMultiplier += $dominion->getImprovementPerkMultiplier('research_points_per_acre');

        $isInvasionSuccessful = $this->invasionResult['result']['success'];
        if ($isInvasionSuccessful)
        {
            $landConquered = array_sum($this->invasionResult['attacker']['landConquered']);

            $researchPointsForGeneratedAcres = 1;

            if($this->militaryCalculator->getRecentlyInvadedCountByAttacker($target, $dominion) == 0)
            {
                $researchPointsForGeneratedAcres = 2;
            }


            $researchPointsGained = round(1.5 * ($landConquered * $researchPointsForGeneratedAcres * $researchPointsPerAcre * $researchPointsPerAcreMultiplier));
            $slowestTroopsReturnHours = $this->getSlowestUnitReturnHours($dominion, $units);

            $this->queueService->queueResources(
                'invasion',
                $dominion,
                ['xp' => $researchPointsGained],
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
        # Snow Elf: Hailstorm Cannon exhausts all mana
        for ($slot = 1; $slot <= 4; $slot++)
        {
            if($exhaustingPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'offense_from_resource_exhausting') and isset($units[$slot]))
            {
                $resourceKey = $exhaustingPerk[0];
                $resourceAmount = $this->resourceCalculator->getAmount($dominion, $resourceKey);

                $this->invasionResult['attacker'][$resourceKey . '_exhausted'] = $resourceAmount;

                $this->resourceService->updateResources($dominion, [$resourceKey => ($resourceAmount * -1)]);

            }
        }

        # Ignore if attacker is overwhelmed.
        if(!$this->invasionResult['result']['overwhelmed'])
        {
            for ($unitSlot = 1; $unitSlot <= 4; $unitSlot++)
            {
                // burns_peasants
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
                      $burnedPeasants = $burningUnits * $peasantsBurnedPerUnit * min($this->invasionResult['result']['opDpRatio'], 1);
                      $burnedPeasants = min(($target->peasants-1000), $burnedPeasants);
                  }
                  $target->peasants -= $burnedPeasants;
                  $this->invasionResult['attacker']['peasants_burned']['peasants'] = $burnedPeasants;
                  $this->invasionResult['defender']['peasants_burned']['peasants'] = $burnedPeasants;

                }

                // Artillery: damages_improvements_on_attack
                if ($dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'damages_improvements_on_attack') and isset($units[$unitSlot]))
                {
                    $improvementsToBeDamaged = [];

                    $damageReductionFromMasonries = 1 - $target->getBuildingPerkMultiplier('lightning_bolt_damage');

                    $damagingUnits = $units[$unitSlot];
                    $damagePerUnit = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'damages_improvements_on_attack');
                    $damageDone = $damagingUnits * $damagePerUnit * $damageReductionFromMasonries;


                    # Calculate target's total imp points, where imp points > 0.
                    foreach ($this->improvementHelper->getImprovementsByRace($target->race) as $improvement)
                    {
                        $improvementsToBeDamaged[$improvement->key] = $this->improvementCalculator->getDominionImprovementAmountInvested($target, $improvement);
                    }
                    $improvementsTotal = array_sum($improvementsToBeDamaged);

                    if($improvementsTotal > 0)
                    {
                        $this->improvementCalculator->decreaseImprovements($target, $improvementsToBeDamaged);
                    }

                    $this->invasionResult['attacker']['improvements_damage']['improvement_points'] = $damageDone;
                    $this->invasionResult['defender']['improvements_damage']['improvement_points'] = $damageDone;
                }


                if ($dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'eats_peasants_on_attack') and isset($units[$unitSlot]))
                {
                    $eatingUnits = $units[$unitSlot];
                    $peasantsEatenPerUnit = (float)$dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'eats_peasants_on_attack');

                    # If target has less than 1000 peasants, we don't eat any.
                    if($target->peasants < 1000)
                    {
                      $eatenPeasants = 0;
                    }
                    else
                    {
                        $eatenPeasants = round($eatingUnits * $peasantsEatenPerUnit * min($this->invasionResult['result']['opDpRatio'], 1));
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

                    $eatenDraftees = round($eatingUnits * $drafteesEatenPerUnit * min($this->invasionResult['result']['opDpRatio'], 1));
                    $eatenDraftees = min($target->military_draftees, $eatenDraftees);

                    $target->military_draftees -= $eatenDraftees;
                    $this->invasionResult['attacker']['draftees_eaten']['draftees'] = $eatenDraftees;
                    $this->invasionResult['defender']['draftees_eaten']['draftees'] = $eatenDraftees;
                }
            }
        }

    }

    protected function handleMenticide(Dominion $cult, Dominion $attacker)
    {
        if(array_sum($this->invasionResult['defender']['mindControlledUnits']) > 0 and $cult->race->name === 'Cult' and $cult->getSpellPerkValue('menticide'))
        {
            $this->invasionResult['defender']['isMenticide'] = true;

            $this->invasionResult['defender']['mindControlledUnitsReleased'] = array_fill(1, 4, 0);
            $newThralls = 0;

            foreach($this->invasionResult['defender']['mindControlledUnits'] as $slot => $amount)
            {
                $newThralls += $amount * (1 - (static::MINDCONTROLLED_UNITS_CASUALTIES / 100));
            }

            $this->invasionResult['defender']['menticide']['newThralls'] = $newThralls;
            $cult->military_unit1 += $newThralls;
        }
    }

    protected function handleStun(Dominion $attacker, Dominion $defender, array $units, float $landRatio)
    {

        $opDpRatio = $this->invasionResult['attacker']['op'] / $this->invasionResult['defender']['dp'];

        $rawOp = 0;
        $stunningOp = 0;

        # Calculate how much of raw OP came from stunning units
        foreach($units as $slot => $amount)
        {

            $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                return ($unit->slot == $slot);
            })->first();

            $unitsOp = $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense') * $amount;

            $rawOp += $unitsOp;

            if($attacker->race->getUnitPerkValueForUnitSlot($slot, 'stuns_units'))
            {
                $stunningOp += $unitsOp;
            }
        }

        if($stunningOp > 0)
        {
            $stunningOpRatio = $stunningOp / $rawOp;

            $stunRatio = min((static::STUN_RATIO / 100) * $opDpRatio * min($stunningOpRatio, 1), 2.5);

            # Collect the stunnable units
            $stunnableUnits = array_fill(1, 4, 0);

            # Exclude certain attributes
            $unconvertibleAttributes = [
                'ammunition',
                'equipment',
                'magical',
                'massive',
                'machine',
                'ship',
              ];

            foreach($this->invasionResult['defender']['unitsDefending'] as $slot => $amount)
            {
                if($slot !== 'draftees')
                {
                    if(isset($this->invasionResult['defender']['unitsLost'][$slot]) and $this->invasionResult['defender']['unitsLost'][$slot] > 0)
                    {
                        $amount -= $this->invasionResult['defender']['unitsLost'][$slot];
                    }
                    $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                        return ($unit->slot === $slot);
                    })->first();

                    $unitRawDp = $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'defense');
                    $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

                    # Only add unit to available casualties if it has none of the unconvertible unit attributes.
                    if(count(array_intersect($unconvertibleAttributes, $unitAttributes)) === 0 and $unitRawDp < 10)
                    {
                        $stunnableUnits[$slot] = (int)$amount;
                    }
                }
                else
                {
                    if($amount > 0)
                    {
                        $amount -= $this->invasionResult['defender']['unitsLost'][$slot];
                    }
                    $stunnableUnits['draftees'] = (int)$amount;
                }
             }

             foreach($stunnableUnits as $slot => $amount)
             {
                $amount = (int)round($amount * $stunRatio);
                $this->invasionResult['defender']['unitsStunned'][$slot] = $amount;

                # Stunned units take 2 ticks to return
                if($slot !== 'draftees')
                {
                    $unitKey = 'military_unit'.$slot;
                }
                else
                {
                    $unitKey = 'military_draftees';
                }

                $defender->$unitKey -= $amount;
                $this->queueService->queueResources(
                    'invasion',
                    $defender,
                    [$unitKey => $amount],
                    2
                );
             }
        }
    }

    # Unit Return 2.0
    protected function handleReturningUnits(Dominion $attacker, array $units, array $convertedUnits, array $mindControlledUnits): void
    {
        # If instant return
        if(random_chance($attacker->getImprovementPerkMultiplier('chance_of_instant_return')) or $attacker->race->getPerkValue('instant_return') or $attacker->getSpellPerkValue('instant_return'))
        #if(1==1)
        {
            $this->invasionResult['attacker']['instantReturn'] = true;
            foreach($units as $unitKey => $returningAmount)
            {
                $attacker->{$unitKey} += $returningAmount;
                $returningUnits[$unitKey] = 0;
            }
        }
        # Normal return
        else
        {
            $returningUnits = [
              'military_unit1' => array_fill(1, 12, 0),
              'military_unit2' => array_fill(1, 12, 0),
              'military_unit3' => array_fill(1, 12, 0),
              'military_unit4' => array_fill(1, 12, 0),
              #'military_spies' => array_fill(1, 12, 0),
              #'military_wizards' => array_fill(1, 12, 0),
              #'military_archmages' => array_fill(1, 12, 0),
              #'military_draftees' => array_fill(1, 12, 0),
              #'military_peasants' => array_fill(1, 12, 0),
            ];

            $someWinIntoUnits = array_fill(1, 4, 0);
            $someWinIntoUnits = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

            foreach($returningUnits as $unitKey => $values)
            {
                $slot = str_replace('military_unit', '', $unitKey);
                $amountReturning = 0;

                $returningUnitKey = $unitKey;

                # See if slot $slot has wins_into perk.
                if($this->invasionResult['result']['success'])
                {
                    if($attacker->race->getUnitPerkValueForUnitSlot($slot, 'wins_into'))
                    {
                        $returnsAsSlot = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'wins_into');
                        $returningUnitKey = 'military_unit' . $returnsAsSlot;
                    }
                    if($someWinIntoPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'some_win_into'))
                    {
                        $ratio = (float)$someWinIntoPerk[0] / 100;
                        $newSlot = (int)$someWinIntoPerk[1];

                        if(isset($units[$slot]))
                        {
                            $newUnits = (int)floor($units[$slot] * $ratio);
                            $someWinIntoUnits[$newSlot] += $newUnits;
                            $amountReturning -= $newUnits;
                        }
                    }
                }

                # Remove the units from attacker and add them to $amountReturning.
                if (array_key_exists($slot, $units))
                {
                    $attacker->$unitKey -= $units[$slot];
                    $amountReturning += $units[$slot];
                }

                # Check if we have conversions for this unit type/slot
                if (array_key_exists($slot, $convertedUnits))
                {
                    $amountReturning += $convertedUnits[$slot];
                }

                # Check if we have some winning into
                if (array_key_exists($slot, $someWinIntoUnits))
                {
                    $amountReturning += $someWinIntoUnits[$slot];
                }

                if(isset($mindControlledUnits[$slot]) and $mindControlledUnits[$slot] > 0)
                {
                    # Release non-menticided mind controlled units, less 10%.
                    $returningAmount += $this->invasionResult['defender']['mindControlledUnitsReleased'][$slot];
                }

                # Default return time is 12 ticks.
                $ticks = $this->getUnitReturnTicksForSlot($attacker, $slot);

                # Default all returners to tick 12
                $returningUnits[$returningUnitKey][$ticks] += $amountReturning;

                # Look for dies_into and variations amongst the dead attacking units.
                if(isset($this->invasionResult['attacker']['unitsLost'][$slot]))
                {
                    $casualties = $this->invasionResult['attacker']['unitsLost'][$slot];

                    if($diesIntoPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'dies_into'))
                    {
                        # Which unit do they die into?
                        $newUnitSlot = $diesIntoPerk[0];
                        $newUnitKey = "military_unit{$newUnitSlot}";
                        $newUnitSlotReturnTime = $this->getUnitReturnTicksForSlot($attacker, $newUnitSlot);

                        $returningUnits[$newUnitKey][$newUnitSlotReturnTime] += $casualties;
                    }

                    if($diesIntoPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_on_offense'))
                    {
                        # Which unit do they die into?
                        $newUnitSlot = $diesIntoPerk[0];
                        $newUnitKey = "military_unit{$newUnitSlot}";
                        $newUnitSlotReturnTime = $this->getUnitReturnTicksForSlot($attacker, $newUnitSlot);

                        $returningUnits[$newUnitKey][$newUnitSlotReturnTime] += $casualties;
                    }

                    if($diesIntoMultiplePerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple'))
                    {
                        # Which unit do they die into?
                        $newUnitSlot = $diesIntoMultiplePerk[0];
                        $newUnitAmount = (float)$diesIntoMultiplePerk[1];
                        $newUnitKey = "military_unit{$newUnitSlot}";
                        $newUnitSlotReturnTime = $this->getUnitReturnTicksForSlot($attacker, $newUnitSlot);

                        $returningUnits[$newUnitKey][$newUnitSlotReturnTime] += floor($casualties * $newUnitAmount);
                    }

                    if($diesIntoMultiplePerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple_on_offense'))
                    {
                        # Which unit do they die into?
                        $newUnitSlot = $diesIntoMultiplePerk[0];
                        $newUnitAmount = (float)$diesIntoMultiplePerk[1];
                        $newUnitKey = "military_unit{$newUnitSlot}";
                        $newUnitSlotReturnTime = $this->getUnitReturnTicksForSlot($attacker, $newUnitSlot);

                        $returningUnits[$newUnitKey][$newUnitSlotReturnTime] += floor($casualties * $newUnitAmount);
                    }

                    if($this->invasionResult['result']['success'] and $diesIntoMultiplePerkOnVictory = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple_on_victory'))
                    {
                        # Which unit do they die into?
                        $newUnitSlot = $diesIntoMultiplePerkOnVictory[0];
                        $newUnitAmount = (float)$diesIntoMultiplePerkOnVictory[1];
                        $newUnitKey = "military_unit{$newUnitSlot}";
                        $newUnitSlotReturnTime = $this->getUnitReturnTicksForSlot($attacker, $newUnitSlot);

                        $returningUnits[$newUnitKey][$newUnitSlotReturnTime] += floor($casualties * $newUnitAmount);
                    }

                    if(!$this->invasionResult['result']['success'] and $diesIntoMultiplePerkOnVictory = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple_on_victory'))
                    {
                        # Which unit do they die into?
                        $newUnitSlot = $diesIntoMultiplePerkOnVictory[0];
                        $newUnitAmount = $diesIntoMultiplePerkOnVictory[2];
                        $newUnitKey = "military_unit{$newUnitSlot}";
                        $newUnitSlotReturnTime = $this->getUnitReturnTicksForSlot($attacker, $newUnitSlot);

                        $returningUnits[$newUnitKey][$newUnitSlotReturnTime] += floor($casualties * $newUnitAmount);
                    }
                }

                # Check for faster_return_if_paired
                foreach($units as $slot => $amount)
                {
                    if($fasterReturnIfPairedPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'faster_return_if_paired'))
                    {
                        $pairedUnitSlot = (int)$fasterReturnIfPairedPerk[0];
                        $pairedUnitKey = 'military_unit'.$pairedUnitSlot;
                        $ticksFaster = (int)$fasterReturnIfPairedPerk[1];
                        $pairedUnitKeyReturning = array_sum($returningUnits[$pairedUnitKey]);

                        # Determine new return speed
                        $fasterReturningTicks = min(max($ticks - $ticksFaster, 1), 12);

                        # How many of $slot should return faster?
                        $unitsWithFasterReturnTime = min($pairedUnitKeyReturning, $amountReturning);
                        $unitsWithRegularReturnTime = max(0, $amount - $unitsWithFasterReturnTime);

                        $returningUnits[$unitKey][$fasterReturningTicks] += $unitsWithFasterReturnTime;
                        $returningUnits[$unitKey][$ticks] -= $unitsWithFasterReturnTime;
                    }
                }

                # Check for faster_return from buildings
                if($buildingFasterReturnPerk = $attacker->getBuildingPerkMultiplier('faster_return'))
                {
                    $fasterReturn = min(max(0, $buildingFasterReturnPerk), 1);
                    $normalReturn = 1 - $fasterReturn;
                    $ticksFaster = 6;

                    $fasterReturningTicks = min(max(1, ($ticks - $ticksFaster), 12));

                    $unitsWithFasterReturnTime = round($amountReturning * $buildingFasterReturnPerk);
                    $unitsWithRegularReturnTime = round($amountReturning - $amountWithFasterReturn);

                    $returningUnits[$unitKey][$fasterReturningTicks] += $unitsWithFasterReturnTime;
                    $returningUnits[$unitKey][$ticks] -= $unitsWithFasterReturnTime;
                }

                # Check for faster_return_units and faster_return_units_increasing from buildings
                if($buildingFasterReturnPerk = $attacker->getBuildingPerkValue('faster_returning_units') or $buildingFasterReturnPerk = $attacker->getBuildingPerkValue('faster_returning_units_increasing'))
                {
                    $fasterReturn = min(max(0, $buildingFasterReturnPerk), 1);
                    $normalReturn = 1 - $fasterReturn;
                    $ticksFaster = 4;

                    $fasterReturningTicks = min(max(1, ($ticks - $ticksFaster)), 12);

                    $unitsWithFasterReturnTime = min($buildingFasterReturnPerk, $amountReturning);
                    $unitsWithRegularReturnTime = round($amountReturning - $unitsWithFasterReturnTime);

                    $returningUnits[$unitKey][$fasterReturningTicks] += $unitsWithFasterReturnTime;
                    $returningUnits[$unitKey][$ticks] -= $unitsWithFasterReturnTime;
                }
            }

            foreach($returningUnits as $unitKey => $unitKeyTicks)
            {
                foreach($unitKeyTicks as $unitTypeTick => $amount)
                {
                    if($amount > 0)
                    {
                        $this->queueService->queueResources(
                            'invasion',
                            $attacker,
                            [$unitKey => $amount],
                            $unitTypeTick
                        );
                    }
                }
                $slot = str_replace('military_unit', '', $unitKey);
                $this->invasionResult['attacker']['unitsReturning'][$slot] = array_sum($unitKeyTicks);
            }
        }
    }

    protected function handleDefensiveConversions(Dominion $defender, array $defensiveConversions): void
    {
        if(array_sum($defensiveConversions) > 0)
        {
            # Defensive conversions take 6 ticks to appear
            foreach($defensiveConversions as $slot => $amount)
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

        /*
            Spells to check for:
            [AFFLICTED]
              - [ATTACKER] Pestilence: Within 50% of target's DP? Cast.
              - [ATTACKER] Great Fever: Is Invasion successful? Cast.
              - [DEFENDER] Unhealing Wounds: Is target Afflicted? Cast.
            [/AFFLICTED]
        */

        if($attacker->race->name == 'Afflicted')
        {
            # Pestilence
            if($this->invasionResult['attacker']['op'] / $this->invasionResult['defender']['dp'] >= 0.50)
            {
                $this->spellActionService->castSpell($attacker, 'pestilence', $defender, $isInvasionSpell);
            }

            # Great Fever
            if($this->invasionResult['result']['success'])
            {
                $this->spellActionService->castSpell($attacker, 'great_fever', $defender, $isInvasionSpell);
            }
        }

        if($defender->race->name == 'Afflicted')
        {
            # Festering Wounds
            $this->spellActionService->castSpell($defender, 'festering_wounds', $attacker, $isInvasionSpell);
        }

        if($attacker->race->name == 'Legion' and $defender->race->name == 'Barbarian' and $this->invasionResult['result']['success'])
        {
            $this->spellActionService->castSpell($attacker, 'annexation', $defender, $isInvasionSpell);
            $this->invasionResult['result']['annexation'] = true;
        }
    }

    protected function handleResourceConversions(Dominion $attacker, Dominion $defender, float $landRatio): void
    {
        $resourceConversionTemplate = [
            'resource_food' => 0,
            'resource_blood' => 0,
            'resource_soul' => 0,
            'resource_mana' => 0,
            'resource_champion' => 0,
            'resource_mana' => 0,
            'resource_lumber' => 0,
        ];

        $this->invasionResult['attacker']['resource_conversion'] = $resourceConversionTemplate;
        $this->invasionResult['defender']['resource_conversion'] = $resourceConversionTemplate;

        $rawOp = 0;
        foreach($this->invasionResult['attacker']['unitsSent'] as $slot => $amount)
        {
            $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                return ($unit->slot === $slot);
            })->first();

            $rawOp += $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense') * $amount;
        }

        $rawDp = 0;
        foreach($this->invasionResult['defender']['unitsDefending'] as $slot => $amount)
        {
            if($slot !== 'draftees' and $slot !== 'peasants')
            {
                $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();
            }

            $rawDp += $this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense') * $amount;
        }

        $dpFromLostDefendingUnits = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, $this->invasionResult['defender']['unitsLost'], 0, false, $this->isAmbush, true);
        $opFromLostAttackingUnits = $this->militaryCalculator->getOffensivePowerRaw($attacker, $defender, $landRatio, $this->invasionResult['attacker']['unitsSent']);


        foreach($this->invasionResult['attacker']['unitsSent'] as $slot => $amount)
        {
            # Attacker: kills_into_resource_per_casualty SINGLE RESOURCE
            if($killsIntoResourcePerCasualty = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resource_per_casualty'))
            {
                $amountPerCasualty = $killsIntoResourcePerCasualty[0] * (1 + $defender->race->getPerkMultiplier('reduced_conversions'));
                $resource = 'resource_' . $killsIntoResourcePerCasualty[1];

                $opFromSlot = $this->militaryCalculator->getOffensivePowerRaw($attacker, $defender, $landRatio, [$slot => $amount]);

                foreach($this->invasionResult['defender']['unitsLost'] as $slotKilled => $amountKilled)
                {
                    if($this->unitHelper->unitSlotHasAttributes($defender->race, $slotKilled, ['living']))
                    {
                          $killsAttributableToThisSlot = $amountKilled * ($opFromSlot / $rawOp);
                          #$this->queueService->queueResources('invasion',$attacker,[$resource => round($killsAttributableToThisSlot * $amountPerCasualty)]);
                          $this->invasionResult['attacker']['resource_conversion'][$resource] += round($killsAttributableToThisSlot * $amountPerCasualty);
                    }
                }
            }

            # Attacker: kills_into_resource_per_casualty_on_success SINGLE RESOURCE, ON SUCCESS
            if($killsIntoResourcePerCasualty = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resource_per_casualty_on_success') and $this->invasionResult['result']['success'])
            {
                $amountPerCasualty = $killsIntoResourcePerCasualty[0] * (1 + $defender->race->getPerkMultiplier('reduced_conversions'));
                $resource = 'resource_' . $killsIntoResourcePerCasualty[1];

                $opFromSlot = $this->militaryCalculator->getOffensivePowerRaw($attacker, $defender, $landRatio, [$slot => $amount]);

                foreach($this->invasionResult['defender']['unitsLost'] as $slotKilled => $amountKilled)
                {
                    if($this->unitHelper->unitSlotHasAttributes($defender->race, $slotKilled, ['living']))
                    {
                          $killsAttributableToThisSlot = $amountKilled * ($opFromSlot / $rawOp);
                          #$this->queueService->queueResources('invasion',$attacker,[$resource => round($killsAttributableToThisSlot * $amountPerCasualty)]);
                          $this->invasionResult['attacker']['resource_conversion'][$resource] += round($killsAttributableToThisSlot * $amountPerCasualty);
                    }
                }
            }

            # Attacker: kills_into_resources_per_casualty MULTIPLE RESOURCES
            if($killsIntoResourcesPerCasualty = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resources_per_casualty'))
            {
                foreach($killsIntoResourcesPerCasualty as $killsIntoResourcesPerCasualtyPerk)
                {
                    $amountPerCasualty = $killsIntoResourcesPerCasualtyPerk[0] * (1 + $defender->race->getPerkMultiplier('reduced_conversions'));
                    $resource = 'resource_' . $killsIntoResourcesPerCasualtyPerk[1];

                    $opFromSlot = $this->militaryCalculator->getOffensivePowerRaw($attacker, $defender, $landRatio, [$slot => $amount]);

                    foreach($this->invasionResult['defender']['unitsLost'] as $slotKilled => $amountKilled)
                    {
                        if($this->unitHelper->unitSlotHasAttributes($defender->race, $slotKilled, ['living']))
                        {
                              $killsAttributableToThisSlot = $amountKilled * ($opFromSlot / $rawOp);
                              #$this->queueService->queueResources('invasion',$attacker,[$resource => round($killsAttributableToThisSlot * $amountPerCasualty)]);
                              $this->invasionResult['attacker']['resource_conversion'][$resource] += round($killsAttributableToThisSlot * $amountPerCasualty);
                        }
                    }
                }
            }

            # Attacker: kills_into_resource_per_value SINGLE RESOURCE
            if($killsIntoResourcePerCasualty = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resource_per_value'))
            {
                $amountPerPoint = $killsIntoResourcePerCasualty[0] * (1 + $defender->race->getPerkMultiplier('reduced_conversions'));
                $resource = 'resource_' . $killsIntoResourcePerCasualty[1];

                $opFromSlot = $this->militaryCalculator->getOffensivePowerRaw($attacker, $defender, $landRatio, [$slot => $amount]);

                foreach($this->invasionResult['defender']['unitsLost'] as $slotKilled => $amountKilled)
                {
                    if($this->unitHelper->unitSlotHasAttributes($defender->race, $slotKilled, ['living']))
                    {
                          $killsAttributableToThisSlot = $dpFromLostDefendingUnits * ($opFromSlot / $rawOp);
                          #$this->queueService->queueResources('invasion', $attacker, [$resource => round($killsAttributableToThisSlot / $amountPerPoint)]);
                          $this->invasionResult['attacker']['resource_conversion'][$resource] += round($killsAttributableToThisSlot * $amountPerPoint);
                    }
                }

            }

            # Attacker: kills_into_resources_per_value MULTIPLE RESOURCES
            if($killsIntoResourcesPerCasualty = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resources_per_value'))
            {
                foreach($killsIntoResourcesPerCasualty as $killsIntoResourcesPerCasualtyPerk)
                {
                    $amountPerPoint = $killsIntoResourcesPerCasualtyPerk[0] * (1 + $defender->race->getPerkMultiplier('reduced_conversions'));
                    $resource = 'resource_' . $killsIntoResourcesPerCasualtyPerk[1];

                    $opFromSlot = $this->militaryCalculator->getOffensivePowerRaw($attacker, $defender, $landRatio, [$slot => $amount]);

                    foreach($this->invasionResult['defender']['unitsLost'] as $slotKilled => $amountKilled)
                    {
                        if($this->unitHelper->unitSlotHasAttributes($defender->race, $slotKilled, ['living']))
                        {
                              $killsAttributableToThisSlot = $dpFromLostDefendingUnits * ($opFromSlot / $rawOp);
                              $resourceAmount = round($killsAttributableToThisSlot * $amountPerPoint);
                              $resourceAmount *= (1 - $defender->race->getPerkMultiplier('reduced_conversions'));
                              $this->invasionResult['attacker']['resource_conversion'][$resource] += $resourceAmount;

                              #echo "<pre>$x >> $slot $resource ($dpFromLostDefendingUnits * $opFromSlot/$rawOp = $killsAttributableToThisSlot): {$this->invasionResult['attacker']['resource_conversion'][$resource]}</pre>";
                        }
                    }
                }
            }

            # Attacker: dies_into_resource
            if($diesIntoResourcePerCasualty = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_resource'))
            {
                $amountPerCasualty = $diesIntoResourcePerCasualty[0];
                $resource = 'resource_' . $diesIntoResourcePerCasualty[1];
                $this->invasionResult['attacker']['resource_conversion'][$resource] += floor($this->invasionResult['attacker']['unitsLost'][$slot] * $amountPerCasualty);
            }

            # Attacker: dies_into_resource_on_success: triggers if an offensive unit has the perk and invasion is successful
            if($diesIntoResourcePerCasualty = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_resource_on_success') and $this->invasionResult['result']['success'])
            {
                $amountPerCasualty = $diesIntoResourcePerCasualty[0];
                $resource = 'resource_' . $diesIntoResourcePerCasualty[1];
                $this->invasionResult['attacker']['resource_conversion'][$resource] += floor($this->invasionResult['attacker']['unitsLost'][$slot] * $amountPerCasualty);
            }
        }

        #
        foreach($this->invasionResult['defender']['unitsDefending'] as $slot => $amount)
        {
            if($slot !== 'draftees' and $slot !== 'peasants')
            {
                 # Defender: kills_into_resource_per_casualty SINGLE RESOURCE
                if($killsIntoResourcesPerCasualty = $defender->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resource_per_casualty'))
                {
                    $amountPerCasualty = $killsIntoResourcesPerCasualty[0] * (1 + $attacker->race->getPerkMultiplier('reduced_conversions'));
                    $resource = 'resource_' . $killsIntoResourcesPerCasualty[1];

                    $dpFromSlot = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, [$slot => $amount]);

                    foreach($this->invasionResult['attacker']['unitsLost'] as $slotKilled => $amountKilled)
                    {
                        if($this->unitHelper->unitSlotHasAttributes($attacker->race, $slotKilled, ['living']))
                        {
                              $killsAttributableToThisSlot = $amountKilled * ($dpFromSlot / $rawDp);
                              #$this->queueService->queueResources('invasion', $defender, [$resource => round($killsAttributableToThisSlot * $amountPerCasualty)]);
                              $this->invasionResult['defender']['resource_conversion'][$resource] += round($killsAttributableToThisSlot * $amountPerCasualty);
                        }
                    }
                }
                     # Defender: kills_into_resource_per_casualty_on_success SINGLE RESOURCE
                    if($killsIntoResourcesPerCasualty = $defender->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resource_per_casualty_on_success') and !$this->invasionResult['result']['success'])
                    {
                        $amountPerCasualty = $killsIntoResourcesPerCasualty[0] * (1 + $attacker->race->getPerkMultiplier('reduced_conversions'));
                        $resource = 'resource_' . $killsIntoResourcesPerCasualty[1];

                        $dpFromSlot = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, [$slot => $amount]);

                        foreach($this->invasionResult['attacker']['unitsLost'] as $slotKilled => $amountKilled)
                        {
                            if($this->unitHelper->unitSlotHasAttributes($attacker->race, $slotKilled, ['living']))
                            {
                                  $killsAttributableToThisSlot = $amountKilled * ($dpFromSlot / $rawDp);
                                  #$this->queueService->queueResources('invasion', $defender, [$resource => round($killsAttributableToThisSlot * $amountPerCasualty)]);
                                  $this->invasionResult['defender']['resource_conversion'][$resource] += round($killsAttributableToThisSlot * $amountPerCasualty);
                            }
                        }
                    }

                # Defender: kills_into_resources_per_casualty MULTIPLE RESOURCES
                if($killsIntoResourcesPerCasualty = $defender->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resources_per_casualty'))
                {
                    foreach($killsIntoResourcesPerCasualty as $killsIntoResourcesPerCasualtyPerk)
                    {
                        $amountPerCasualty = $killsIntoResourcesPerCasualtyPerk[0] * (1 + $attacker->race->getPerkMultiplier('reduced_conversions'));
                        $resource = 'resource_' . $killsIntoResourcesPerCasualtyPerk[1];

                        $dpFromSlot = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, [$slot => $amount]);

                        foreach($this->invasionResult['attacker']['unitsLost'] as $slotKilled => $amountKilled)
                        {
                            if($this->unitHelper->unitSlotHasAttributes($attacker->race, $slotKilled, ['living']))
                            {
                                  $killsAttributableToThisSlot = $amountKilled * ($dpFromSlot / $rawDp);
                                  #$this->queueService->queueResources('invasion', $defender, [$resource => round($killsAttributableToThisSlot * $amountPerCasualty)]);
                                  $this->invasionResult['defender']['resource_conversion'][$resource] += round($killsAttributableToThisSlot * $amountPerCasualty);
                            }
                        }
                    }
                }

                # Defender: kills_into_resource_per_value SINGLE RESOURCE
                if($killsIntoResourcePerCasualty = $defender->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resource_per_value'))
                {
                    $amountPerPoint = $killsIntoResourcePerCasualty[0] * (1 + $attacker->race->getPerkMultiplier('reduced_conversions'));
                    $resource = 'resource_' . $killsIntoResourcePerCasualty[1];

                    $dpFromSlot = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, [$slot => $amount]);

                    foreach($this->invasionResult['attacker']['unitsLost'] as $slotKilled => $amountKilled)
                    {
                        if($this->unitHelper->unitSlotHasAttributes($attacker->race, $slotKilled, ['living']))
                        {
                              $killsAttributableToThisSlot = $opFromLostAttackingUnits * ($dpFromSlot / $rawDp);
                              #$this->queueService->queueResources('invasion', $defender, [$resource => round($killsAttributableToThisSlot / $amountPerPoint)]);
                              $this->invasionResult['defender']['resource_conversion'][$resource] += round($killsAttributableToThisSlot / $amountPerPoint);
                        }
                    }
                }

                # Defender: kills_into_resources_per_value MULTIPLE RESOURCES
                if($killsIntoResourcesPerCasualty = $defender->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resources_per_value'))
                {
                    foreach($killsIntoResourcesPerCasualty as $killsIntoResourcesPerValuePerk)
                    {
                        $amountPerPoint = $killsIntoResourcesPerValuePerk[0] * (1 + $attacker->race->getPerkMultiplier('reduced_conversions'));
                        $resource = 'resource_' . $killsIntoResourcesPerValuePerk[1];

                        $dpFromSlot = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, [$slot => $amount]);

                        foreach($this->invasionResult['attacker']['unitsLost'] as $slotKilled => $amountKilled)
                        {
                            if($this->unitHelper->unitSlotHasAttributes($attacker->race, $slotKilled, ['living']))
                            {
                                  $killsAttributableToThisSlot = $opFromLostAttackingUnits * ($dpFromSlot / $rawDp);
                                  #$this->queueService->queueResources('invasion', $defender, [$resource => round($killsAttributableToThisSlot / $amountPerPoint)]);
                                  $this->invasionResult['defender']['resource_conversion'][$resource] += round($killsAttributableToThisSlot / $amountPerPoint);
                            }
                        }
                    }
                }

                # Defender: dies_into_resource
                if(isset($this->invasionResult['defender']['unitsLost'][$slot]) and $diesIntoResourcePerCasualty = $defender->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_resource'))
                {
                    $amountPerCasualty = $diesIntoResourcePerCasualty[0];
                    $resource = 'resource_' . $diesIntoResourcePerCasualty[1];
                    $this->invasionResult['defender']['resource_conversion'][$resource] += floor($this->invasionResult['defender']['unitsLost'][$slot] * $amountPerCasualty);
                }

                # Defender: dies_into_resource_on_success: triggers if a defensive unit has the perk and invasion is NOT successful
                if(isset($this->invasionResult['defender']['unitsLost'][$slot]) and $diesIntoResourcePerCasualtyOnSuccess = $defender->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_resource_on_success') and !$this->invasionResult['result']['success'])
                {
                    $amountPerCasualty = $diesIntoResourcePerCasualtyOnSuccess[0];
                    $resource = 'resource_' . $diesIntoResourcePerCasualtyOnSuccess[1];
                    $this->invasionResult['defender']['resource_conversion'][$resource] += floor($this->invasionResult['defender']['unitsLost'][$slot] * $amountPerCasualty);
                }
            }
        }

        # Add/Queue the sources
        foreach($this->invasionResult['attacker']['resource_conversion'] as $resourceType => $amount)
        {
            if($amount > 0)
            {
                $this->queueService->queueResources('invasion', $attacker, [$resourceType => $amount]);
            }
        }

        foreach($this->invasionResult['defender']['resource_conversion'] as $resourceType => $amount)
        {
            if($amount > 0)
            {
                $this->queueService->queueResources('invasion', $defender, [$resourceType => $amount]);
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

        $result['attacker']['plunder']['gold'] = 0;
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
                if($slot !== 'draftees' and $slot !== 'peasants')
                {
                    $unitType = 'unit'.$slot;
                    $unitOreCost = $unitCosts[$unitType]['ore'];
                    $unitLumberCost = $unitCosts[$unitType]['lumber'];
                    $unitGemCost = $unitCosts[$unitType]['gem'];

                    $result['defender']['salvage']['ore'] += $amountLost * $unitOreCost * $salvaging;
                    $result['defender']['salvage']['lumber'] += $amountLost * $unitLumberCost * $salvaging;
                    $result['defender']['salvage']['gems'] += $amountLost * $unitGemCost * $salvaging;

                    # Update statistics
                    $this->statsService->updateStat($defender, 'ore_salvaged', $result['defender']['salvage']['ore']);
                    $this->statsService->updateStat($defender, 'lumber_salvaged', $result['defender']['salvage']['lumber']);
                    $this->statsService->updateStat($defender, 'gems_salvaged', $result['defender']['salvage']['gems']);
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
                $this->statsService->updateStat($attacker, 'ore_salvaged', $result['attacker']['salvage']['ore']);
                $this->statsService->updateStat($attacker, 'lumber_salvaged', $result['attacker']['salvage']['lumber']);
                $this->statsService->updateStat($attacker, 'gems_salvaged', $result['attacker']['salvage']['gems']);
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

                    if($resourceToPlunder === 'gem')
                    {
                        $resourceToPlunder = 'gems';
                    }

                    $amountToPlunder = intval(min($this->resourceCalculator->getAmount($defender, $resourceToPlunder), $amount * $amountPlunderedPerUnit));
                    $result['attacker']['plunder'][$resourceToPlunder] += $amountToPlunder;
                }
            }

            if($plunderPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot,'plunder'))
            {
                $resourceToPlunder = $plunderPerk[0];
                $amountPlunderedPerUnit = $plunderPerk[1];

                if($resourceToPlunder === 'gem')
                {
                    $resourceToPlunder = 'gems';
                }

                $amountToPlunder = intval(min($this->resourceCalculator->getAmount($defender, $resourceToPlunder), $amount * $amountPlunderedPerUnit));
                $result['attacker']['plunder'][$resourceToPlunder] += $amountToPlunder;
            }
        }

        # Remove plundered resources from defender.
        foreach($result['attacker']['plunder'] as $resource => $amount)
        {
            $result['attacker']['plunder'][$resource] = min($amount, $this->resourceCalculator->getAmount($defender, $resource));
            #$defender->{'resource_'.$resource} -= $result['attacker']['plunder'][$resource];
            $this->resourceService->updateResources($defender, [$resource => ($result['attacker']['plunder'][$resource] * -1)])

        }

        # Add salvaged resources to defender.
        foreach($result['defender']['salvage'] as $resource => $amount)
        {
            #$defender->{'resource_'.$resource} += $amount;
            $this->resourceService->updateResources($defender, [$resource => $amount]);
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

            $this->statsService->updateStat($attacker, ($resource . '_plundered'), $amount);

        }

        $this->invasionResult['attacker']['salvage'] = $result['attacker']['salvage'];
        $this->invasionResult['attacker']['plunder'] = $result['attacker']['plunder'];
        $this->invasionResult['defender']['salvage'] = $result['defender']['salvage'];
    }

    # Add casualties to the Imperial Crypt.
    protected function handleCrypt(Dominion $attacker, Dominion $defender, array $offensiveConversions, array $defensiveConversions): void
    {

        if($attacker->race->alignment === 'evil' or $defender->race->alignment === 'evil')
        {

            $cryptLogString = '';

            $this->invasionResult['defender']['crypt'] = [];
            $this->invasionResult['attacker']['crypt'] = [];

            # The battlefield:
            # Cap bodies by reduced_conversions perk, and round.
            $defensiveBodies = round(array_sum($this->invasionResult['defender']['unitsLost']) * (1 - $defender->race->getPerkMultiplier('reduced_conversions')));
            $offensiveBodies = round(array_sum($this->invasionResult['attacker']['unitsLost']) * (1 - $attacker->race->getPerkMultiplier('reduced_conversions')));

            $cryptLogString .= 'Defensive bodies (raw): ' . number_format($defensiveBodies) . ' | ';
            $cryptLogString .= 'Offensive bodies (raw): ' . number_format($offensiveBodies) . ' | ';

            $this->invasionResult['defender']['crypt']['bodies_available_raw'] = $defensiveBodies;
            $this->invasionResult['attacker']['crypt']['bodies_available_raw'] = $offensiveBodies;

            # Loop through defensive casualties and remove units that don't qualify.
            foreach($this->invasionResult['defender']['unitsLost'] as $slot => $lost)
            {
                $uncryptablePerks = [
                    'dies_into',
                    'dies_into_multiple',
                    'dies_into_resource',
                    'dies_into_resources',
                    'dies_into_multiple_on_defense',
                    'dies_into_on_defense',
                    'dies_into_on_defense_instantly'
                ];

                if($slot !== 'draftees' and $slot !== 'peasants')
                {
                    $isUnitConvertible = true;

                    $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                            return ($unit->slot == $slot);
                        })->first();

                    if(!$this->conversionCalculator->isSlotConvertible($slot, $defender) and !$defender->race->getUnitPerkValueForUnitSlot($slot, 'dies_into'))
                    {
                        $defensiveBodies -= $lost;
                    }
                }
            }

            # Loop through offensive casualties and remove units that don't qualify.
            foreach($this->invasionResult['attacker']['unitsLost'] as $slot => $lost)
            {
                $uncryptablePerks = [
                    'dies_into',
                    'dies_into_multiple',
                    'dies_into_resource',
                    'dies_into_resources',
                    'dies_into_multiple_on_offense',
                    'dies_into_on_offense',
                    'dies_into_multiple_on_victory'
                ];


                if($slot !== 'draftees')
                {
                    $isUnitConvertible = true;

                    $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                            return ($unit->slot == $slot);
                        })->first();

                    if(!$this->conversionCalculator->isSlotConvertible($slot, $attacker) or $attacker->race->getUnitPerkValueForUnitSlot($slot, 'dies_into'))
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

            $this->invasionResult['defender']['crypt']['bodies_available_net'] = $defensiveBodies;
            $this->invasionResult['attacker']['crypt']['bodies_available_net'] = $offensiveBodies;

            $cryptLogString .= 'Defensive bodies (net): ' . number_format($defensiveBodies) . ' | ';
            $cryptLogString .= 'Offensive bodies (net): ' . number_format($offensiveBodies) . ' | ';

            $toTheCrypt = 0;

            # If defender is empire
            if($defender->race->alignment === 'evil')
            {
                  $whoHasCrypt = 'defender';
                  # If the attack is successful
                  if($this->invasionResult['result']['success'])
                  {
                      # 50% of defensive and 0% of offensive bodies go to the crypt.
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
                      # 50% of defensive and 100% of offensive bodies go to the crypt.
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

            $cryptLogString .= 'Defensive bodies (final): ' . number_format($defensiveBodies) . ' | ';
            $cryptLogString .= 'Offensive bodies (final): ' . number_format($offensiveBodies) . ' | ';

            $this->invasionResult['defender']['crypt']['bodies_available_final'] = $defensiveBodies;
            $this->invasionResult['attacker']['crypt']['bodies_available_final'] = $offensiveBodies;

            $toTheCrypt = max(0, round($defensiveBodies + $offensiveBodies));

            if($whoHasCrypt == 'defender')
            {

                $this->invasionResult['defender']['crypt']['defensiveBodies'] = $defensiveBodies;
                $this->invasionResult['defender']['crypt']['offensiveBodies'] = $offensiveBodies;
                $this->invasionResult['defender']['crypt']['total'] = $toTheCrypt;

                $cryptLogString .= '* Bodies currently in crypt: ' . number_format($defender->realm->crypt) . ' | ';

                $defender->realm->fill([
                    'crypt' => ($defender->realm->crypt + $toTheCrypt),
                ])->save();

                $cryptLogString .= '* Bodies added to crypt: ' . number_format($toTheCrypt) . ' *';
            }
            elseif($whoHasCrypt == 'attacker')
            {
                $this->invasionResult['attacker']['crypt']['defensiveBodies'] = $defensiveBodies;
                $this->invasionResult['attacker']['crypt']['offensiveBodies'] = $offensiveBodies;
                $this->invasionResult['attacker']['crypt']['total'] = $toTheCrypt;

                $cryptLogString .= '* Bodies currently in crypt: ' . number_format($attacker->realm->crypt) . ' | ';

                $attacker->realm->fill([
                    'crypt' => ($attacker->realm->crypt + $toTheCrypt),
                ])->save();

                $cryptLogString .= '* Bodies added to crypt: ' . number_format($toTheCrypt) . ' *';
            }

            Log::info($cryptLogString);

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
        if ($this->spellCalculator->getPassiveSpellPerkValue($attacker, 'convert_enemy_casualties_to_food'))
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

                $food += $dpFromEatenUnits * 8;

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
          elseif ($this->spellCalculator->getPassiveSpellPerkValue($defender, 'convert_enemy_casualties_to_food'))
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

              $food += $opFromKilledEaten * 8;

              $this->invasionResult['attacker']['metabolism']['unitsEaten'] = $unitsEaten;
              $this->invasionResult['attacker']['metabolism']['opFromUnitsEaten'] = $opFromKilledEaten;
              $this->invasionResult['attacker']['metabolism']['food'] = $food;

              $this->resourceService->updateResources($defender, ['food' => $food]);
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
        $immortalsKilledPerZealot = 1.5;
        $soulsDestroyedPerZealot = 2;

        $unkillableAttributes = ['machine', 'ship', 'ammunition'];

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

                    if($unit->power_defense !== 0 and ($defender->race->getUnitPerkValueForUnitSlot($slot, 'immortal') or $defender->race->getUnitPerkValueForUnitSlot($slot, 'true_immortal')) and !$this->unitHelper->unitSlotHasAttributes($defender->race, $slot, $unkillableAttributes))
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

            $immortalsKilled = min($zealots * $immortalsKilledPerZealot, array_sum($immortalDefenders) * 0.03);

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

                          $this->statsService->updateStat($attacker, 'units_killed', $deaths);
                          $this->statsService->updateStat($defender, ('unit' . $slot . '_lost'), $deaths);

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
            if($defenderSouls = $this->resourceCalculator->getAmount($defender, 'soul'))
            {
                $soulsDestroyed = (int)floor(min($defenderSouls * 0.04, ($zealots * $soulsDestroyedPerZealot)));

                $this->invasionResult['attacker']['soulsDestroyed'] = $soulsDestroyed;
                $this->resourceService->updateResources($defender, ['souls' => ($soulsDestroyed*-1)])
                $this->statsService->updateStat($attacker, 'soul_destroyed', $soulsDestroyed);

            }
        }
        elseif($defender->race->name === 'Qur')
        {
              # See if attacker has any immortal units
              foreach($this->invasionResult['attacker']['unitsSent'] as $slot => $amount)
              {
                  $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                      return ($unit->slot == $slot);
                  })->first();

                  if($attacker->race->getUnitPerkValueForUnitSlot($slot, 'immortal') or $attacker->race->getUnitPerkValueForUnitSlot($slot, 'true_immortal') and !$this->unitHelper->unitSlotHasAttributes($attacker->race, $slot, $unkillableAttributes))
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

              $immortalsKilled = min($zealots * $immortalsKilledPerZealot, array_sum($immortalAttackers) * 0.03);

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

                            $this->statsService->updateStat($attacker, ('unit' . $slot . '_lost'), $deaths);
                            $this->statsService->updateStat($defender, 'units_killed', $deaths);

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
              if($attackerSouls = $this->resourceCalculator->getAmount($attacker, 'soul'))
              {
                  $soulsDestroyed = (int)floor(min($attackerSouls * 0.04, ($zealots * $soulsDestroyedPerZealot)));

                  $this->invasionResult['defender']['soulsDestroyed'] = $soulsDestroyed;

                  $this->resourceService->updateResources($attacker, ['soul' => ($soulsDestroyed*-1)])
                  $this->statsService->updateStat($defender, 'soul_destroyed', $soulsDestroyed);
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

        if($this->militaryCalculator->getRawDefenseAmbushReductionRatio($attacker) > 0)
        {
            $this->isAmbush = true;
        }

        $this->invasionResult['attacker']['ambush'] = $this->isAmbush;
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
        $this->invasionResult['defender']['mindControlledUnits'] = array_fill(1,4,0);
        if($cult->race->name === 'Cult' and $cult->getSpellPerkValue('mind_control'))
        {
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

                $isUnitControllable = false;
                if($this->unitHelper->unitSlotHasAttributes($attacker->race, $slot, ['sentient']) and !$this->unitHelper->unitSlotHasAttributes($attacker->race, $slot, $nonControllableAttributes))
                {
                    $isUnitControllable = true;
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
        $this->invasionResult['result']['opDpRatio'] = $attackingForceOP / $targetDP;

        $this->statsService->setStat($dominion, 'op_sent_max', max($this->invasionResult['attacker']['op'], $this->statsService->getStat($dominion, 'op_sent_max')));
        $this->statsService->updateStat($dominion, 'op_sent_total', $this->invasionResult['attacker']['op']);

        if(request()->getHost() === 'odarena.com')
        {
            $day = $dominion->round->start_date->subDays(1)->diffInDays(now());
            $day = sprintf('%02d', $day);
            $this->statsService->setRoundStat($dominion->round, ('day' . $day . '_top_op'), max($this->invasionResult['attacker']['op'], $this->statsService->getRoundStat($dominion->round, ('day' . $day . '_top_op'))));
        }


        if($this->invasionResult['result']['success'])
        {
            $this->statsService->setStat($target, 'dp_failure_max', max($this->invasionResult['defender']['dp'], $this->statsService->getStat($dominion, 'dp_failure_max')));
        }
        else
        {
            $this->statsService->setStat($target, 'dp_success_max', max($this->invasionResult['defender']['dp'], $this->statsService->getStat($dominion, 'dp_success_max')));
        }
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
    *   0) Add OP from annexed dominions (already done when calculating attacker's OP)
    *   1) Remove OP units from annexed dominions.
    *   2) Incur 10% casualties on annexed units.
    *   3) Queue returning units.
    *   4) Save data to $this->invasionResult to create pretty battle report
    */
    protected function handleAnnexedDominions(Dominion $attacker, Dominion $defender, array $units): void
    {

        $casualties = 0.10; # / because we want to invert the ratio

        $legion = null;
        if($this->spellCalculator->hasAnnexedDominions($attacker))
        {
            $legion = $attacker;
            $legionString = 'attacker';
            $casualties /= $this->invasionResult['result']['opDpRatio'];
        }
        elseif($this->spellCalculator->hasAnnexedDominions($defender))
        {
            $legion = $defender;
            $legionString = 'defender';
            $casualties *= $this->invasionResult['result']['opDpRatio'];

            if($this->invasionResult['result']['overwhelmed'])
            {
                $casualties = 0;
            }
        }

        if($defender->race->getPerkValue('does_not_kill'))
        {
            $casualties = 0;
        }

        $casualties = min(max(0, $casualties), 0.20);

        if($legion and $this->invasionResult['result']['opDpRatio'] >= 0.85)
        {
            $this->invasionResult[$legionString]['annexation'] = [];
            $this->invasionResult[$legionString]['annexation']['hasAnnexedDominions'] = count($this->spellCalculator->getAnnexedDominions($legion));
            $this->invasionResult[$legionString]['annexation']['annexedDominions'] = [];

            foreach($this->spellCalculator->getAnnexedDominions($legion) as $annexedDominion)
            {
                $this->invasionResult[$legionString]['annexation']['annexedDominions'][$annexedDominion->id] = [];
                $this->invasionResult[$legionString]['annexation']['annexedDominions'][$annexedDominion->id]['unitsSent'] = [1 => $annexedDominion->military_unit1, 2 => 0, 3 => 0, 4 => $annexedDominion->military_unit4];

                # If there are troops to send and if defender is not a Barbarian
                if(array_sum($this->invasionResult[$legionString]['annexation']['annexedDominions'][$annexedDominion->id]['unitsSent']) > 0 and $defender->race->name !== 'Barbarian')
                {
                    # Incur casualties
                    $this->invasionResult[$legionString]['annexation']['annexedDominions'][$annexedDominion->id]['unitsLost'] =      [1 => (int)round($annexedDominion->military_unit1 * $casualties), 2 => 0, 3 => 0, 4 => (int)round($annexedDominion->military_unit4 * $casualties)];
                    $this->invasionResult[$legionString]['annexation']['annexedDominions'][$annexedDominion->id]['unitsReturning'] = [1 => (int)round($annexedDominion->military_unit1 * (1 - $casualties)), 2 => 0, 3 => 0, 4 => (int)round($annexedDominion->military_unit4 * (1 - $casualties))];

                    # Remove the units
                    $annexedDominion->military_unit1 -= $annexedDominion->military_unit1;
                    $annexedDominion->military_unit4 -= $annexedDominion->military_unit4;

                    # Queue the units
                    foreach($this->invasionResult[$legionString]['annexation']['annexedDominions'][$annexedDominion->id]['unitsReturning'] as $slot => $returning)
                    {
                        $unitType = 'military_unit' . $slot;

                        $this->queueService->queueResources(
                            'invasion',
                            $annexedDominion,
                            [$unitType => $returning],
                            12
                        );
                    }

                    $annexedDominion->save();
                }
            }
        }
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
    protected function allUnitsHaveOP(Dominion $dominion, array $units, Dominion $target, float $landRatio): bool
    {
        foreach ($dominion->race->units as $unit)
        {
            if (!isset($units[$unit->slot]) || ((int)$units[$unit->slot] === 0))
            {
                continue;
            }

            if ($this->militaryCalculator->getUnitPowerWithPerks($dominion, $target, $landRatio, $unit, 'offense', null, $units, $this->invasionResult['defender']['unitsDefending']) === 0.0 and $unit->getPerkValue('sendable_with_zero_op') != 1)
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
        foreach ($dominion->race->units as $unit)
        {
            if (!isset($units[$unit->slot]) || ((int)$units[$unit->slot] === 0))
            {
                continue;
            }

            if ($units[$unit->slot] > $dominion->{'military_unit' . $unit->slot})
            {
                return false;
            }
        }

        return true;
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
        $newHomeForcesDP = $this->militaryCalculator->getDefensivePower($dominion, null, null, $unitsHome, 0, false, false, false, null, null, true); # The "true" at the end excludes raw DP from annexed dominions

        $attackingForceMaxOP = (int)ceil($newHomeForcesDP * (4/3));

        return ($attackingForceOP <= $attackingForceMaxOP);
    }

    /**
     * Check if an invasion passes the 4:3-rule.
     *
     * @param Dominion $dominion
     * @param array $units
     * @return bool
     */
    protected function passesMinimumDpaCheck(Dominion $dominion, Dominion $target, float $landRatio, array $units): bool
    {
        $attackingForceOP = $this->militaryCalculator->getOffensivePower($dominion, $target, $landRatio, $units);

        return ($attackingForceOP > $this->landCalculator->getTotalLand($target) * static::MINIMUM_DPA);
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
        $ticks = 12;

        $unit = $dominion->race->units->filter(function ($unit) use ($slot) {
            return ($unit->slot === $slot);
        })->first();

        if ($unit->getPerkValue('faster_return'))
        {
            $ticks -= (int)$unit->getPerkValue('faster_return');
        }

        return $ticks;
    }

    protected function getUnitReturnTicksForSlot(Dominion $dominion, int $slot): int
    {
        $ticks = 12;

        $unit = $dominion->race->units->filter(function ($unit) use ($slot) {
            return ($unit->slot === $slot);
        })->first();

        $ticks -= (int)$unit->getPerkValue('faster_return');
        $ticks -= (int)$dominion->getSpellPerkValue('faster_return');
        $ticks -= (int)$dominion->getTechPerkValue('faster_return');

        return min(max(1, $ticks), 12);
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
        $dpReductionPerTemple = 1.8;
        $templeMaxDpReduction = 36;
        $ignoreDraftees = false;

        $dpMultiplierReduction = 0;
        $dpMultiplierReduction += $attacker->getBuildingPerkMultiplier('defensive_modifier_reduction');
        $dpMultiplierReduction += $attacker->getSpellPerkMultiplier('defensive_modifier_reduction');
        $dpMultiplierReduction += $attacker->getImprovementPerkMultiplier('defensive_modifier_reduction');
        $dpMultiplierReduction += $attacker->getDeityPerkMultiplier('defensive_modifier_reduction');

        // Void: Spell (remove DP reduction from Temples)
        if ($target->getSpellPerkValue('immune_to_temples'))
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
