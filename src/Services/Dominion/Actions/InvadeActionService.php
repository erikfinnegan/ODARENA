<?php

namespace OpenDominion\Services\Dominion\Actions;

use DB;
use Log;
use OpenDominion\Exceptions\GameException;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\Building;
use OpenDominion\Models\GameEvent;
use OpenDominion\Models\Improvement;
use OpenDominion\Models\Resource;
use OpenDominion\Models\Spell;
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
     * @var int The minimum morale required to initiate an invasion
     */
    protected const MIN_MORALE = 50;

    /**
     * @var float Failing an invasion by this percentage (or more) results in 'being overwhelmed'
     */
    protected const OVERWHELMED_PERCENTAGE = 15.0;

    /**
     * @var float Percentage of units to be stunned
     */
    protected const STUN_RATIO = 1;

    /**
     * @var float Lowest possible DPA.
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

        $now = time();

        DB::transaction(function () use ($dominion, $target, $units, $now) {
            // Checks

            if ($this->protectionService->isUnderProtection($dominion))
            {
                throw new GameException('You cannot invade while under protection.');
            }

            if ($this->protectionService->isUnderProtection($target))
            {
                throw new GameException('You cannot invade dominions which are under protection.');
            }

            if (!$this->rangeCalculator->isInRange($dominion, $target))
            {
                throw new GameException('You cannot invade dominions outside of your range.');
            }

            if ($dominion->round->id !== $target->round->id)
            {
                throw new GameException('Nice try, but you cannot invade cross-round.');
            }

            if ($dominion->realm->id === $target->realm->id and ($dominion->round->mode == 'standard' or $dominion->round->mode == 'standard-duration'))
            {
                throw new GameException('You can only invade other dominions in the same realm in deathmatch rounds.');
            }

            if ($dominion->id == $target->id)
            {
                throw new GameException('Nice try, but you cannot invade yourself.');
            }

            foreach($dominion->race->resources as $resourceKey)
            {
                if($resourceCostToInvade = $dominion->race->getPerkValue($resourceKey . '_to_invade'))
                {
                    if($this->resourceCalculator->getAmount($dominion, $resourceKey) < $resourceCostToInvade)
                    {
                        $resource = Resource::where('key', $resourceKey)->first();
                        throw new GameException('You do not have enough ' . str_plural($resource->name, $resourceCostToInvade) . ' to invade. You have ' . number_format($this->resourceCalculator->getAmount($dominion, $resourceKey)) . ' and you need at least ' . number_format($resourceCostToInvade) . '.');
                    }
                    else
                    {
                        $this->resourceService->updateResources($dominion, [$resourceKey => $resourceCostToInvade*-1]);
                    }
                }
            }

            // Sanitize input
            $units = array_map('intval', array_filter($units));
            $landRatio = $this->rangeCalculator->getDominionRange($dominion, $target);
            $this->invasionResult['land_ratio'] = $landRatio;
            $landRatio /= 100;

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

                  $this->invasionResult['defender']['unitsDefending']['draftees'] = $target->military_draftees;
            }

            if (!$this->hasAnyOP($dominion, $units))
            {
                throw new GameException('You need to send at least some units.');
            }

            if (!$this->allUnitsHaveOP($dominion, $units, $target, $landRatio))
            {
                throw new GameException('You cannot send units that have no offensive power.');
            }

            if (!$this->hasEnoughUnitsAtHome($dominion, $units))
            {
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

            foreach($units as $slot => $amount)
            {

                $unit = $dominion->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                if(!$this->unitHelper->isUnitSendableByDominion($unit, $dominion))
                {
                    throw new GameException('You cannot send ' . $unit->name . ' on invasion.');
                }

                if($amount < 0)
                {
                    throw new GameException('Invasion was canceled due to an invalid amount of ' . str_plural($unit->name, $amount) . '.');
                }


                # OK, unit can be trained. Let's check for pairing limits.
                if($this->unitHelper->unitHasCapacityLimit($dominion, $slot) and !$this->unitHelper->checkUnitLimitForInvasion($dominion, $slot, $amount))
                {

                    throw new GameException('You can at most control ' . number_format($this->unitHelper->getUnitMaxCapacity($dominion, $slot)) . ' ' . str_plural($unit->name) . '. To control more, you need to first have more of their superior unit.');
                }

                # Check for spends_resource_on_offense
                if($spendsResourcesOnOffensePerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'spends_resource_on_offense'))
                {
                    $resourceKey = (string)$spendsResourcesOnOffensePerk[0];
                    $resourceAmount = (float)$spendsResourcesOnOffensePerk[1];
                    $resource = Resource::where('key', $resourceKey)->firstOrFail();

                    $resourceAmountRequired = ceil($resourceAmount * $amount);
                    $resourceAmountOwned = $this->resourceCalculator->getAmount($resourceKey, $dominion);

                    if($resourceAmountRequired > $resourceAmountOwned)
                    {
                        throw new GameException('You do not have enough ' . $resource->name . ' to attack to send this many ' . str_plural($unit->name, $amount) . '. You need ' . number_format($resourceAmountRequired) . ' but only have ' . number_format($resourceAmountOwned) . '.');
                    }
                }
             }

            if ($dominion->race->getPerkValue('cannot_invade'))
            {
                throw new GameException($dominion->race->name . ' cannot invade other dominions.');
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

            // Cannot invade until round has started.
            if(!$dominion->round->hasStarted())
            {
                throw new GameException('You cannot invade until the round has started.');
            }

            // Cannot invade after round has ended.
            if($dominion->round->hasEnded())
            {
                throw new GameException('You cannot invade after the round has ended.');
            }

            // Qur: Statis cannot be invaded.
            if($target->getSpellPerkValue('stasis'))
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

            $this->invasionResult['attacker']['fog'] = $dominion->getSpellPerkValue('fog_of_war') ? true : false;
            $this->invasionResult['defender']['fog'] = $target->getSpellPerkValue('fog_of_war') ? true : false;

            $this->invasionResult['log']['initiated_at'] = $now;
            $this->invasionResult['log']['requested_at'] = $_SERVER['REQUEST_TIME'];

            // Handle pre-invasion
            $this->handleBeforeInvasionPerks($dominion);

            // Handle invasion results
            $this->checkInvasionSuccess($dominion, $target, $units);
            $this->checkOverwhelmed();

            $attackerCasualties = $this->casualtiesCalculator->getInvasionCasualties($dominion, $this->invasionResult['attacker']['unitsSent'], $target, $this->invasionResult, 'offense');
            $defenderCasualties = $this->casualtiesCalculator->getInvasionCasualties($target, $this->invasionResult['defender']['unitsDefending'], $dominion, $this->invasionResult, 'defense');

            $this->invasionResult['attacker']['unitsLost'] = $attackerCasualties;
            $this->invasionResult['defender']['unitsLost'] = $defenderCasualties;

            $this->handleCasualties($dominion, $target, $this->invasionResult['attacker']['unitsLost'], 'offense');
            $this->handleCasualties($target, $dominion, $this->invasionResult['defender']['unitsLost'], 'defense');
            $this->handleDefensiveDiesIntoPerks($target);

            $this->handleAnnexedDominions($dominion, $target, $units);

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

            $this->handleMoraleChanges($dominion, $target, $landRatio, $units);
            $this->handleLandGrabs($dominion, $target, $landRatio, $units);
            $this->handleResearchPoints($dominion, $target, $units);

            # Dwarg
            $this->handleStun($dominion, $target, $units, $landRatio);

            # Demon
            $this->handlePeasantCapture($dominion, $target, $units, $landRatio);

            # Demon
            $this->handlePeasantKilling($dominion, $target, $units, $landRatio);

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

            $this->handleReturningUnits($dominion, $this->invasionResult['attacker']['survivingUnits'], $offensiveConversions, $defensiveConversions);
            $this->handleDefensiveConversions($target, $defensiveConversions);

            # Afflicted
            $this->handleInvasionSpells($dominion, $target);

            # Handle dies_into_resource, dies_into_resources, kills_into_resource, kills_into_resources
            $this->handleResourceConversions($dominion, $target, $landRatio);

            # Salvage and Plunder
            $this->handleSalvagingAndPlundering($dominion, $target);

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
                                'tick' => $annexedDominion->round->ticks
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

            $this->invasionResult['log']['finished_at'] = time();

            $this->invasionEvent = GameEvent::create([
                'round_id' => $dominion->round_id,
                'source_type' => Dominion::class,
                'source_id' => $dominion->id,
                'target_type' => Dominion::class,
                'target_id' => $target->id,
                'type' => 'invasion',
                'data' => $this->invasionResult,
                'tick' => $dominion->round->ticks
            ]);

            // todo: move to its own method
            // Notification
            if ($this->invasionResult['result']['success']) {
                $this->notificationService->queueNotification('received_invasion', [
                    '_routeParams' => [(string)$this->invasionEvent->id],
                    'attackerDominionId' => $dominion->id,
                    'landLost' => $this->landLost,
                    'unitsLost' => $this->invasionResult['defender']['unitsLost'],
                ]);
            } else {
                $this->notificationService->queueNotification('repelled_invasion', [
                    '_routeParams' => [(string)$this->invasionEvent->id],
                    'attackerDominionId' => $dominion->id,
                    'attackerWasOverwhelmed' => $this->invasionResult['result']['overwhelmed'],
                    'unitsLost' => $this->invasionResult['defender']['unitsLost'],
                ]);
            }

            # Debug before saving:
            if(request()->getHost() === 'odarena.local')
            {
                dd($this->invasionResult);
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
        $attackerPrestigeChangeMultiplier += $this->militaryCalculator->getPrestigeGainsPerk($attacker, $units);
        $attackerPrestigeChangeMultiplier += $attacker->getTechPerkMultiplier('prestige_gains');
        $attackerPrestigeChangeMultiplier += $attacker->getBuildingPerkMultiplier('prestige_gains');
        $attackerPrestigeChangeMultiplier += $attacker->getImprovementPerkMultiplier('prestige_gains');
        $attackerPrestigeChangeMultiplier += $attacker->getSpellPerkMultiplier('prestige_gains');
        $attackerPrestigeChangeMultiplier += $attacker->realm->getArtefactPerkMultiplier('prestige_gains');
        $attackerPrestigeChangeMultiplier += $attacker->title->getPerkMultiplier('prestige_gains') * $attacker->title->getPerkBonus($attacker);

        # Monarch gains +10% always
        if($attacker->isMonarch())
        {
            $attackerPrestigeChangeMultiplier += 0.10;
        }

        # Attacker gains +20% if defender is Monarch
        if($defender->isMonarch() and $this->invasionResult['result']['success'])
        {
            $attackerPrestigeChangeMultiplier += 0.20;
        }

        $attackerPrestigeChange *= (1 + $attackerPrestigeChangeMultiplier);

        // 1/4 gains for hitting Barbarians.
        if($defender->race->name === 'Barbarian')
        {
            $attackerPrestigeChange /= 4;

            # Liberation
            if(
                $attacker->realm->alignment !== 'evil' and
                $this->invasionResult['result']['success'] and
                $this->invasionResult['result']['opDpRatio'] >= 1.20 and
                $this->spellCalculator->isAnnexed($defender))
            {
                $this->invasionResult['attacker']['liberation'] = true;
                $attackerPrestigeChange = max(0, $attackerPrestigeChange);
                $attackerPrestigeChange *= 3;
            }
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
     * Handles casualties for a dominion
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
    protected function handleCasualties(Dominion $dominion, Dominion $enemy, array $casualties = [], string $mode = 'offense'): void
    {

        if($mode == 'offense')
        {
            foreach ($this->invasionResult['attacker']['unitsLost'] as $slot => $amount)
            {
                $dominion->{"military_unit{$slot}"} -= $amount;
                $this->invasionResult['attacker']['survivingUnits'][$slot] = $this->invasionResult['attacker']['unitsSent'][$slot] - $this->invasionResult['attacker']['unitsLost'][$slot];

                if(in_array($slot,[1,2,3,4]))
                {
                    $this->statsService->updateStat($dominion, ('unit' . $slot . '_lost'), $amount);
                }
                else
                {
                    $this->statsService->updateStat($dominion, ($slot . '_lost'), $amount);
                }
            }
        }

        if($mode == 'defense')
        {
            foreach ($this->invasionResult['defender']['unitsLost'] as $slot => $amount)
            {

                $this->invasionResult['defender']['survivingUnits'][$slot] = $this->invasionResult['defender']['unitsDefending'][$slot] - $this->invasionResult['defender']['unitsLost'][$slot];

                if(in_array($slot,[1,2,3,4]))
                {
                    $dominion->{"military_unit{$slot}"} -= $amount;
                    $this->statsService->updateStat($dominion, ('unit' . $slot . '_lost'), $amount);
                }
                else
                {
                    $dominion->{"military_{$slot}"} -= $amount;
                    $this->statsService->updateStat($dominion, ($slot . '_lost'), $amount);
                }
            }
        }

        $this->statsService->updateStat($enemy, 'units_killed', array_sum($casualties));
    }

    # !!! Offensive dies into handled in handleReturningUnits()!!!
    public function handleDefensiveDiesIntoPerks(Dominion $dominion)
    {
        # Look for dies_into amongst the dead.
        $diesIntoNewUnits = array_fill(1,4,0);
        $diesIntoNewUnitsInstantly = array_fill(1,4,0);

        $diesIntoNewUnits['spies'] = 0;
        $diesIntoNewUnits['wizards'] = 0;
        $diesIntoNewUnits['archmages'] = 0;
        $diesIntoNewUnitsInstantly['spies'] = 0;
        $diesIntoNewUnitsInstantly['wizards'] = 0;
        $diesIntoNewUnitsInstantly['archmages'] = 0;

        $unitsLost = $this->invasionResult['attacker']['unitsLost'];

        foreach($this->invasionResult['defender']['unitsLost'] as $slot => $casualties)
        {
            if(in_array($slot, [1,2,3,4]))
            {
                if($diesIntoPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into'))
                {
                    $slot = (int)$diesIntoPerk[0];

                    $diesIntoNewUnits[$slot] += intval($casualties);
                }

                if($diesIntoPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_spy'))
                {
                    $diesIntoNewUnits['spies'] += intval($casualties);
                }

                if($diesIntoPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_wizard'))
                {
                    $diesIntoNewUnits['wizards'] += intval($casualties);
                }

                if($diesIntoPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_archmage'))
                {
                    $diesIntoNewUnits['archmages'] += intval($casualties);
                }

                if($diesIntoPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_on_defense'))
                {
                    $slot = (int)$diesIntoPerk[0];

                    $diesIntoNewUnits[$slot] += intval($casualties);
                }

                if($diesIntoPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_on_defense_instantly'))
                {
                    $slot = (int)$diesIntoPerk[0];

                    $diesIntoNewUnitsInstantly[$slot] += intval($casualties);
                }

                if($diesIntoMultiplePerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple'))
                {
                    $slot = (int)$diesIntoMultiplePerk[0];
                    $amount = (float)$diesIntoMultiplePerk[1];

                    $diesIntoNewUnits[$slot] += intval($casualties * $amount);
                }

                if($diesIntoMultiplePerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple_on_defense'))
                {
                    $slot = (int)$diesIntoMultiplePerk[0];
                    $amount = (float)$diesIntoMultiplePerk[1];

                    $diesIntoNewUnits[$slot] += intval($casualties * $amount);
                }

                if($diesIntoMultiplePerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple_on_defense_instantly'))
                {
                    $slot = (int)$diesIntoMultiplePerk[0];
                    $amount = (float)$diesIntoMultiplePerk[1];

                    $diesIntoNewUnitsInstantly[$slot] += intval($casualties * $amount);
                }

                if(!$this->invasionResult['result']['success'] and $diesIntoMultiplePerkOnVictory = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple_on_victory'))
                {
                    $slot = (int)$diesIntoMultiplePerkOnVictory[0];
                    $amount = (float)$diesIntoMultiplePerkOnVictory[1];

                    $diesIntoNewUnits[$slot] += intval($casualties * $amount);
                }
            }

        }

        # Dies into units take 1 tick to appear
        foreach($diesIntoNewUnits as $slot => $amount)
        {
            if(in_array($slot, [1,2,3,4]))
            {
                $unitKey = 'military_unit'.$slot;
            }
            else
            {
                $unitKey = 'military_' . $slot;
            }

            $this->queueService->queueResources(
                'training',
                $dominion,
                [$unitKey => $amount],
                1
            );
        }

        # Dies into units take 1 tick to appear
        foreach($diesIntoNewUnitsInstantly as $slot => $amount)
        {
            if(in_array($slot, [1,2,3,4]))
            {
                $unitKey = 'military_unit'.$slot;
            }
            else
            {
                $unitKey = 'military_' . $slot;
            }
            $dominion->{$unitKey} += $amount;
        }
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

    protected function handleMoraleChanges(Dominion $dominion, Dominion $target, float $landRatio, array $units): void
    {

        $landRatio *= 100;
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

            $attackerMoraleChangeMultiplier = 1;
            $attackerMoraleChangeMultiplier += $dominion->getBuildingPerkMultiplier('morale_gains');
            $attackerMoraleChangeMultiplier += $dominion->race->getPerkMultiplier('morale_change_invasion');
            $attackerMoraleChangeMultiplier += $dominion->title->getPerkMultiplier('morale_gains') * $dominion->title->getPerkBonus($dominion);

            # Look for lowers_target_morale_on_successful_invasion
            for ($slot = 1; $slot <= 4; $slot++)
            {
                if(
                    $increasesMoraleGainsPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'increases_morale_gains') and
                    isset($units[$slot]) and
                    $this->invasionResult['result']['success']
                    )
                {
                    $attackerMoraleChangeMultiplier += ($this->invasionResult['attacker']['unitsSent'][$slot] / array_sum($this->invasionResult['attacker']['unitsSent'])) * $increasesMoraleGainsPerk;
                }
            }

            $attackerMoraleChange *= $attackerMoraleChangeMultiplier;

            $defenderMoraleChangeMultiplier = 1;
            $defenderMoraleChangeMultiplier += $target->race->getPerkMultiplier('morale_change_invasion');

            $defenderMoraleChange *= $defenderMoraleChangeMultiplier;

            # Look for lowers_target_morale_on_successful_invasion
            for ($slot = 1; $slot <= 4; $slot++)
            {
                if(
                    $lowersTargetMoralePerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'lowers_target_morale_on_successful_invasion') and
                    isset($units[$slot]) and
                    $this->invasionResult['result']['success']
                    )
                {
                    $defenderMoraleChange -= $this->invasionResult['attacker']['unitsSent'][$slot] * $lowersTargetMoralePerk;
                }
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
            $defenderMoraleChange = ($target->morale * -1);
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

        $researchPointsPerAcre = 40;

        $researchPointsPerAcreMultiplier = 1;

        # Increase RP per acre
        $researchPointsPerAcreMultiplier += $dominion->race->getPerkMultiplier('xp_per_acre_gained');
        $researchPointsPerAcreMultiplier += $dominion->getImprovementPerkMultiplier('xp_per_acre_gained');
        $researchPointsPerAcreMultiplier += $dominion->getBuildingPerkMultiplier('xp_per_acre_gained');
        $researchPointsPerAcreMultiplier += $dominion->getSpellPerkMultiplier('xp_per_acre_gained');
        $researchPointsPerAcreMultiplier += $dominion->getDeityPerkMultiplier('xp_per_acre_gained');

        $isInvasionSuccessful = $this->invasionResult['result']['success'];
        if ($isInvasionSuccessful)
        {
            $landConquered = array_sum($this->invasionResult['attacker']['landConquered']);

            $researchPointsForGeneratedAcres = 1;

            if(!$this->militaryCalculator->getRecentlyInvadedCountByAttacker($target, $dominion))
            {
                $researchPointsForGeneratedAcres = 2;
            }

            $researchPointsGained = round($landConquered * $researchPointsForGeneratedAcres * $researchPointsPerAcre * $researchPointsPerAcreMultiplier);
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
    protected function handleDuringInvasionUnitPerks(Dominion $attacker, Dominion $target, array $units): void
    {

        # Only if invasion is successful
        if($this->invasionResult['result']['success'])
        {
            # ATTACKER
            foreach($this->invasionResult['attacker']['unitsSent'] as $slot => $amount)
            {
                if ($destroysResourcePerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'destroy_resource_on_victory'))
                {
                    $resourceKey = (string)$destroysResourcePerk[0];
                    $amountDestroyedPerUnit = (float)$destroysResourcePerk[1];
                    $maxDestroyedBySlot = (int)round(min($this->invasionResult['attacker']['unitsSent'][$slot] * $amountDestroyedPerUnit, $this->resourceCalculator->getAmount($target, $resourceKey)));

                    if($maxDestroyedBySlot > 0)
                    {
                        if(isset($this->invasionResult['attacker']['resources_destroyed'][$resourceKey]))
                        {
                            $this->invasionResult['attacker']['resources_destroyed'][$resourceKey] += $maxDestroyedBySlot;
                        }
                        else
                        {
                            $this->invasionResult['attacker']['resources_destroyed'][$resourceKey] = $maxDestroyedBySlot;
                        }

                        $this->resourceService->updateResources($target, [$resourceKey => ($maxDestroyedBySlot * -1)]);
                    }
                }
            }
        }

        for ($slot = 1; $slot <= 4; $slot++)
        {
          # Snow Elf: Hailstorm Cannon exhausts all mana
           if($exhaustingPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'offense_from_resource_exhausting') and isset($units[$slot]))
           {
               $resourceKey = $exhaustingPerk[0];
               $resourceAmount = $this->resourceCalculator->getAmount($attacker, $resourceKey);

               $this->invasionResult['attacker'][$resourceKey . '_exhausted'] = $resourceAmount;

               $this->resourceService->updateResources($attacker, [$resourceKey => ($resourceAmount * -1)]);
           }

           # Yeti: Stonethrowers spend ore (but not necessarily all of it)
           if($exhaustingPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'offense_from_resource_capped_exhausting') and isset($units[$slot]))
           {
               $amountPerUnit = (float)$exhaustingPerk[1];
               $resourceKey = (string)$exhaustingPerk[2];

               $resourceAmountExhausted = $units[$slot] * $amountPerUnit;

               $this->invasionResult['attacker'][$resourceKey . '_exhausted'] = $resourceAmountExhausted;

               $this->resourceService->updateResources($attacker, [$resourceKey => ($resourceAmountExhausted * -1)]);
           }

           # Imperial Gnome: brimmer to fuel the Airships
           if($spendsResourcesOnOffensePerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'spends_resource_on_offense'))
           {
               $resourceKey = (string)$spendsResourcesOnOffensePerk[0];
               $resourceAmountPerUnit = (float)$spendsResourcesOnOffensePerk[1];
               $resource = Resource::where('key', $resourceKey)->firstOrFail();

               $resourceAmountSpent = $units[$slot] * $resourceAmountPerUnit;

               $this->invasionResult['attacker'][$resourceKey . '_exhausted'] = $resourceAmountSpent;

               $this->resourceService->updateResources($attacker, [$resourceKey => ($resourceAmountSpent * -1)]);
           }
        }

        # Ignore if attacker is overwhelmed.
        if(!$this->invasionResult['result']['overwhelmed'])
        {
            for ($unitSlot = 1; $unitSlot <= 4; $unitSlot++)
            {
                // burns_peasants
                if ($attacker->race->getUnitPerkValueForUnitSlot($unitSlot, 'burns_peasants_on_attack') and isset($units[$unitSlot]))
                {
                    $burningUnits = $units[$unitSlot];
                    $peasantsBurnedPerUnit = (float)$attacker->race->getUnitPerkValueForUnitSlot($unitSlot, 'burns_peasants_on_attack');

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

                // damages_improvements_on_attack
                if ($attacker->race->getUnitPerkValueForUnitSlot($unitSlot, 'damages_improvements_on_attack') and isset($units[$unitSlot]))
                {

                    $totalImprovementPoints = $this->improvementCalculator->getDominionImprovementTotalAmountInvested($target);

                    $targetImprovements = $this->improvementCalculator->getDominionImprovements($target);

                    $damagingUnits = $units[$unitSlot];
                    $damagePerUnit = $attacker->race->getUnitPerkValueForUnitSlot($unitSlot, 'damages_improvements_on_attack');

                    $damageMultiplier = 1;
                    $damageMultiplier += $target->getBuildingPerkMultiplier('lightning_bolt_damage');

                    $damage = $damagingUnits * $damagePerUnit * $damageMultiplier;
                    $damage = min($damage, $totalImprovementPoints);

                    if($damage > 0)
                    {
                        foreach($targetImprovements as $targetImprovement)
                        {
                            $improvement = Improvement::where('id', $targetImprovement->improvement_id)->first();
                            $improvementDamage[$improvement->key] = floor($damage * ($this->improvementCalculator->getDominionImprovementAmountInvested($target, $improvement) / $totalImprovementPoints));
                        }
                        $this->improvementCalculator->decreaseImprovements($target, $improvementDamage);
                    }

                    $this->invasionResult['attacker']['improvements_damage']['improvement_points'] = $damage;
                    $this->invasionResult['defender']['improvements_damage']['improvement_points'] = $damage;
                }


                if ($attacker->race->getUnitPerkValueForUnitSlot($unitSlot, 'eats_peasants_on_attack') and isset($units[$unitSlot]))
                {
                    $eatingUnits = $units[$unitSlot];
                    $peasantsEatenPerUnit = (float)$attacker->race->getUnitPerkValueForUnitSlot($unitSlot, 'eats_peasants_on_attack');

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
                if ($attacker->race->getUnitPerkValueForUnitSlot($unitSlot, 'eats_draftees_on_attack') and isset($units[$unitSlot]))
                {
                    $eatingUnits = $units[$unitSlot];
                    $drafteesEatenPerUnit = $attacker->race->getUnitPerkValueForUnitSlot($unitSlot, 'eats_draftees_on_attack');

                    $eatenDraftees = round($eatingUnits * $drafteesEatenPerUnit * min($this->invasionResult['result']['opDpRatio'], 1));
                    $eatenDraftees = min($target->military_draftees, $eatenDraftees);

                    $target->military_draftees -= $eatenDraftees;
                    $this->invasionResult['attacker']['draftees_eaten']['draftees'] = $eatenDraftees;
                    $this->invasionResult['defender']['draftees_eaten']['draftees'] = $eatenDraftees;
                }
            }
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
                'aspect',
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

    public function handlePeasantCapture(Dominion $attacker, Dominion $defender, array $units, float $landRatio): void
    {
        if($attacker->race->name !== 'Demon' or !$this->invasionResult['result']['success'])
        {
            return;
        }

        $rawOp = 0;
        foreach($this->invasionResult['attacker']['unitsSent'] as $slot => $amount)
        {
            if($amount > 0)
            {
                $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $rawOpFromSlot = $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense');
                $totalRawOpFromSlot = $rawOpFromSlot * $amount;

                $rawOp += $totalRawOpFromSlot;
            }
        }

        $landConquered = array_sum($this->invasionResult['attacker']['landConquered']);
        $displacedPeasants = intval(($defender->peasants / $this->invasionResult['defender']['landSize']) * $landConquered);

        foreach($units as $slot => $amount)
        {
            if ($attacker->race->getUnitPerkValueForUnitSlot($slot, 'captures_displaced_peasants'))
            {
                $opFromSlot = $this->militaryCalculator->getOffensivePowerRaw($attacker, $defender, $landRatio, [$slot => $amount]);
                $opRatio = $opFromSlot / $rawOp;

                $peasantsCaptured = (int)floor($displacedPeasants * $opRatio);

                #dump('Slot ' . $slot . ' OP: ' . number_format($opFromSlot) . ' which is ' . $opRatio . ' ratio relative to ' . number_format($rawOp) . ' raw OP total.');

                if(isset($this->invasionResult['attacker']['peasants_captured']))
                {
                    $this->invasionResult['attacker']['peasants_captured'] += $peasantsCaptured;
                }
                else
                {
                    $this->invasionResult['attacker']['peasants_captured'] = $peasantsCaptured;
                }
            }
        }

        if(isset($this->invasionResult['attacker']['peasants_captured']))
        {
            $this->invasionResult['attacker']['peasants_captured'] = intval(max(0, $this->invasionResult['attacker']['peasants_captured']));

            $this->queueService->queueResources(
                'invasion',
                $attacker,
                ['peasants' => $this->invasionResult['attacker']['peasants_captured']],
                12
            );
        }
    }

    public function handlePeasantKilling(Dominion $attacker, Dominion $defender, array $units, float $landRatio): void
    {
        if($defender->race->name !== 'Demon' or !$this->invasionResult['result']['success'])
        {
            return;
        }

        $this->invasionResult['defender']['displaced_peasants_killing']['peasants_killed'] = 0;
        $this->invasionResult['defender']['displaced_peasants_killing']['soul'] = 0;
        $this->invasionResult['defender']['displaced_peasants_killing']['blood'] = 0;

        $rawDp = 0;
        foreach($this->invasionResult['defender']['unitsDefending'] as $slot => $amount)
        {
            if($amount > 0)
            {
                if($slot == 'draftees')
                {
                    $rawDpFromSlot = 1;
                }
                elseif(in_array($slot, [1,2,3,4]))
                {
                    $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                        return ($unit->slot === $slot);
                    })->first();

                    $rawDpFromSlot = $this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense');
                }

                $totalRawDpFromSlot = $rawDpFromSlot * $amount;

                $rawDp += $totalRawDpFromSlot;
            }
        }

        $landConquered = array_sum($this->invasionResult['attacker']['landConquered']);
        $displacedPeasants = intval(($defender->peasants / $this->invasionResult['defender']['landSize']) * $landConquered);

        foreach($this->invasionResult['defender']['unitsDefending'] as $slot => $amount)
        {
            if(in_array($slot, [1,2,3,4]))
            {
                if ($defender->race->getUnitPerkValueForUnitSlot($slot, 'kills_displaced_peasants'))
                {
                    $dpFromSlot = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, [$slot => $amount]);
                    $dpRatio = $dpFromSlot / $rawDp;

                    $peasantsKilled = (int)floor($displacedPeasants * $dpRatio);

                    $this->invasionResult['defender']['displaced_peasants_killing']['peasants_killed'] += $peasantsKilled;
                }
            }
        }

        $this->invasionResult['defender']['displaced_peasants_killing']['peasants_killed'] = intval(min(($defender->peasants-1000), max(0, $this->invasionResult['defender']['displaced_peasants_killing']['peasants_killed'])));
        $this->invasionResult['defender']['displaced_peasants_killing']['soul'] = $this->invasionResult['defender']['displaced_peasants_killing']['peasants_killed'];
        $this->invasionResult['defender']['displaced_peasants_killing']['blood'] = $this->invasionResult['defender']['displaced_peasants_killing']['peasants_killed'] * 6;

        $defender->peasants -= $this->invasionResult['defender']['displaced_peasants_killing']['peasants_killed'];

        $resourceArray = ['blood' => $this->invasionResult['defender']['displaced_peasants_killing']['blood'], 'soul' => $this->invasionResult['defender']['displaced_peasants_killing']['soul']];

        $this->resourceService->updateResources($defender, $resourceArray);

    }


    # Unit Return 2.0
    protected function handleReturningUnits(Dominion $attacker, array $units, array $convertedUnits): void
    {
        # If instant return
        if(random_chance($attacker->getImprovementPerkMultiplier('chance_of_instant_return')) or $attacker->race->getPerkValue('instant_return') or $attacker->getSpellPerkValue('instant_return'))
        {
            $this->invasionResult['attacker']['instantReturn'] = true;
        }
        # Normal return
        else
        {
            $returningUnits = [
                'military_unit1' => array_fill(1, 12, 0),
                'military_unit2' => array_fill(1, 12, 0),
                'military_unit3' => array_fill(1, 12, 0),
                'military_unit4' => array_fill(1, 12, 0),
                'military_spies' => array_fill(1, 12, 0),
                'military_wizards' => array_fill(1, 12, 0),
                'military_archmages' => array_fill(1, 12, 0),
            ];

            # Check for instant_return
            for ($slot = 1; $slot <= 4; $slot++)
            {
                if($attacker->race->getUnitPerkValueForUnitSlot($slot, 'instant_return'))
                {
                    # This removes the unit from the $returningUnits array, thereby ensuring it is neither removed nor queued.
                    unset($returningUnits['military_unit' . $slot]);
                }
            }

            $someWinIntoUnits = array_fill(1, 4, 0);
            $someWinIntoUnits = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

            foreach($returningUnits as $unitKey => $values)
            {
                $unitType = str_replace('military_', '', $unitKey);
                $slot = str_replace('unit', '', $unitType);
                $amountReturning = 0;

                $returningUnitKey = $unitKey;

                if(in_array($slot, [1,2,3,4]))
                {
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

                        if($diesIntoPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_wizard'))
                        {
                            # Which unit do they die into?
                            $newUnitKey = "military_wizards";
                            $newUnitSlotReturnTime = 12;

                            $returningUnits[$newUnitKey][$newUnitSlotReturnTime] += $casualties;
                        }

                        if($diesIntoPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_spy'))
                        {
                            # Which unit do they die into?
                            $newUnitKey = "military_spies";
                            $newUnitSlotReturnTime = 12;

                            $returningUnits[$newUnitKey][$newUnitSlotReturnTime] += $casualties;
                        }

                        if($diesIntoPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_archmage'))
                        {
                            # Which unit do they die into?
                            $newUnitKey = "military_archmages";
                            $newUnitSlotReturnTime = 12;

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

                        # Check for faster_return_from_time
                        if($fasterReturnFromTimePerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'faster_return_from_time'))
                        {

                            $hourFrom = $fasterReturnFromTimePerk[0];
                            $hourTo = $fasterReturnFromTimePerk[1];
                            if (
                                (($hourFrom < $hourTo) and (now()->hour >= $hourFrom and now()->hour < $hourTo)) or
                                (($hourFrom > $hourTo) and (now()->hour >= $hourFrom or now()->hour < $hourTo))
                            )
                            {
                                $ticksFaster = (int)$fasterReturnFromTimePerk[2];
                            }
                            else
                            {
                                $ticksFaster = 0;
                            }

                            $fasterReturningTicks = min(max(1, ($ticks - $ticksFaster)), 12);

                            # How many of $slot should return faster?
                            $unitsWithFasterReturnTime = $amountReturning;

                            $returningUnits[$unitKey][$fasterReturningTicks] += $unitsWithFasterReturnTime;
                            $returningUnits[$unitKey][$ticks] -= $unitsWithFasterReturnTime;
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
                }
            }

            # Check for faster return from pairing perks
            foreach($returningUnits as $unitKey => $unitKeyTicks)
            {
                $unitType = str_replace('military_', '', $unitKey);
                $slot = str_replace('unit', '', $unitType);
                $amountReturning = 0;

                $returningUnitKey = $unitKey;

                if(in_array($slot, [1,2,3,4]))
                {
                    $amountReturning = array_sum($returningUnits[$unitKey]);

                    # Check for faster_return_if_paired
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
                        $unitsWithRegularReturnTime = max(0, $units[$slot] - $unitsWithFasterReturnTime);

                        $returningUnits[$unitKey][$fasterReturningTicks] += $unitsWithFasterReturnTime;
                        $returningUnits[$unitKey][$ticks] -= $unitsWithFasterReturnTime;
                    }

                    # Check for faster_return_if_paired_multiple
                    if($fasterReturnIfPairedMultiplePerk = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'faster_return_if_paired_multiple'))
                    {
                        $pairedUnitSlot = (int)$fasterReturnIfPairedMultiplePerk[0];
                        $pairedUnitKey = 'military_unit'.$pairedUnitSlot;
                        $ticksFaster = (int)$fasterReturnIfPairedMultiplePerk[1];
                        $unitChunkSize = (int)$fasterReturnIfPairedMultiplePerk[2];
                        $pairedUnitKeyReturning = array_sum($returningUnits[$pairedUnitKey]);

                        # Determine new return speed
                        $fasterReturningTicks = min(max($ticks - $ticksFaster, 1), 12);

                        # How many of $slot should return faster?
                        $unitsWithFasterReturnTime = min($pairedUnitKeyReturning * $unitChunkSize, $amountReturning);
                        $unitsWithRegularReturnTime = max(0, $units[$slot] - $unitsWithFasterReturnTime);

                        $returningUnits[$unitKey][$fasterReturningTicks] += $unitsWithFasterReturnTime;
                        $returningUnits[$unitKey][$ticks] -= $unitsWithFasterReturnTime;
                    }
                }
            }

            $this->invasionResult['attacker']['units_returning_raw'] = $returningUnits;

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
                $this->invasionResult['attacker']['invasion_spell'][] = 'pestilence';
            }

            # Great Fever
            if($this->invasionResult['result']['success'])
            {
                $this->spellActionService->castSpell($attacker, 'great_fever', $defender, $isInvasionSpell);
                $this->invasionResult['attacker']['invasion_spell'][] = 'great_fever';
            }
        }

        if($defender->race->name == 'Afflicted')
        {
            # Festering Wounds
            $this->spellActionService->castSpell($defender, 'festering_wounds', $attacker, $isInvasionSpell);
            $result['attacker']['invasion_spell'][] = 'festering_wounds';

            # Not an invasion spell, but this goes here for now (Miasmic Charges)
            if($defender->getSpellPerkValue('resource_lost_on_invasion') and !$this->invasionResult['result']['overwhelmed'])
            {
                $spell = Spell::where('key', 'miasmic_charges')->first();
                $perkValueArray = $spell->getActiveSpellPerkValues($spell->key, 'resource_lost_on_invasion');

                $ratio = (float)$perkValueArray[0] / 100;
                $resourceKey = (string)$perkValueArray[1];
                $resourceAmountOwned = $this->resourceCalculator->getAmount($defender, $resourceKey);
                $resourceAmountLost = $resourceAmountOwned * ($ratio * -1);

                $this->invasionResult['defender']['resources_lost'][$resourceKey] = $resourceAmountLost;

                $this->resourceService->updateResources($defender, [$resourceKey => ($resourceAmountOwned * -1)]);
            }
        }

        if($attacker->race->name == 'Legion' and $defender->race->name == 'Barbarian' and $this->invasionResult['result']['success'])
        {
            $this->spellActionService->castSpell($attacker, 'annexation', $defender, $isInvasionSpell);
            $this->invasionResult['result']['annexation'] = true;
        }
    }

    protected function handleResourceConversions(Dominion $attacker, Dominion $defender, float $landRatio): void
    {
        foreach($attacker->race->resources as $resourceKey)
        {
            $attackerResourceTemplate['resource_' . $resourceKey] = 0;
        }
        foreach($defender->race->resources as $resourceKey)
        {
            $defenderResourceTemplate['resource_' . $resourceKey] = 0;
        }

        $this->invasionResult['attacker']['resource_conversion'] = $attackerResourceTemplate;
        $this->invasionResult['defender']['resource_conversion'] = $defenderResourceTemplate;

        $rawOp = 0;
        foreach($this->invasionResult['attacker']['unitsSent'] as $slot => $amount)
        {
            if($amount > 0)
            {
                $unit = $attacker->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $rawOpFromSlot = $this->militaryCalculator->getUnitPowerWithPerks($attacker, $defender, $landRatio, $unit, 'offense');
                $totalRawOpFromSlot = $rawOpFromSlot * $amount;

                $rawOp += $totalRawOpFromSlot;
            }
        }

        $rawDp = 0;
        foreach($this->invasionResult['defender']['unitsDefending'] as $slot => $amount)
        {
            if($amount > 0)
            {
                if($slot !== 'draftees' and $slot !== 'peasants')
                {
                    $unit = $defender->race->units->filter(function ($unit) use ($slot) {
                        return ($unit->slot === $slot);
                    })->first();
                }

                $rawDpFromSlot = $this->militaryCalculator->getUnitPowerWithPerks($defender, $attacker, $landRatio, $unit, 'defense');
                $totalRawDpFromSlot = $rawDpFromSlot * $amount;

                $rawDp += $totalRawDpFromSlot;

                # slot:amount_defending:raw_dp_per_unit_of_this_slot:raw_dp_from_slot
                #dump($slot . ':' . $amount . ':' . $rawDpFromSlot . ':' . $totalRawDpFromSlot);
            }

        }

        # Remove non-living units from $dpFromLostDefendingUnits and $opFromLostAttackingUnits
        $livingDefendingUnits = $this->invasionResult['defender']['unitsLost'];
        $livingAttackingUnits = $this->invasionResult['attacker']['unitsLost'];

        foreach($livingDefendingUnits as $slot => $amount)
        {
            if($slot !== 'draftees' and !$this->unitHelper->unitSlotHasAttributes($defender->race, $slot, ['living']))
            {
                unset($livingDefendingUnits[$slot]);
            }
        }

        foreach($livingAttackingUnits as $slot => $amount)
        {
            if($slot !== 'draftees' and !$this->unitHelper->unitSlotHasAttributes($attacker->race, $slot, ['living']))
            {
                unset($livingAttackingUnits[$slot]);
            }
        }

        $dpFromLostDefendingUnits = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, $livingDefendingUnits, 0, false, $this->isAmbush, true);
        $opFromLostAttackingUnits = $this->militaryCalculator->getOffensivePowerRaw($attacker, $defender, $landRatio, $livingAttackingUnits);

        $this->invasionResult['defender']['dp_from_lost_living_defending_units'] = $dpFromLostDefendingUnits;
        $this->invasionResult['attacker']['op_from_lost_living_attacking_units'] = $opFromLostAttackingUnits;

        foreach($this->invasionResult['attacker']['unitsSent'] as $slot => $amount)
        {
            # Attacker: kills_into_resource_per_casualty SINGLE RESOURCE
            if($killsIntoResourcePerCasualty = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resource_per_casualty'))
            {
                $amountPerCasualty = $killsIntoResourcePerCasualty[0] * $this->conversionCalculator->getConversionReductionMultiplier($defender);
                $resource = 'resource_' . $killsIntoResourcePerCasualty[1];

                $opFromSlot = $this->militaryCalculator->getOffensivePowerRaw($attacker, $defender, $landRatio, [$slot => $amount]);

                foreach($this->invasionResult['defender']['unitsLost'] as $slotKilled => $amountKilled)
                {
                    if($this->unitHelper->unitSlotHasAttributes($defender->race, $slotKilled, ['living']))
                    {
                          $killsAttributableToThisSlot = $amountKilled * ($opFromSlot / $rawOp);
                          $this->invasionResult['attacker']['resource_conversion'][$resource] += round($killsAttributableToThisSlot * $amountPerCasualty);

                          #dump('[Enemy slot ' . $slotKilled . ' killed: ' . number_format($amountKilled) . '] Slot ' . $slot . ' OP: ' . number_format($opFromSlot) . '/' . number_format($rawOp) . ' = ' . ($opFromSlot / $rawOp) . '. Kills attributable: ' . number_format($killsAttributableToThisSlot) . '.');
                    }
                }
            }

            # Attacker: kills_into_resource_per_casualty_on_success SINGLE RESOURCE, ON SUCCESS
            if($killsIntoResourcePerCasualty = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resource_per_casualty_on_success') and $this->invasionResult['result']['success'])
            {
                $amountPerCasualty = $killsIntoResourcePerCasualty[0] * $this->conversionCalculator->getConversionReductionMultiplier($defender);
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
                    $amountPerCasualty = $killsIntoResourcesPerCasualtyPerk[0] * $this->conversionCalculator->getConversionReductionMultiplier($defender);
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
                $amountPerPoint = $killsIntoResourcePerCasualty[0] * $this->conversionCalculator->getConversionReductionMultiplier($defender);
                $resource = 'resource_' . $killsIntoResourcePerCasualty[1];

                $opFromSlot = $this->militaryCalculator->getOffensivePowerRaw($attacker, $defender, $landRatio, [$slot => $amount]);

                foreach($this->invasionResult['defender']['unitsLost'] as $slotKilled => $amountKilled)
                {
                    if($this->unitHelper->unitSlotHasAttributes($defender->race, $slotKilled, ['living']))
                    {
                          $killsAttributableToThisSlot = $dpFromLostDefendingUnits * ($opFromSlot / $rawOp);
                          $this->invasionResult['attacker']['resource_conversion'][$resource] += round($killsAttributableToThisSlot * $amountPerPoint);
                    }
                }

            }

            # Attacker: kills_into_resources_per_value MULTIPLE RESOURCES
            if($killsIntoResourcesPerCasualty = $attacker->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resources_per_value'))
            {
                foreach($killsIntoResourcesPerCasualty as $killsIntoResourcesPerCasualtyPerk)
                {
                    $amountPerPoint = $killsIntoResourcesPerCasualtyPerk[0] * $this->conversionCalculator->getConversionReductionMultiplier($defender);
                    $resource = 'resource_' . $killsIntoResourcesPerCasualtyPerk[1];

                    $opFromSlot = $this->militaryCalculator->getOffensivePowerRaw($attacker, $defender, $landRatio, [$slot => $amount]);

                    foreach($this->invasionResult['defender']['unitsLost'] as $slotKilled => $amountKilled)
                    {
                        if($this->unitHelper->unitSlotHasAttributes($defender->race, $slotKilled, ['living']))
                        {
                              $dpKilledFromSlot = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, [$slotKilled => $amountKilled], 0, false, $this->isAmbush, true, [$this->invasionResult['attacker']['unitsSent']], true);
                              #$dpKilledByThisSlot =  * ($opFromSlot / $rawOp);
                              $killsAttributableToThisSlot = $dpKilledFromSlot * ($opFromSlot / $rawOp);
                              $resourceAmount = round($killsAttributableToThisSlot * $amountPerPoint);
                              $this->invasionResult['attacker']['resource_conversion'][$resource] += $resourceAmount;

                              #dump('[Enemy slot ' . $slotKilled . ' killed: ' . number_format($amountKilled) . ', DP: ' . number_format($dpKilledFromSlot) .'] Slot ' . $slot . ' OP: ' . number_format($opFromSlot) . '/' . number_format($rawOp) . ' = ' . ($opFromSlot / $rawOp) . '. DP killed attributable: ' . number_format($killsAttributableToThisSlot) . ', yielding ' . number_format($resourceAmount) . ' ' . $resource . '.');
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
                    $amountPerCasualty = $killsIntoResourcesPerCasualty[0] * $this->conversionCalculator->getConversionReductionMultiplier($attacker);
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
                  $amountPerCasualty = $killsIntoResourcesPerCasualty[0] * $this->conversionCalculator->getConversionReductionMultiplier($attacker);
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
                      $amountPerCasualty = $killsIntoResourcesPerCasualtyPerk[0] * $this->conversionCalculator->getConversionReductionMultiplier($attacker);
                      $resource = 'resource_' . $killsIntoResourcesPerCasualtyPerk[1];

                      $dpFromSlot = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, [$slot => $amount]);

                      foreach($this->invasionResult['attacker']['unitsLost'] as $slotKilled => $amountKilled)
                      {
                          if($this->unitHelper->unitSlotHasAttributes($attacker->race, $slotKilled, ['living']))
                          {
                                $killsAttributableToThisSlot = $amountKilled * ($dpFromSlot / $rawDp);
                                $this->invasionResult['defender']['resource_conversion'][$resource] += round($killsAttributableToThisSlot * $amountPerCasualty);

                                #echo "<pre>Slot $slot accounts for " . $dpFromSlot / $rawDp . " of $opFromLostAttackingUnits OP killed, meaning they killed $killsAttributableToThisSlot raw OP</pre>";
                          }
                      }
                  }
                }

                # Defender: kills_into_resource_per_value SINGLE RESOURCE
                if($killsIntoResourcePerCasualty = $defender->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resource_per_value'))
                {
                  $amountPerPoint = $killsIntoResourcePerCasualty[0] * $this->conversionCalculator->getConversionReductionMultiplier($attacker);
                  $resource = 'resource_' . $killsIntoResourcePerCasualty[1];

                  $dpFromSlot = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, [$slot => $amount]);

                  foreach($this->invasionResult['attacker']['unitsLost'] as $slotKilled => $amountKilled)
                  {
                      if($this->unitHelper->unitSlotHasAttributes($attacker->race, $slotKilled, ['living']))
                      {
                            $killsAttributableToThisSlot = $opFromLostAttackingUnits * ($dpFromSlot / $rawDp);
                            $this->invasionResult['defender']['resource_conversion'][$resource] += round($killsAttributableToThisSlot * $amountPerPoint);

                            #dump($killsAttributableToThisSlot, $opFromLostAttackingUnits, $dpFromSlot, $rawDp, ($dpFromSlot / $rawDp));
                      }
                  }
                }

                # Defender: kills_into_resources_per_value MULTIPLE RESOURCES
                if($killsIntoResourcesPerCasualty = $defender->race->getUnitPerkValueForUnitSlot($slot, 'kills_into_resources_per_value'))
                {
                    foreach($killsIntoResourcesPerCasualty as $killsIntoResourcesPerValuePerk)
                    {
                        $amountPerPoint = $killsIntoResourcesPerValuePerk[0] * $this->conversionCalculator->getConversionReductionMultiplier($attacker);
                        $resource = 'resource_' . $killsIntoResourcesPerValuePerk[1];

                        $dpFromSlot = $this->militaryCalculator->getDefensivePowerRaw($defender, $attacker, $landRatio, [$slot => $amount]);

                        foreach($this->invasionResult['attacker']['unitsLost'] as $slotKilled => $amountKilled)
                        {
                            if($this->unitHelper->unitSlotHasAttributes($attacker->race, $slotKilled, ['living']))
                            {
                                  $killsAttributableToThisSlot = $opFromLostAttackingUnits * ($dpFromSlot / $rawDp);
                                  $this->invasionResult['defender']['resource_conversion'][$resource] += round($killsAttributableToThisSlot * $amountPerPoint);
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
    protected function handleSalvagingAndPlundering(Dominion $attacker, Dominion $defender): void
    {
        foreach($attacker->race->resources as $resourceKey)
        {
            $result['attacker']['plunder'][$resourceKey] = 0;
        }

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
                    $unitOreCost = isset($unitCosts[$unitType]['ore']) ? $unitCosts[$unitType]['ore'] : 0;
                    $unitLumberCost = isset($unitCosts[$unitType]['lumber']) ? $unitCosts[$unitType]['lumber'] : 0;
                    $unitGemCost = isset($unitCosts[$unitType]['gems']) ? $unitCosts[$unitType]['gems'] : 0;

                    $result['defender']['salvage']['ore'] += $amountLost * $unitOreCost * $salvaging;
                    $result['defender']['salvage']['lumber'] += $amountLost * $unitLumberCost * $salvaging;
                    $result['defender']['salvage']['gems'] += $amountLost * $unitGemCost * $salvaging;
                }
            }

            # Update statistics
            $this->statsService->updateStat($defender, 'ore_salvaged', $result['defender']['salvage']['ore']);
            $this->statsService->updateStat($defender, 'lumber_salvaged', $result['defender']['salvage']['lumber']);
            $this->statsService->updateStat($defender, 'gems_salvaged', $result['defender']['salvage']['gems']);
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
                $unitOreCost = isset($unitCosts[$unitType]['ore']) ? $unitCosts[$unitType]['ore'] : 0;
                $unitLumberCost = isset($unitCosts[$unitType]['lumber']) ? $unitCosts[$unitType]['lumber'] : 0;
                $unitGemCost = isset($unitCosts[$unitType]['gems']) ? $unitCosts[$unitType]['gems'] : 0;

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
        foreach($this->invasionResult['attacker']['survivingUnits'] as $slot => $amount)
        {
            if($plunderPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot,'plunders'))
            {
                foreach($plunderPerk as $plunder)
                {
                    $resourceToPlunder = $plunder[0];
                    $amountPlunderedPerUnit = (float)$plunder[1];

                    $amountToPlunder = $amount * $amountPlunderedPerUnit;
                    $result['attacker']['plunder'][$resourceToPlunder] += $amountToPlunder;
                }

                #dump($amountToPlunder . ' ' . $resourceToPlunder . ' plundered by unit' . $slot . '(' . $amountPlunderedPerUnit . ' each: ' . number_format($amount) . ' survivors)');
            }

            if($plunderPerk = $attacker->race->getUnitPerkValueForUnitSlot($slot,'plunder'))
            {
                $resourceToPlunder = $plunderPerk[0];
                $amountPlunderedPerUnit = (float)$plunderPerk[1];

                $amountToPlunder = $amount * $amountPlunderedPerUnit;
                $result['attacker']['plunder'][$resourceToPlunder] += $amountToPlunder;

                #dump($amountToPlunder . ' ' . $resourceToPlunder . ' plundered by unit' . $slot . '(' . $amountPlunderedPerUnit . ' each: ' . number_format($amount) . ' survivors)');
            }
        }

        # Remove plundered resources from defender.
        foreach($result['attacker']['plunder'] as $resourceKey => $amount)
        {
            if($amount > 0)
            {
                $result['attacker']['plunder'][$resourceKey] = min($amount, $this->resourceCalculator->getAmount($defender, $resourceKey));
                $this->resourceService->updateResources($defender, [$resourceKey => ($result['attacker']['plunder'][$resourceKey] * -1)]);
            }
        }

        # Add salvaged resources to defender.
        foreach($result['defender']['salvage'] as $resourceKey => $amount)
        {
            if($amount > 0)
            {
                $this->resourceService->updateResources($defender, [$resourceKey => $amount]);
            }
        }

        # Queue plundered and salvaged resources to attacker.
        foreach($result['attacker']['plunder'] as $resourceKey => $amount)
        {
            if($amount > 0)
            {
                # If the resource is ore, lumber, or gems, also check for salvaged resources.
                if(in_array($resourceKey, ['ore', 'lumber', 'gems']))
                {
                    $amount += $result['attacker']['salvage'][$resourceKey];
                }

                $this->queueService->queueResources(
                    'invasion',
                    $attacker,
                    [
                        'resource_'.$resourceKey => $amount
                    ]
                );

                $this->statsService->updateStat($attacker, ($resourceKey . '_plundered'), $amount);
            }
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
            # Cap bodies by reduced conversions perk, and round.
            $defensiveBodies = round(array_sum($this->invasionResult['defender']['unitsLost']) * $this->conversionCalculator->getConversionReductionMultiplier($defender));
            $offensiveBodies = round(array_sum($this->invasionResult['attacker']['unitsLost']) * $this->conversionCalculator->getConversionReductionMultiplier($attacker));

            $cryptLogString .= 'Defensive bodies (raw): ' . number_format($defensiveBodies) . ' | ';
            $cryptLogString .= 'Offensive bodies (raw): ' . number_format($offensiveBodies) . ' | ';

            $this->invasionResult['defender']['crypt']['bodies_available_raw'] = $defensiveBodies;
            $this->invasionResult['attacker']['crypt']['bodies_available_raw'] = $offensiveBodies;

            # Loop through defensive casualties and remove units that don't qualify.
            foreach($this->invasionResult['defender']['unitsLost'] as $slot => $lost)
            {
                $uncryptablePerks = [
                    'dies_into',
                    'dies_into_spy',
                    'dies_into_wizard',
                    'dies_into_archmage',
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
                    'dies_into_spy',
                    'dies_into_wizard',
                    'dies_into_archmage',
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
        $attackingForceOP = $this->militaryCalculator->getOffensivePower($dominion, $target, $landRatio, $units, [], true);
        $targetDP = $this->getDefensivePowerWithTemples($dominion, $target, $units, $landRatio, $this->isAmbush);
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
        $newHomeForcesDP = $this->militaryCalculator->getDefensivePower($dominion, null, null, $unitsHome, 0, false, false, false, null, true); # The "true" at the end excludes raw DP from annexed dominions

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
        $ticks -= (int)$dominion->realm->getArtefactPerkValue('faster_return');

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
      bool $isAmbush
      ): float
    {
        $dpMultiplierReduction = $this->militaryCalculator->getDefensiveMultiplierReduction($attacker);

        // Void: immunity to DP mod reductions
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
                                                            false, # ignoreDraftees
                                                            $this->isAmbush,
                                                            false,
                                                            $units, # Becomes $invadingUnits
                                                          );
    }

}
