<?php

namespace OpenDominion\Services\Dominion;

use DB;
use File;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Log;
use OpenDominion\Calculators\RealmCalculator;
use OpenDominion\Calculators\Dominion\BarbarianCalculator;
use OpenDominion\Calculators\Dominion\BuildingCalculator;
use OpenDominion\Calculators\Dominion\CasualtiesCalculator;
use OpenDominion\Calculators\Dominion\DeityCalculator;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\PopulationCalculator;
use OpenDominion\Calculators\Dominion\PrestigeCalculator;
use OpenDominion\Calculators\Dominion\ProductionCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;
use OpenDominion\Calculators\Dominion\SpellDamageCalculator;
use OpenDominion\Helpers\ImprovementHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Realm;
use OpenDominion\Models\GameEvent;
use OpenDominion\Models\Dominion\Tick;
use OpenDominion\Models\Resource;
use OpenDominion\Models\Round;
use OpenDominion\Models\Spell;
use OpenDominion\Models\Building;
use OpenDominion\Models\Improvement;
use OpenDominion\Models\DominionBuilding;
use OpenDominion\Services\NotificationService;
use OpenDominion\Services\Dominion\InsightService;
use OpenDominion\Services\Dominion\ProtectionService;
use OpenDominion\Models\Deity;
use Throwable;

class TickService
{
    protected const COUNTDOWN_DURATION_HOURS = 12;
    protected const EXTENDED_LOGGING = false;

    /** @var Carbon */
    protected $now;

    /** @var CasualtiesCalculator */
    protected $casualtiesCalculator;

    /** @var LandCalculator */
    protected $landCalculator;

    /** @var NotificationService */
    protected $notificationService;

    /** @var PopulationCalculator */
    protected $populationCalculator;

    /** @var ProductionCalculator */
    protected $productionCalculator;

    /** @var QueueService */
    protected $queueService;

    /** @var BarbarianService */
    protected $barbarianService;

    /** @var BarbarianCalculator */
    protected $barbarianCalculator;

    /** @var SpellCalculator */
    protected $spellCalculator;

    /** @var MilitaryCalculator */
    protected $militaryCalculator;

    /** @var ImprovementHelper */
    protected $improvementHelper;

    /** @var RealmCalculator */
    protected $realmCalculator;

    /**
     * TickService constructor.
     */
    public function __construct()
    {
        $this->now = now();
        $this->barbarianCalculator = app(BarbarianCalculator::class);
        $this->casualtiesCalculator = app(CasualtiesCalculator::class);
        $this->improvementCalculator = app(ImprovementCalculator::class);
        $this->landCalculator = app(LandCalculator::class);
        $this->notificationService = app(NotificationService::class);
        $this->populationCalculator = app(PopulationCalculator::class);
        $this->prestigeCalculator = app(PrestigeCalculator::class);
        $this->productionCalculator = app(ProductionCalculator::class);
        $this->resourceCalculator = app(ResourceCalculator::class);
        $this->queueService = app(QueueService::class);
        $this->spellCalculator = app(SpellCalculator::class);

        $this->buildingCalculator = app(BuildingCalculator::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->improvementHelper = app(ImprovementHelper::class);
        $this->realmCalculator = app(RealmCalculator::class);
        $this->spellDamageCalculator = app(SpellDamageCalculator::class);
        $this->deityService = app(DeityService::class);
        $this->insightService = app(InsightService::class);
        $this->protectionService = app(ProtectionService::class);
        $this->resourceService = app(ResourceService::class);

        $this->barbarianService = app(BarbarianService::class);

        /* These calculators need to ignore queued resources for the following tick */
        $this->populationCalculator->setForTick(true);
        $this->queueService->setForTick(true);
    }


    /**
     * Does an hourly tick on all active dominions.
     *
     * @throws Exception|Throwable
     */
    public function tickHourly()
    {
        if(File::exists('storage/framework/down'))
        {
            $logString = 'Tick at ' . $this->now . ' skipped.';
            Log::debug($logString);
        }

        $tickTime = now();

        Log::debug('Scheduled tick started');

        $activeRounds = Round::active()->get();

        foreach ($activeRounds as $round)
        {

            Log::debug('Tick for round ' . $round->number . ' started at ' . $tickTime . '.');

            # Get dominions IDs with Stasis active
            $stasisDominions = [];
            $dominions = $round->activeDominions()->get();
            $largestDominionSize = 0;

            if(static::EXTENDED_LOGGING) { Log::debug('* Going through all dominions'); }
            foreach ($dominions as $dominion)
            {
                if($dominion->getSpellPerkValue('stasis'))
                {
                    $stasisDominions[] = $dominion->id;
                }

                if(($dominion->round->ticks % 4 == 0) and !$this->protectionService->isUnderProtection($dominion) and $dominion->round->hasStarted() and !$dominion->getSpellPerkValue('fog_of_war'))
                {
                    if(static::EXTENDED_LOGGING) { Log::debug('** Capturing insight for ' . $dominion->name); }
                    $this->insightService->captureDominionInsight($dominion);
                }

                if(static::EXTENDED_LOGGING) { Log::debug('** Updating resources for ' . $dominion->name); }
                $this->handleResources($dominion);

                if(static::EXTENDED_LOGGING) { Log::debug('** Updating buildings for ' . $dominion->name); }
                $this->handleBuildings($dominion);

                if(static::EXTENDED_LOGGING){ Log::debug('** Updating improvments for ' . $dominion->name); }
                $this->handleImprovements($dominion);

                if(static::EXTENDED_LOGGING){ Log::debug('** Updating deities for ' . $dominion->name); }
                $this->handleDeities($dominion);

                if(static::EXTENDED_LOGGING) { Log::debug('** Handle Barbarians:'); }
                # NPC Barbarian: invasion, training, construction
                if($dominion->race->name === 'Barbarian')
                {
                    if(static::EXTENDED_LOGGING) { Log::debug('*** Handle Barbarian invasions for ' . $dominion->name); }
                    $this->barbarianService->handleBarbarianInvasion($dominion, $largestDominionSize);

                    if(static::EXTENDED_LOGGING) { Log::debug('*** Handle Barbarian construction for ' . $dominion->name); }
                    $this->barbarianService->handleBarbarianConstruction($dominion);
                }

                if(static::EXTENDED_LOGGING) { Log::debug('** Calculate $largestDominion'); }
                $largestDominionSize = max($this->landCalculator->getTotalLand($dominion), $largestDominionSize);
                if(static::EXTENDED_LOGGING) { Log::debug('*** $largestDominion =' . number_format($largestDominionSize)); }

                if(static::EXTENDED_LOGGING) { Log::debug('** Checking for countdown'); }
                # If we don't already have a countdown, see if any dominion triggers it.
                if(!$round->hasCountdown())
                {
                    if($this->landCalculator->getTotalLand($dominion) >= $round->land_target)
                    {
                        $hoursEndingIn = static::COUNTDOWN_DURATION_HOURS + 1;
                        $roundEnd = Carbon::now()->addHours($hoursEndingIn)->startOfHour();

                        $countdownEvent = GameEvent::create([
                            'round_id' => $dominion->round_id,
                            'source_type' => Dominion::class,
                            'source_id' => $dominion->id,
                            'target_type' => Realm::class,
                            'target_id' => $dominion->realm_id,
                            'type' => 'round_countdown',
                            'data' => ['end_date' => $roundEnd],
                        ]);
                        $dominion->save(['event' => HistoryService::EVENT_ROUND_COUNTDOWN]);
                        $round->end_date = $roundEnd;
                        $round->save();

                        if(static::EXTENDED_LOGGING) { Log::debug('*** Countdown triggered by ' . $dominion->name . ' in realm #' . $dominion->realm->number); }
                    }
                }
            }

            unset($dominions);

            if(static::EXTENDED_LOGGING) { Log::debug('* Update stasis dominions'); }
            // Scoot hour 1 Qur Stasis units back to hour 2
            foreach($stasisDominions as $stasisDominion)
            {
                $stasisDominion = Dominion::findorfail($stasisDominion);

                ## Determine how many of each unit type is returning in $tick ticks
                $tick = 1;

                foreach (range(1, 4) as $slot)
                {
                    $unitType = 'unit' . $slot;
                    for ($i = 1; $i <= 12; $i++)
                    {
                        $invasionQueueUnits[$slot][$i] = $this->queueService->getInvasionQueueAmount($stasisDominion, "military_{$unitType}", $i);
                    }
                }

                $this->queueService->setForTick(false);
                $units[1] = $this->queueService->getInvasionQueueAmount($stasisDominion, "military_unit1", $tick);
                $units[2] = $this->queueService->getInvasionQueueAmount($stasisDominion, "military_unit2", $tick);
                $units[3] = $this->queueService->getInvasionQueueAmount($stasisDominion, "military_unit3", $tick);
                $units[4] = $this->queueService->getInvasionQueueAmount($stasisDominion, "military_unit4", $tick);

                foreach($units as $slot => $amount)
                {
                      $unitType = 'military_unit'.$slot;
                      # Dequeue the units from hour 1
                      $this->queueService->dequeueResourceForHour('invasion', $stasisDominion, $unitType, $amount, $tick);
                      #echo "\nUnits dequeued";

                      # (Re-)Queue the units to hour 2
                      $this->queueService->queueResources('invasion', $stasisDominion, [$unitType => $amount], ($tick+1));
                      #echo "\nUnits requeued";
                }

                $this->queueService->setForTick(true);

            }

            if(static::EXTENDED_LOGGING) { Log::debug('* Update all dominions'); }
            $this->updateDominions($round, $stasisDominions);

            if(static::EXTENDED_LOGGING) { Log::debug('* Update all spells'); }
            $this->updateAllSpells($round);

            if(static::EXTENDED_LOGGING) { Log::debug('* Update all deities duration'); }
            $this->updateAllDeities($round);

            if(static::EXTENDED_LOGGING) { Log::debug('* Update invasion queues'); }
            $this->updateAllInvasionQueues($round);

            if(static::EXTENDED_LOGGING) { Log::debug('* Update all other queues'); }
            $this->updateAllOtherQueues($round, $stasisDominions);

            Log::info(sprintf(
                '[TICK] Ticked %s dominions in %s ms in %s',
                number_format($round->activeDominions->count()),
                number_format($this->now->diffInMilliseconds(now())),
                $round->name
            ));

            $this->now = now();
        #}
        #
        #foreach ($activeRounds as $round)
        #{
            $dominions = $round->activeDominions()
                ->with([
                    'race',
                    'race.perks',
                    'race.units',
                    'race.units.perks',
                ])
                ->get();

            $realms = $round->realms()->get();

            $spawnBarbarian = rand(1, (int)$this->barbarianCalculator->getSetting('ONE_IN_CHANCE_TO_SPAWN'));

            Log::Debug('[BARBARIAN] spawn chance value: '. $spawnBarbarian . ' (spawn if this value is 1).');

            if($spawnBarbarian === 1)
            {
                $this->barbarianService->createBarbarian($round);
            }

            if(static::EXTENDED_LOGGING){ Log::debug('* Going through all dominions again'); }
            foreach ($dominions as $dominion)
            {

                if(static::EXTENDED_LOGGING) { Log::debug('** Handle Pestilence'); }
                // Afflicted: Abomination generation
                if(!empty($dominion->tick->pestilence_units))
                {
                    $caster = Dominion::findorfail($dominion->tick->pestilence_units['caster_dominion_id']);

                    if(static::EXTENDED_LOGGING) { Log::debug('*** ' . $dominion->name . ' has pestilence from ' . $caster->name); }

                    if ($caster)
                    {
                        $this->queueService->queueResources('training', $caster, ['military_unit1' => $dominion->tick->pestilence_units['units']['military_unit1']], 12);
                    }
                }

                if(static::EXTENDED_LOGGING) { Log::debug('** Handle land generation'); }
                // Myconid: Land generation
                if(!empty($dominion->tick->generated_land) and $dominion->protection_ticks == 0)
                {
                    $homeLandType = 'land_' . $dominion->race->home_land_type;
                    $this->queueService->queueResources('exploration', $dominion, [$homeLandType => $dominion->tick->generated_land], 12);
                }

                if(static::EXTENDED_LOGGING) { Log::debug('** Handle unit generation'); }
                // Unit generation
                if(!empty($dominion->tick->generated_unit1) and $dominion->protection_ticks == 0)
                {
                    $this->queueService->queueResources('training', $dominion, ['military_unit1' => $dominion->tick->generated_unit1], 12);
                }
                if(!empty($dominion->tick->generated_unit2) and $dominion->protection_ticks == 0)
                {
                    $this->queueService->queueResources('training', $dominion, ['military_unit2' => $dominion->tick->generated_unit2], 12);
                }
                if(!empty($dominion->tick->generated_unit3) and $dominion->protection_ticks == 0)
                {
                    $this->queueService->queueResources('training', $dominion, ['military_unit3' => $dominion->tick->generated_unit3], 12);
                }
                if(!empty($dominion->tick->generated_unit4) and $dominion->protection_ticks == 0)
                {
                    $this->queueService->queueResources('training', $dominion, ['military_unit4' => $dominion->tick->generated_unit4], 12);
                }


                DB::transaction(function () use ($dominion)
                {
                    if(static::EXTENDED_LOGGING) { Log::debug('** Handle starvation for ' . $dominion->name); }
                    if($dominion->tick->starvation_casualties > 0 and !$dominion->isAbandoned())
                    {
                        $this->notificationService->queueNotification('starvation_occurred');
                    }

                    if(static::EXTENDED_LOGGING) { Log::debug('** Handle unit attrition for ' . $dominion->name); }
                    if(
                        (
                          isset($dominion->tick->attrition_unit1) or
                          isset($dominion->tick->attrition_unit2) or
                          isset($dominion->tick->attrition_unit3) or
                          isset($dominion->tick->attrition_unit4)
                        )
                        and array_sum([$dominion->tick->attrition_unit1, $dominion->tick->attrition_unit2, $dominion->tick->attrition_unit3, $dominion->tick->attrition_unit4]) > 0
                        and !$dominion->isAbandoned()
                      )
                    {
                        $this->notificationService->queueNotification('attrition_occurred',[$dominion->tick->attrition_unit1, $dominion->tick->attrition_unit2, $dominion->tick->attrition_unit3, $dominion->tick->attrition_unit4]);
                    }

                    if(static::EXTENDED_LOGGING) { Log::debug('** Cleaning up active spells'); }
                    $this->cleanupActiveSpells($dominion);

                    if(static::EXTENDED_LOGGING) { Log::debug('** Cleaning up queues'); }
                    $this->cleanupQueues($dominion);

                    if(static::EXTENDED_LOGGING) { Log::debug('** Sending notifications (hourly_dominion)'); }
                    $this->notificationService->sendNotifications($dominion, 'hourly_dominion');

                    if(static::EXTENDED_LOGGING) { Log::debug('** Precalculate tick'); }
                    $this->precalculateTick($dominion, true);

                });
            }

            foreach($realms as $realm)
            {
                if($realm->crypt > 0)
                {
                    # Imperial Crypt: handle decay (handleDecay)
                    $bodiesDecayed = $this->realmCalculator->getCryptBodiesDecayed($realm);

                    $bodiesSpent = DB::table('dominion_tick')
                                 ->join('dominions', 'dominion_tick.dominion_Id', '=', 'dominions.id')
                                 ->join('races', 'dominions.race_id', '=', 'races.id')
                                 ->select(DB::raw("SUM(crypt_bodies_spent) as cryptBodiesSpent"))
                                 ->where('dominions.round_id', '=', $realm->round->id)
                                 ->where('races.name', '=', 'Undead')
                                 ->where('dominions.protection_ticks', '=', 0)
                                 ->where('dominions.is_locked', '=', 0)
                                 ->first();

                     $bodiesToRemove = intval($bodiesDecayed + $bodiesSpent->cryptBodiesSpent);
                     $bodiesToRemove = max(0, min($bodiesToRemove, $realm->crypt));

                     $cryptLogString = '[CRYPT] ';
                     $cryptLogString .= "Bodies current: " . number_format($realm->crypt) . ". ";
                     $cryptLogString .= "Bodies decayed: " . number_format($bodiesDecayed) . ". ";
                     $cryptLogString .= "Bodies spent: " . number_format($bodiesSpent->cryptBodiesSpent) . ". ";
                     $cryptLogString .= "Bodies to remove: " . number_format($bodiesToRemove) . ". ";


                    $realm->fill([
                        'crypt' => ($realm->crypt - $bodiesToRemove),
                    ])->save();

                    Log::info($cryptLogString);
                }
            }

            Log::info(sprintf(
                '[QUEUES] Cleaned up queues, sent notifications, and precalculated %s dominions in %s ms in %s',
                number_format($round->activeDominions->count()),
                number_format($this->now->diffInMilliseconds(now())),
                $round->name
            ));

            $this->now = now();

           $round->fill([
               'ticks' => ($round->ticks + 1),
           ])->save();
        }
    }

    /**
     * Does a daily tick on all active dominions and rounds.
     *
     * @throws Exception|Throwable
     */
    public function tickDaily()
    {
        Log::debug('[DAILY] Daily tick started');

        DB::transaction(function () {
            foreach (Round::with('dominions')->active()->get() as $round) {
                // toBase required to prevent ambiguous updated_at column in query
                $round->dominions()->toBase()->update([
                    'daily_gold' => false,
                    'daily_land' => false,
                ], [
                    'event' => 'tick',
                ]);
            }
        });

        Log::info('[DAILY] Daily tick finished');
    }

    protected function cleanupActiveSpells(Dominion $dominion)
    {
        $finished = DB::table('dominion_spells')
            ->where('dominion_id', $dominion->id)
            ->where('duration', '<=', 0)
            ->get();

        $beneficialSpells = [];
        $harmfulSpells = [];

        foreach ($finished as $row)
        {
            $spell = Spell::where('id', $row->spell_id)->first();

            if ($row->caster_id == $dominion->id)
            {
                $beneficialSpells[] = $spell->key;
            }
            else
            {
                $harmfulSpells[] = $spell->key;
            }
        }

        if (!empty($beneficialSpells) and !$dominion->isAbandoned())
        {
            $this->notificationService->queueNotification('beneficial_magic_dissipated', $beneficialSpells);
        }

        if (!empty($harmfulSpells) and !$dominion->isAbandoned())
        {
            $this->notificationService->queueNotification('harmful_magic_dissipated', $harmfulSpells);
        }

        DB::table('dominion_spells')
            ->where('dominion_id', $dominion->id)
            ->where('duration', '<=', 0)
            ->delete();
    }

    protected function cleanupQueues(Dominion $dominion)
    {
        $finished = DB::table('dominion_queue')
            ->where('dominion_id', $dominion->id)
            ->where('hours', '<=', 0)
            ->get();

        foreach ($finished->groupBy('source') as $source => $group)
        {
            if(!$dominion->isAbandoned())
            {
                $resources = [];
                foreach ($group as $row)
                {
                    $resources[$row->resource] = $row->amount;
                }

                if ($source === 'invasion')
                {
                    $notificationType = 'returning_completed';
                }
                else
                {
                    $notificationType = "{$source}_completed";
                }

                $this->notificationService->queueNotification($notificationType, $resources);
            }
        }


        DB::transaction(function () use ($dominion)
        {
            DB::table('dominion_queue')
                ->where('dominion_id', $dominion->id)
                ->where('hours', '<=', 0)
                ->delete();
        }, 10);

    }

    public function precalculateTick(Dominion $dominion, ?bool $saveHistory = false): void
    {

        /** @var Tick $tick */
        $tick = Tick::firstOrCreate(['dominion_id' => $dominion->id]);

          if ($saveHistory)
          {
              // Save a dominion history record
              $dominionHistoryService = app(HistoryService::class);

              $changes = array_filter($tick->getAttributes(), static function ($value, $key)
              {
                  return (
                      !in_array($key, [
                          'id',
                          'dominion_id',
                          'created_at',
                          'updated_at'
                      ], true) &&
                      ($value != 0) // todo: strict type checking?
                  );
              }, ARRAY_FILTER_USE_BOTH);

              $dominionHistoryService->record($dominion, $changes, HistoryService::EVENT_TICK);
          }

          // Reset tick values
          foreach ($tick->getAttributes() as $attr => $value)
          {
              if (!in_array($attr, ['id', 'dominion_id', 'updated_at', 'pestilence_units', 'generated_land', 'generated_unit1', 'generated_unit2', 'generated_unit3', 'generated_unit4'], true))
              {
                    $tick->{$attr} = 0;
              }
              elseif (in_array($attr, ['starvation_casualties', 'pestilence_units', 'generated_land', 'generated_unit1', 'generated_unit2', 'generated_unit3', 'generated_unit4'/*, 'attrition_unit1', 'attrition_unit2', 'attrition_unit3', 'attrition_unit4'*/], true))
              {
                    $tick->{$attr} = [];
              }
          }

          // Hacky refresh for dominion
          $dominion->refresh();

          // Queues
          $incomingQueue = DB::table('dominion_queue')
              ->where('dominion_id', $dominion->id)
              ->where('source', '!=', 'construction')
              ->where('hours', '=', 1)
              ->get();

          foreach ($incomingQueue as $row)
          {
              if($row->source !== 'deity')
              {
                  $tick->{$row->resource} += $row->amount;
                  // Temporarily add next hour's resources for accurate calculations
                  $dominion->{$row->resource} += $row->amount;
              }
          }

          if($dominion->race->name == 'Barbarian')
          {
              if(static::EXTENDED_LOGGING) { Log::debug('*** Handle Barbarian training for ' . $dominion->name); }
              $this->barbarianService->handleBarbarianTraining($dominion);
          }

          $tick->protection_ticks = 0;
          // Tick
          if($dominion->protection_ticks > 0)
          {
              $tick->protection_ticks += -1;
          }

          // Population
          $drafteesGrowthRate = $this->populationCalculator->getPopulationDrafteeGrowth($dominion);
          $populationPeasantGrowth = $this->populationCalculator->getPopulationPeasantGrowth($dominion);

          if ($this->spellCalculator->isSpellActive($dominion, 'pestilence'))
          {
              $spell = Spell::where('key', 'pestilence')->first();
              $pestilence = $spell->getActiveSpellPerkValues('pestilence', 'kills_peasants_and_converts_for_caster_unit');
              $ratio = $pestilence[0] / 100;
              $slot = $pestilence[1];
              $caster = $this->spellCalculator->getCaster($dominion, 'pestilence');

              $amountToDie = $dominion->peasants * $ratio * $this->spellDamageCalculator->getDominionHarmfulSpellDamageModifier($dominion, null, Spell::where('key', 'pestilence')->first(), null);
              $amountToDie *= (1 - $dominion->race->getPerkMultiplier('reduced_conversions'));
              $amountToDie = (int)round($amountToDie);

              $tick->pestilence_units = ['caster_dominion_id' => $caster->id, 'units' => ['military_unit1' => $amountToDie]];

              $populationPeasantGrowth -= $amountToDie;
          }


          if(($dominion->peasants + $tick->peasants) <= 0)
          {
              $tick->peasants = ($dominion->peasants)*-1;
          }

          $tick->peasants = $populationPeasantGrowth;

          $tick->peasants_sacrificed = min($this->populationCalculator->getPeasantsSacrificed($dominion), ($dominion->peasants + $tick->peasants)) * -1;

          $tick->peasants_sacrificed = max($tick->peasants_sacrificed, ($dominion->peasants + $tick->peasants)*-1);

          $tick->military_draftees = $drafteesGrowthRate;

          $tick->military_draftees += $this->productionCalculator->getDrafteesGenerated($dominion, $drafteesGrowthRate);

          // Resources
          $tick->resource_gold += $this->productionCalculator->getGoldProduction($dominion);
          $tick->resource_lumber += $this->productionCalculator->getLumberProduction($dominion);
          $tick->resource_mana += $this->productionCalculator->getManaNetChange($dominion);
          $tick->resource_food += $this->productionCalculator->getFoodNetChange($dominion);
          $tick->resource_ore += $this->productionCalculator->getOreProduction($dominion);
          $tick->resource_gems += $this->productionCalculator->getGemProduction($dominion);
          $tick->xp += $this->productionCalculator->getXpGeneration($dominion);
          $tick->resource_soul += $this->productionCalculator->getSoulProduction($dominion);
          $tick->resource_blood += $this->productionCalculator->getBloodProduction($dominion);

          # Decay, rot, drain
          $tick->resource_food_consumption += $this->productionCalculator->getFoodConsumption($dominion);
          $tick->resource_food_decay += 0;
          $tick->resource_lumber_rot += 0;
          $tick->resource_mana_drain += 0;

          # Contribution: how much is LOST (GIVEN AWAY)
          $tick->resource_food_contribution = $this->productionCalculator->getContribution($dominion, 'food');
          $tick->resource_mana_contribution = $this->productionCalculator->getContribution($dominion, 'mana');

          # Contributed: how much is RECEIVED (GIVEN TO)
          $tick->resource_food_contributed = 0;
          $tick->resource_mana_contributed = 0;
          if($dominion->race->name == 'Monster')
          {
              $totalContributions = $this->realmCalculator->getTotalContributions($dominion->realm);
              $tick->resource_food_contributed = $totalContributions['food'];
              $tick->resource_mana_contributed = $totalContributions['mana'];
          }

          $tick->prestige += $this->productionCalculator->getPrestigeInterest($dominion);

          // Starvation
          $tick->starvation_casualties = 0;
          if(($dominion->resource_food + $tick->resource_food) <= 0 and !$dominion->race->getPerkValue('no_food_consumption'))
          {
              $tick->starvation_casualties = 1;
              $tick->resource_food = ($dominion->resource_food)*-1;
          }

          // Morale
          $baseMorale = 100;
          $baseMoraleModifier = $this->militaryCalculator->getBaseMoraleModifier($dominion, $this->populationCalculator->getPopulation($dominion));
          $baseMorale *= (1 + $baseMoraleModifier);
          $baseMorale = intval($baseMorale);

          $moraleChangeModifier = (1 + $dominion->race->getPerkMultiplier('morale_change_tick') + $dominion->race->getPerkMultiplier('morale_change_tick'));

          if($tick->starvation_casualties)
          {
              # Lower morale by 10.
              $starvationMoraleChange = -10;
              if(($dominion->morale + $starvationMoraleChange) < 0)
              {
                  $tick->morale = -$dominion->morale;
              }
              else
              {
                  $tick->morale = $starvationMoraleChange;
              }
          }
          else
          {
              if ($dominion->morale < 35)
              {
                  $tick->morale = 7;
              }
              elseif ($dominion->morale < 70)
              {
                  $tick->morale = 6;
              }
              elseif ($dominion->morale < $baseMorale)
              {
                  $tick->morale = min(3, $baseMorale - $dominion->morale);
              }
              elseif($dominion->morale > $baseMorale)
              {
                  $tick->morale -= min(2 * $moraleChangeModifier, $dominion->morale - $baseMorale);
              }
          }

          // Spy Strength
          if ($dominion->spy_strength < 100)
          {
              $spyStrengthAdded = 4;
              $spyStrengthAdded += $dominion->getBuildingPerkValue('spy_strength_recovery');
              $spyStrengthAdded += $dominion->getTechPerkValue('spy_strength_recovery');
              $spyStrengthAdded += $dominion->getSpellPerkValue('spy_strength_recovery');
              $spyStrengthAdded += $dominion->title->getPerkValue('spy_strength_recovery') * $dominion->title->getPerkBonus($dominion);

              $spyStrengthAdded = floor($spyStrengthAdded);

              $tick->spy_strength = min($spyStrengthAdded, 100 - $dominion->spy_strength);
          }

          // Wizard Strength
          if ($dominion->wizard_strength < 100)
          {
              $wizardStrengthAdded = 4;

              $wizardStrengthAdded += $dominion->getBuildingPerkValue('wizard_strength_recovery');
              $wizardStrengthAdded += $dominion->getTechPerkValue('wizard_strength_recovery');
              $wizardStrengthAdded += $dominion->getSpellPerkValue('wizard_strength_recovery');
              $wizardStrengthAdded += $dominion->title->getPerkValue('wizard_strength_recovery') * $dominion->title->getPerkBonus($dominion);

              $wizardStrengthAdded = floor($wizardStrengthAdded);

              $tick->wizard_strength = min($wizardStrengthAdded, 100 - $dominion->wizard_strength);
          }

          # Tickly unit perks
          $generatedLand = 0;

          $generatedUnit1 = 0;
          $generatedUnit2 = 0;
          $generatedUnit3 = 0;
          $generatedUnit4 = 0;

          $attritionUnit1 = 0;
          $attritionUnit2 = 0;
          $attritionUnit3 = 0;
          $attritionUnit4 = 0;

          # Cult unit attrition reduction
          $attritionMultiplier = 0;
          if($dominion->race->name == 'Cult')
          {
              $attritionMultiplier -= $dominion->military_unit3 / max($this->populationCalculator->getPopulationMilitary($dominion),1);
              $attritionMultiplier -= $dominion->getBuildingPerkMultiplier('reduces_attrition');
          }

          # Cap at -100%
          $attritionMultiplier = max(-1, $attritionMultiplier);

          // Check for no-attrition perks.
          if($dominion->getSpellPerkValue('no_attrition'))
          {
              $attritionMultiplier = -1;
          }

          for ($slot = 1; $slot <= 4; $slot++)
          {
              // Myconid: Land generation
              if($dominion->race->getUnitPerkValueForUnitSlot($slot, 'land_per_tick'))
              {
                  $generatedLand += $dominion->{"military_unit".$slot} * $dominion->race->getUnitPerkValueForUnitSlot($slot, 'land_per_tick');
                  $generatedLand = max($generatedLand, 0);

                  # Defensive Warts turn off land generation
                  if($dominion->getSpellPerkValue('stop_land_generation'))
                  {
                      $generatedLand = 0;
                  }
              }

              $availablePopulation = $this->populationCalculator->getMaxPopulation($dominion) - $this->populationCalculator->getPopulationMilitary($dominion);

              // Myconid and Cult: Unit generation
              if($unitGenerationPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'unit_production'))
              {
                  $unitToGenerateSlot = $unitGenerationPerk[0];
                  $unitAmountToGeneratePerGeneratingUnit = $unitGenerationPerk[1];
                  $unitAmountToGenerate = $dominion->{'military_unit'.$slot} * $unitAmountToGeneratePerGeneratingUnit;

                  #echo $dominion->name . " has " . number_format($dominion->{'military_unit'.$slot}) . " unit" . $slot .", which generate " . $unitAmountToGeneratePerGeneratingUnit . " per tick. Total generation is " . $unitAmountToGenerate . " unit" . $unitToGenerateSlot . ". Available population: " . number_format($availablePopulation) . "\n";

                  $unitAmountToGenerate = max(0, min($unitAmountToGenerate, $availablePopulation));

                  #echo "\tAmount generated: " . $unitAmountToGenerate . "\n\n";

                  if($unitToGenerateSlot == 1)
                  {
                      $generatedUnit1 += $unitAmountToGenerate;
                  }
                  elseif($unitToGenerateSlot == 2)
                  {
                      $generatedUnit2 += $unitAmountToGenerate;
                  }
                  elseif($unitToGenerateSlot == 3)
                  {
                      $generatedUnit3 += $unitAmountToGenerate;
                  }
                  elseif($unitToGenerateSlot == 4)
                  {
                      $generatedUnit4 += $unitAmountToGenerate;
                  }

                  $availablePopulation -= $unitAmountToGenerate;
              }

              // Unit attrition
              if($unitAttritionPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'attrition'))
              {
                  $unitAttritionAmount = intval($dominion->{'military_unit'.$slot} * $unitAttritionPerk/100 * (1 + $attritionMultiplier));
                  #echo $dominion->name . " has " . number_format($dominion->{'military_unit'.$slot}) . " unit" . $slot .", which has an attrition rate of " . $unitAttritionPerk . "%. " . number_format($unitAttritionAmount) . " will abandon.\n";
                  $unitAttritionAmount = max(0, min($unitAttritionAmount, $dominion->{'military_unit'.$slot})); # Sanity caps.

                  if($slot == 1)
                  {
                      $attritionUnit1 += $unitAttritionAmount;
                  }
                  elseif($slot == 2)
                  {
                      $attritionUnit2 += $unitAttritionAmount;
                  }
                  elseif($slot == 3)
                  {
                      $attritionUnit3 += $unitAttritionAmount;
                  }
                  elseif($slot == 4)
                  {
                      $attritionUnit4 += $unitAttritionAmount;
                  }
              }
          }

          # Imperial Crypt: Rites of Zidur, Rites of Kinthys
          $tick->crypt_bodies_spent = 0;

          # Version 1.4 (Round 50, no Necromancer pairing limit)
          # Version 1.3 (Round 42, Spells 2.0 compatible-r)
          if ($this->spellCalculator->isSpellActive($dominion, 'rites_of_zidur'))
          {
              $spell = Spell::where('key', 'rites_of_zidur')->first();

              $spellPerkValues = $spell->getActiveSpellPerkValues($spell->key, 'converts_crypt_bodies');

              # Check bodies available in the crypt
              $bodiesAvailable = max(0, floor($dominion->realm->crypt - $tick->crypt_bodies_spent));

              # Break down the spell perk
              $raisersPerRaisedUnit = (int)$spellPerkValues[0];
              $raisingUnitSlot = (int)$spellPerkValues[1];
              $unitRaisedSlot = (int)$spellPerkValues[2];

              $unitsRaised = $dominion->{'military_unit' . $raisingUnitSlot} / $raisersPerRaisedUnit;

              $unitsRaised = max(0, min($unitsRaised, $bodiesAvailable));

              $tick->{'generated_unit' . $unitRaisedSlot} += $unitsRaised;
              $tick->crypt_bodies_spent += $unitsRaised;
          }
          if ($this->spellCalculator->isSpellActive($dominion, 'rites_of_kinthys'))
          {
              $spell = Spell::where('key', 'rites_of_kinthys')->first();

              $spellPerkValues = $spell->getActiveSpellPerkValues($spell->key, 'converts_crypt_bodies');

              # Check bodies available in the crypt
              $bodiesAvailable = max(0, floor($dominion->realm->crypt - $tick->crypt_bodies_spent));

              # Break down the spell perk
              $raisersPerRaisedUnit = (int)$spellPerkValues[0];
              $raisingUnitSlot = (int)$spellPerkValues[1];
              $unitRaisedSlot = (int)$spellPerkValues[2];

              $unitsRaised = $dominion->{'military_unit' . $raisingUnitSlot} / $raisersPerRaisedUnit;

              $unitsRaised = max(0, min($unitsRaised, $bodiesAvailable));

              $tick->{'generated_unit' . $unitRaisedSlot} += $unitsRaised;
              $tick->crypt_bodies_spent += $unitsRaised;
          }

          # Snow Elf: Gryphon Nests generate Gryphons
          if($dominion->race->getPerkValue('gryphon_nests_generate_gryphons'))
          {
              $gryphonSlot = 4;
              $newGryphons = 0;
              $maxGryphonNestsPercentage = 0.20;
              $gryphonNestsOwned = $this->buildingCalculator->getBuildingAmountOwned($dominion, null, 'gryphon_nest', null);
              $gryphonNestsPercentage = min($maxGryphonNestsPercentage, ($gryphonNestsOwned / $this->landCalculator->getTotalLand($dominion)));

              $gryphonNests = floor($gryphonNestsPercentage * $this->landCalculator->getTotalLand($dominion));
              $gryphonsMax = $gryphonNests * 1 * (1 + $this->prestigeCalculator->getPrestigeMultiplier($dominion));

              $gryphonsCurrent = $this->militaryCalculator->getTotalUnitsForSlot($dominion, $gryphonSlot);
              $gryphonsCurrent += $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit'.$gryphonSlot);

              $availableNestHousing = $gryphonsMax - $gryphonsCurrent;

              if($availableNestHousing > 0)
              {
                  $newGryphons = min($gryphonNests * 0.05, $availableNestHousing);
              }

              $tick->generated_unit4 = $newGryphons;
          }

          # Use decimals as probability to round up
          $tick->generated_land += intval($generatedLand) + (rand()/getrandmax() < fmod($generatedLand, 1) ? 1 : 0);

          $tick->generated_unit1 += intval($generatedUnit1) + (rand()/getrandmax() < fmod($generatedUnit1, 1) ? 1 : 0);
          $tick->generated_unit2 += intval($generatedUnit2) + (rand()/getrandmax() < fmod($generatedUnit2, 1) ? 1 : 0);
          $tick->generated_unit3 += intval($generatedUnit3) + (rand()/getrandmax() < fmod($generatedUnit3, 1) ? 1 : 0);
          $tick->generated_unit4 += intval($generatedUnit4) + (rand()/getrandmax() < fmod($generatedUnit4, 1) ? 1 : 0);

          $tick->attrition_unit1 += intval($attritionUnit1);
          $tick->attrition_unit2 += intval($attritionUnit2);
          $tick->attrition_unit3 += intval($attritionUnit3);
          $tick->attrition_unit4 += intval($attritionUnit4);

          foreach ($incomingQueue as $row)
          {
              if($row->source !== 'deity')
              {
                  // Reset current resources in case object is saved later
                  $dominion->{$row->resource} -= $row->amount;
              }
          }

          $tick->save();
    }

    # SINGLE DOMINION TICKS, MANUAL TICK
    /**
     * Does an hourly tick on all active dominions.
     *
     * @throws Exception|Throwable
     */
    public function tickManually(Dominion $dominion)
    {

        Log::debug(sprintf(
            '[TICK] Manual tick started for %s.',
            $dominion->name
        ));

        $this->precalculateTick($dominion, true);

        $this->handleResources($dominion);
        $this->handleBuildings($dominion);
        $this->handleImprovements($dominion);
        $this->handleDeities($dominion);

        $this->updateDominion($dominion);
        $this->updateDominionSpells($dominion);
        $this->updateDominionDeity($dominion);
        $this->updateDominionQueues($dominion);

        Log::info(sprintf(
            '[TICK] Ticked dominion %s in %s ms.',
            $dominion->name,
            number_format($this->now->diffInMilliseconds(now()))
        ));

        $this->now = now();

        # Starvation
        DB::transaction(function () use ($dominion)
        {
            # Send starvation notification.
            if($dominion->tick->starvation_casualties > 0 and !$dominion->isAbandoned())
            {
                $this->notificationService->queueNotification('starvation_occurred');
            }

            if(array_sum([$dominion->tick->attrition_unit1, $dominion->tick->attrition_unit2, $dominion->tick->attrition_unit3, $dominion->tick->attrition_unit4]) > 0 and !$dominion->isAbandoned())
            {
                $this->notificationService->queueNotification('attrition_occurred',[$dominion->tick->attrition_unit1, $dominion->tick->attrition_unit2, $dominion->tick->attrition_unit3, $dominion->tick->attrition_unit4]);
            }

            # Clean up
            $this->cleanupActiveSpells($dominion);
            $this->cleanupQueues($dominion);

            $this->notificationService->sendNotifications($dominion, 'hourly_dominion');

            $this->precalculateTick($dominion, true);

        });

        // Myconid: Land generation
        if(!empty($dominion->tick->generated_land) and $dominion->protection_ticks > 0)
        {
            $homeLandType = 'land_' . $dominion->race->home_land_type;
            $this->queueService->queueResources('exploration', $dominion, [$homeLandType => $dominion->tick->generated_land], 12);
        }

        // Myconid and Cult: Unit generation
        if(!empty($dominion->tick->generated_unit1) and $dominion->protection_ticks > 0)
        {
            $this->queueService->queueResources('training', $dominion, ['military_unit1' => $dominion->tick->generated_unit1], 12);
        }
        if(!empty($dominion->tick->generated_unit2) and $dominion->protection_ticks > 0)
        {
            $this->queueService->queueResources('training', $dominion, ['military_unit2' => $dominion->tick->generated_unit2], 12);
        }
        if(!empty($dominion->tick->generated_unit3) and $dominion->protection_ticks > 0)
        {
            $this->queueService->queueResources('training', $dominion, ['military_unit3' => $dominion->tick->generated_unit3], 12);
        }
        if(!empty($dominion->tick->generated_unit4) and $dominion->protection_ticks > 0)
        {
            $this->queueService->queueResources('training', $dominion, ['military_unit4' => $dominion->tick->generated_unit4], 12);
        }

        Log::info(sprintf(
            '[TICK] Cleaned up queues, sent notifications, and precalculated dominion %s in %s ms.',
            $dominion->name,
            number_format($this->now->diffInMilliseconds(now()))
        ));

        $this->now = now();
    }

    # TICK SERVICE 1.1 functions

    // Update dominions
    private function updateDominions(Round $round, array $stasisDominions)
    {
        DB::table('dominions')
            ->join('dominion_tick', 'dominions.id', '=', 'dominion_tick.dominion_id')
            ->where('dominions.round_id', $round->id)
            ->where('dominions.is_locked', false)
            ->whereNotIn('dominion_tick.dominion_id', $stasisDominions)
            ->where('dominions.protection_ticks', '=', 0)
            ->update([
                'dominions.prestige' => DB::raw('dominions.prestige + dominion_tick.prestige'),
                'dominions.xp' => DB::raw('dominions.xp + dominion_tick.xp'),
                'dominions.peasants' => DB::raw('dominions.peasants + dominion_tick.peasants + dominion_tick.peasants_sacrificed'),
                'dominions.peasants_last_hour' => DB::raw('dominion_tick.peasants'),
                'dominions.morale' => DB::raw('dominions.morale + dominion_tick.morale'),
                'dominions.spy_strength' => DB::raw('dominions.spy_strength + dominion_tick.spy_strength'),
                'dominions.wizard_strength' => DB::raw('dominions.wizard_strength + dominion_tick.wizard_strength'),

                /*
                'dominions.resource_gold' => DB::raw('dominions.resource_gold + dominion_tick.resource_gold'),
                'dominions.resource_food' => DB::raw('dominions.resource_food + dominion_tick.resource_food + dominion_tick.resource_food_contributed'),
                'dominions.resource_lumber' => DB::raw('dominions.resource_lumber + dominion_tick.resource_lumber'),
                'dominions.resource_mana' => DB::raw('dominions.resource_mana + dominion_tick.resource_mana + dominion_tick.resource_mana_contributed'),
                'dominions.resource_ore' => DB::raw('dominions.resource_ore + dominion_tick.resource_ore'),
                'dominions.resource_gems' => DB::raw('dominions.resource_gems + dominion_tick.resource_gems'),
                'dominions.resource_champion' => DB::raw('dominions.resource_champion + dominion_tick.resource_champion'),
                'dominions.resource_soul' => DB::raw('dominions.resource_soul + dominion_tick.resource_soul'),
                'dominions.resource_blood' => DB::raw('dominions.resource_blood + dominion_tick.resource_blood'),
                */

                'dominions.military_draftees' => DB::raw('dominions.military_draftees + dominion_tick.military_draftees'),
                'dominions.military_unit1' => DB::raw('dominions.military_unit1 + dominion_tick.military_unit1 - dominion_tick.attrition_unit1'),
                'dominions.military_unit2' => DB::raw('dominions.military_unit2 + dominion_tick.military_unit2 - dominion_tick.attrition_unit2'),
                'dominions.military_unit3' => DB::raw('dominions.military_unit3 + dominion_tick.military_unit3 - dominion_tick.attrition_unit3'),
                'dominions.military_unit4' => DB::raw('dominions.military_unit4 + dominion_tick.military_unit4 - dominion_tick.attrition_unit4'),
                'dominions.military_spies' => DB::raw('dominions.military_spies + dominion_tick.military_spies'),
                'dominions.military_wizards' => DB::raw('dominions.military_wizards + dominion_tick.military_wizards'),
                'dominions.military_archmages' => DB::raw('dominions.military_archmages + dominion_tick.military_archmages'),

                'dominions.land_plain' => DB::raw('dominions.land_plain + dominion_tick.land_plain'),
                'dominions.land_mountain' => DB::raw('dominions.land_mountain + dominion_tick.land_mountain'),
                'dominions.land_swamp' => DB::raw('dominions.land_swamp + dominion_tick.land_swamp'),
                'dominions.land_cavern' => DB::raw('dominions.land_cavern + dominion_tick.land_cavern'),
                'dominions.land_forest' => DB::raw('dominions.land_forest + dominion_tick.land_forest'),
                'dominions.land_hill' => DB::raw('dominions.land_hill + dominion_tick.land_hill'),
                'dominions.land_water' => DB::raw('dominions.land_water + dominion_tick.land_water'),

                'dominions.protection_ticks' => DB::raw('dominions.protection_ticks + dominion_tick.protection_ticks'),

                'dominions.last_tick_at' => DB::raw('now()')
            ]);
    }

    // Update dominion: used for tickManually
    private function updateDominion(Dominion $dominion)
    {
        DB::table('dominions')
            ->join('dominion_tick', 'dominions.id', '=', 'dominion_tick.dominion_id')
            ->where('dominions.id', $dominion->id)
            ->where('dominions.protection_ticks', '>', 0)
            ->where('dominions.is_locked', false)
            ->update([
                'dominions.prestige' => DB::raw('dominions.prestige + dominion_tick.prestige'),
                'dominions.xp' => DB::raw('dominions.xp + dominion_tick.xp'),
                'dominions.peasants' => DB::raw('dominions.peasants + dominion_tick.peasants + dominion_tick.peasants_sacrificed'),
                'dominions.peasants_last_hour' => DB::raw('dominion_tick.peasants'),
                'dominions.morale' => DB::raw('dominions.morale + dominion_tick.morale'),
                'dominions.spy_strength' => DB::raw('dominions.spy_strength + dominion_tick.spy_strength'),
                'dominions.wizard_strength' => DB::raw('dominions.wizard_strength + dominion_tick.wizard_strength'),

                /*
                'dominions.resource_gold' => DB::raw('dominions.resource_gold + dominion_tick.resource_gold'),
                'dominions.resource_food' => DB::raw('dominions.resource_food + dominion_tick.resource_food'),
                'dominions.resource_lumber' => DB::raw('dominions.resource_lumber + dominion_tick.resource_lumber'),
                'dominions.resource_mana' => DB::raw('dominions.resource_mana + dominion_tick.resource_mana'),
                'dominions.resource_ore' => DB::raw('dominions.resource_ore + dominion_tick.resource_ore'),
                'dominions.resource_gems' => DB::raw('dominions.resource_gems + dominion_tick.resource_gems'),
                'dominions.resource_champion' => DB::raw('dominions.resource_champion + dominion_tick.resource_champion'),
                'dominions.resource_soul' => DB::raw('dominions.resource_soul + dominion_tick.resource_soul'),
                'dominions.resource_blood' => DB::raw('dominions.resource_blood + dominion_tick.resource_blood'),
                */

                'dominions.military_draftees' => DB::raw('dominions.military_draftees + dominion_tick.military_draftees'),
                'dominions.military_unit1' => DB::raw('dominions.military_unit1 + dominion_tick.military_unit1 - dominion_tick.attrition_unit1'),
                'dominions.military_unit2' => DB::raw('dominions.military_unit2 + dominion_tick.military_unit2 - dominion_tick.attrition_unit2'),
                'dominions.military_unit3' => DB::raw('dominions.military_unit3 + dominion_tick.military_unit3 - dominion_tick.attrition_unit3'),
                'dominions.military_unit4' => DB::raw('dominions.military_unit4 + dominion_tick.military_unit4 - dominion_tick.attrition_unit4'),
                'dominions.military_spies' => DB::raw('dominions.military_spies + dominion_tick.military_spies'),
                'dominions.military_wizards' => DB::raw('dominions.military_wizards + dominion_tick.military_wizards'),
                'dominions.military_archmages' => DB::raw('dominions.military_archmages + dominion_tick.military_archmages'),

                'dominions.land_plain' => DB::raw('dominions.land_plain + dominion_tick.land_plain'),
                'dominions.land_mountain' => DB::raw('dominions.land_mountain + dominion_tick.land_mountain'),
                'dominions.land_swamp' => DB::raw('dominions.land_swamp + dominion_tick.land_swamp'),
                'dominions.land_cavern' => DB::raw('dominions.land_cavern + dominion_tick.land_cavern'),
                'dominions.land_forest' => DB::raw('dominions.land_forest + dominion_tick.land_forest'),
                'dominions.land_hill' => DB::raw('dominions.land_hill + dominion_tick.land_hill'),
                'dominions.land_water' => DB::raw('dominions.land_water + dominion_tick.land_water'),

                'dominions.protection_ticks' => DB::raw('dominions.protection_ticks + dominion_tick.protection_ticks'),

                'dominions.last_tick_at' => DB::raw('now()')
            ]);
    }

    // Update spells for a specific dominion
    private function updateDominionSpells(Dominion $dominion): void
    {
        DB::table('dominion_spells')
            ->join('dominions', 'dominion_spells.dominion_id', '=', 'dominions.id')
            ->where('dominions.id', $dominion->id)
            ->update([
                'duration' => DB::raw('`duration` - 1'),
                'dominion_spells.updated_at' => $this->now,
            ]);
    }

    // Update deity duration for a specific dominion
    private function updateDominionDeity(Dominion $dominion): void
    {
        DB::table('dominion_deity')
            ->join('dominions', 'dominion_deity.dominion_id', '=', 'dominions.id')
            ->where('dominions.id', $dominion->id)
            ->update([
                'duration' => DB::raw('`duration` + 1'),
                'dominion_deity.updated_at' => $this->now,
            ]);
    }

    // Update queues for a specific dominion
    private function updateDominionQueues(Dominion $dominion): void
    {
        DB::table('dominion_queue')
            ->join('dominions', 'dominion_queue.dominion_id', '=', 'dominions.id')
            ->where('dominions.id', $dominion->id)
            ->update([
                'hours' => DB::raw('`hours` - 1'),
                'dominion_queue.updated_at' => $this->now,
            ]);
    }

    // Update spells for all dominions
    private function updateAllSpells(Round $round): void
    {
        DB::table('dominion_spells')
            ->join('dominions', 'dominion_spells.dominion_id', '=', 'dominions.id')
            ->where('dominions.round_id', $round->id)
            ->where('dominions.protection_ticks', '=', 0)
            ->update([
                'duration' => DB::raw('`duration` - 1'),
                'dominion_spells.updated_at' => $this->now,
            ]);
    }

    // Update deities duration for all dominions
    private function updateAllDeities(Round $round): void
    {
        DB::table('dominion_deity')
            ->join('dominions', 'dominion_deity.dominion_id', '=', 'dominions.id')
            ->where('dominions.round_id', $round->id)
            ->update([
                'duration' => DB::raw('`duration` + 1'),
                'dominion_deity.updated_at' => $this->now,
            ]);
    }

    // Update invasion queues for all dominions
    private function updateAllInvasionQueues(Round $round): void
    {
        DB::table('dominion_queue')
            ->join('dominions', 'dominion_queue.dominion_id', '=', 'dominions.id')
            ->where('dominions.round_id', $round->id)
            ->where('dominions.protection_ticks', '=', 0)
            ->where('source', '=', 'invasion')
            ->update([
                'hours' => DB::raw('`hours` - 1'),
                'dominion_queue.updated_at' => $this->now,
            ]);
    }

    // Update other queues (with stasis dominions) for all dominions
    private function updateAllOtherQueues(Round $round, array $stasisDominions)
    {
        DB::table('dominion_queue')
            ->join('dominions', 'dominion_queue.dominion_id', '=', 'dominions.id')
            ->where('dominions.round_id', $round->id)
            ->where('dominions.protection_ticks', '=', 0)
            ->whereNotIn('dominions.id', $stasisDominions)
            ->where('source', '!=', 'invasion')
            ->update([
                'hours' => DB::raw('`hours` - 1'),
                'dominion_queue.updated_at' => $this->now,
            ]);
    }

    # Take buildings that are one tick away from finished and create or increment DominionBuildings.
    private function handleBuildings(Dominion $dominion): void
    {
        $finishedBuildingsInQueue = DB::table('dominion_queue')
                                        ->where('dominion_id',$dominion->id)
                                        ->where('resource', 'like', 'building%')
                                        ->where('hours',1)
                                        ->get();

        foreach($finishedBuildingsInQueue as $finishedBuildingInQueue)
        {
            $buildingKey = str_replace('building_', '', $finishedBuildingInQueue->resource);
            $amount = intval($finishedBuildingInQueue->amount);
            $building = Building::where('key', $buildingKey)->first();
            $this->buildingCalculator->createOrIncrementBuildings($dominion, [$buildingKey => $amount]);
        }
    }

    # Take improvements that are one tick away from finished and create or increment DominionImprovements.
    private function handleImprovements(Dominion $dominion): void
    {
        $finishedImprovementsInQueue = DB::table('dominion_queue')
                                        ->where('dominion_id',$dominion->id)
                                        ->where('resource', 'like', 'improvement%')
                                        ->where('hours',1)
                                        ->get();
        foreach($finishedImprovementsInQueue as $finishedImprovementInQueue)
        {
            $improvementKey = str_replace('improvement_', '', $finishedImprovementInQueue->resource);
            $amount = intval($finishedImprovementInQueue->amount);
            $improvement = Improvement::where('key', $improvementKey)->first();
            $this->improvementCalculator->createOrIncrementImprovements($dominion, [$improvementKey => $amount]);
        }

        if($improvementInterestPerk = $dominion->race->getPerkValue('improvements_interest'))
        {
            $improvementInterest = [];
            $improvementInterestPerk *= 1 + $dominion->getBuildingPerkMultiplier('improvement_interest');
            foreach($this->improvementCalculator->getDominionImprovements($dominion) as $dominionImprovement)
            {
                $improvement = Improvement::where('id', $dominionImprovement->improvement_id)->first();
                $increment = floor($dominionImprovement->invested * ($improvementInterestPerk / 100));
                $improvementInterest[$improvement->key] = $increment;
            }

            $this->improvementCalculator->createOrIncrementImprovements($dominion, $improvementInterest);
        }
    }

    # Take deities that are one tick away from finished and create or increment DominionImprovements.
    private function handleDeities(Dominion $dominion): void
    {
        $finishedDeitiesInQueue = DB::table('dominion_queue')
                                        ->where('dominion_id',$dominion->id)
                                        ->where('source', 'deity')
                                        ->where('hours',1)
                                        ->get();
        foreach($finishedDeitiesInQueue as $finishedDeityInQueue)
        {
            $deityKey = $finishedDeityInQueue->resource;
            $amount = 1;
            $deity = Deity::where('key', $deityKey)->first();
            $this->deityService->completeSubmissionToDeity($dominion, $deity);

            $deityEvent = GameEvent::create([
                'round_id' => $dominion->round_id,
                'source_type' => Deity::class,
                'source_id' => $deity->id,
                'target_type' => Dominion::class,
                'target_id' => $dominion->id,
                'type' => 'deity_completed',
                'data' => NULL,
            ]);
        }

    }

    # Take resources that are one tick away from finished and create or increment DominionImprovements.
    private function handleResources(Dominion $dominion): void
    {
        $resourcesProduced = [];
        $resourcesConsumed = [];
        $resourcesNetChange = [];

        $finishedResourcesInQueue = DB::table('dominion_queue')
                                        ->where('dominion_id',$dominion->id)
                                        ->where('resource', 'like', 'resource%')
                                        ->whereIn('source', ['exploration','invasion'])
                                        ->where('hours',1)
                                        ->get();

        foreach($finishedResourcesInQueue as $finishedResourceInQueue)
        {
            $resourceKey = str_replace('resource_', '', $finishedResourceInQueue->resource);
            $amount = intval($finishedResourceInQueue->amount);
            $resource = Resource::where('key', $resourceKey)->first();

            # Silently discard resources this faction doesn't use, if we somehow have any incoming from queue.
            if(in_array($resourceKey, $dominion->race->resources))
            {
                if(isset($resourcesToAdd[$resourceKey]))
                {
                    $resourcesProduced[$resourceKey] += $amount;
                }
                else
                {
                    $resourcesProduced[$resourceKey] = $amount;
                }
            }
        }

        # Add production.
        foreach($dominion->race->resources as $resourceKey)
        {
            $resourcesProduced[$resourceKey] = $this->resourceCalculator->getProduction($dominion, $resourceKey);
            $resourcesConsumed[$resourceKey] = $this->resourceCalculator->getConsumption($dominion, $resourceKey);
            $resourcesNetChange[$resourceKey] = $resourcesProduced[$resourceKey] - $resourcesConsumed[$resourceKey];
        }

        # Check for starvation
        $dominion->tick->starvation_casualties = false;
        if($resourcesConsumed['food'] > 0 and (($this->resourceCalculator->getAmount($dominion, 'food') + $resourcesNetChange['food']) < 0))
        {
            $dominion->tick->starvation_casualties = true;
        }

        $this->resourceService->updateResources($dominion, $resourcesNetChange);

    }
}
