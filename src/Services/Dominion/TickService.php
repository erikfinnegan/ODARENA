<?php

namespace OpenDominion\Services\Dominion;

use DB;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Log;
use OpenDominion\Calculators\Dominion\CasualtiesCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\PopulationCalculator;
use OpenDominion\Calculators\Dominion\ProductionCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;
use OpenDominion\Calculators\NetworthCalculator;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Dominion\Tick;
use OpenDominion\Models\Round;
use OpenDominion\Services\NotificationService;
use Throwable;

# ODA
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Models\GameEvent;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Helpers\ImprovementHelper;
use OpenDominion\Calculators\RealmCalculator;

class TickService
{
    /** @var Carbon */
    protected $now;

    /** @var CasualtiesCalculator */
    protected $casualtiesCalculator;

    /** @var LandCalculator */
    protected $landCalculator;

    /** @var NetworthCalculator */
    protected $networthCalculator;

    /** @var NotificationService */
    protected $notificationService;

    /** @var PopulationCalculator */
    protected $populationCalculator;

    /** @var ProductionCalculator */
    protected $productionCalculator;

    /** @var QueueService */
    protected $queueService;

    /** @var SpellCalculator */
    protected $spellCalculator;

    /** @var MilitaryCalculator */
    protected $militaryCalculator;

    /** @var RangeCalculator */
    protected $rangeCalculator;

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
        $this->casualtiesCalculator = app(CasualtiesCalculator::class);
        $this->landCalculator = app(LandCalculator::class);
        $this->networthCalculator = app(NetworthCalculator::class);
        $this->notificationService = app(NotificationService::class);
        $this->populationCalculator = app(PopulationCalculator::class);
        $this->productionCalculator = app(ProductionCalculator::class);
        $this->queueService = app(QueueService::class);
        $this->spellCalculator = app(SpellCalculator::class);

        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->rangeCalculator = app(RangeCalculator::class);
        $this->improvementHelper = app(ImprovementHelper::class);
        $this->realmCalculator = app(RealmCalculator::class);

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
        Log::debug('Scheduled tick started');

        $activeRounds = Round::active()->get();

        foreach ($activeRounds as $round) {
            DB::transaction(function () use ($round)
            {

                // Update dominions
                DB::table('dominions')
                    ->join('dominion_tick', 'dominions.id', '=', 'dominion_tick.dominion_id')
                    ->where('dominions.round_id', $round->id)
                    ->where('dominions.is_locked', false)
                    ->where('dominions.protection_ticks', '=', 0)
                    ->update([
                        'dominions.prestige' => DB::raw('dominions.prestige + dominion_tick.prestige'),
                        'dominions.peasants' => DB::raw('dominions.peasants + dominion_tick.peasants + dominion_tick.peasants_sacrificed'),
                        'dominions.peasants_last_hour' => DB::raw('dominion_tick.peasants'),
                        'dominions.morale' => DB::raw('dominions.morale + dominion_tick.morale'),
                        'dominions.spy_strength' => DB::raw('dominions.spy_strength + dominion_tick.spy_strength'),
                        'dominions.wizard_strength' => DB::raw('dominions.wizard_strength + dominion_tick.wizard_strength'),

                        'dominions.resource_platinum' => DB::raw('dominions.resource_platinum + dominion_tick.resource_platinum'),
                        'dominions.resource_food' => DB::raw('dominions.resource_food + dominion_tick.resource_food - dominion_tick.resource_food_contribution + dominion_tick.resource_food_contributed'),
                        'dominions.resource_lumber' => DB::raw('dominions.resource_lumber + dominion_tick.resource_lumber - dominion_tick.resource_lumber_contribution + dominion_tick.resource_lumber_contributed'),
                        'dominions.resource_mana' => DB::raw('dominions.resource_mana + dominion_tick.resource_mana'),
                        'dominions.resource_ore' => DB::raw('dominions.resource_ore + dominion_tick.resource_ore - dominion_tick.resource_ore_contribution + dominion_tick.resource_ore_contributed'),
                        'dominions.resource_gems' => DB::raw('dominions.resource_gems + dominion_tick.resource_gems'),
                        'dominions.resource_tech' => DB::raw('dominions.resource_tech + dominion_tick.resource_tech'),
                        'dominions.resource_boats' => DB::raw('dominions.resource_boats + dominion_tick.resource_boats'),

                        # Improvements
                        'dominions.improvement_markets' => DB::raw('dominions.improvement_markets + dominion_tick.improvement_markets'),
                        'dominions.improvement_keep' => DB::raw('dominions.improvement_keep + dominion_tick.improvement_keep'),
                        'dominions.improvement_forges' => DB::raw('dominions.improvement_forges + dominion_tick.improvement_forges'),
                        'dominions.improvement_walls' => DB::raw('dominions.improvement_walls + dominion_tick.improvement_walls'),
                        'dominions.improvement_armory' => DB::raw('dominions.improvement_armory + dominion_tick.improvement_armory'),
                        'dominions.improvement_infirmary' => DB::raw('dominions.improvement_infirmary + dominion_tick.improvement_infirmary'),
                        'dominions.improvement_workshops' => DB::raw('dominions.improvement_workshops + dominion_tick.improvement_workshops'),
                        'dominions.improvement_observatory' => DB::raw('dominions.improvement_observatory + dominion_tick.improvement_observatory'),
                        'dominions.improvement_cartography' => DB::raw('dominions.improvement_cartography + dominion_tick.improvement_cartography'),
                        'dominions.improvement_towers' => DB::raw('dominions.improvement_towers + dominion_tick.improvement_towers'),
                        'dominions.improvement_hideouts' => DB::raw('dominions.improvement_hideouts + dominion_tick.improvement_hideouts'),
                        'dominions.improvement_granaries' => DB::raw('dominions.improvement_granaries + dominion_tick.improvement_granaries'),
                        'dominions.improvement_harbor' => DB::raw('dominions.improvement_harbor + dominion_tick.improvement_harbor'),
                        'dominions.improvement_forestry' => DB::raw('dominions.improvement_forestry + dominion_tick.improvement_forestry'),
                        'dominions.improvement_refinery' => DB::raw('dominions.improvement_refinery + dominion_tick.improvement_refinery'),
                        'dominions.improvement_tissue' => DB::raw('dominions.improvement_tissue + dominion_tick.improvement_tissue'),

                        # ODA resources
                        'dominions.resource_wild_yeti' => DB::raw('dominions.resource_wild_yeti + dominion_tick.resource_wild_yeti'),
                        'dominions.resource_champion' => DB::raw('dominions.resource_champion + dominion_tick.resource_champion'),
                        'dominions.resource_soul' => DB::raw('dominions.resource_soul + dominion_tick.resource_soul'),
                        'dominions.resource_blood' => DB::raw('dominions.resource_blood + dominion_tick.resource_blood'),

                        'dominions.military_draftees' => DB::raw('dominions.military_draftees + dominion_tick.military_draftees'),
                        'dominions.military_unit1' => DB::raw('dominions.military_unit1 + dominion_tick.military_unit1'),
                        'dominions.military_unit2' => DB::raw('dominions.military_unit2 + dominion_tick.military_unit2'),
                        'dominions.military_unit3' => DB::raw('dominions.military_unit3 + dominion_tick.military_unit3'),
                        'dominions.military_unit4' => DB::raw('dominions.military_unit4 + dominion_tick.military_unit4'),
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

                        'dominions.discounted_land' => DB::raw('dominions.discounted_land + dominion_tick.discounted_land'),
                        'dominions.building_home' => DB::raw('dominions.building_home + dominion_tick.building_home'),
                        'dominions.building_alchemy' => DB::raw('dominions.building_alchemy + dominion_tick.building_alchemy'),
                        'dominions.building_farm' => DB::raw('dominions.building_farm + dominion_tick.building_farm'),
                        'dominions.building_smithy' => DB::raw('dominions.building_smithy + dominion_tick.building_smithy'),
                        'dominions.building_masonry' => DB::raw('dominions.building_masonry + dominion_tick.building_masonry'),
                        'dominions.building_ore_mine' => DB::raw('dominions.building_ore_mine + dominion_tick.building_ore_mine'),
                        'dominions.building_gryphon_nest' => DB::raw('dominions.building_gryphon_nest + dominion_tick.building_gryphon_nest'),
                        'dominions.building_tower' => DB::raw('dominions.building_tower + dominion_tick.building_tower'),
                        'dominions.building_wizard_guild' => DB::raw('dominions.building_wizard_guild + dominion_tick.building_wizard_guild'),
                        'dominions.building_temple' => DB::raw('dominions.building_temple + dominion_tick.building_temple'),
                        'dominions.building_diamond_mine' => DB::raw('dominions.building_diamond_mine + dominion_tick.building_diamond_mine'),
                        'dominions.building_school' => DB::raw('dominions.building_school + dominion_tick.building_school'),
                        'dominions.building_lumberyard' => DB::raw('dominions.building_lumberyard + dominion_tick.building_lumberyard'),
                        'dominions.building_forest_haven' => DB::raw('dominions.building_forest_haven + dominion_tick.building_forest_haven'),
                        'dominions.building_factory' => DB::raw('dominions.building_factory + dominion_tick.building_factory'),
                        'dominions.building_guard_tower' => DB::raw('dominions.building_guard_tower + dominion_tick.building_guard_tower'),
                        'dominions.building_shrine' => DB::raw('dominions.building_shrine + dominion_tick.building_shrine'),
                        'dominions.building_barracks' => DB::raw('dominions.building_barracks + dominion_tick.building_barracks'),
                        'dominions.building_dock' => DB::raw('dominions.building_dock + dominion_tick.building_dock'),

                        'dominions.building_ziggurat' => DB::raw('dominions.building_ziggurat + dominion_tick.building_ziggurat'),
                        'dominions.building_tissue' => DB::raw('dominions.building_tissue + dominion_tick.building_tissue'),
                        'dominions.building_mycelia' => DB::raw('dominions.building_mycelia + dominion_tick.building_mycelia'),

                        'dominions.stat_total_platinum_production' => DB::raw('dominions.stat_total_platinum_production + dominion_tick.resource_platinum'),
                        'dominions.stat_total_food_production' => DB::raw('dominions.stat_total_food_production + dominion_tick.resource_food_production'),
                        'dominions.stat_total_lumber_production' => DB::raw('dominions.stat_total_lumber_production + dominion_tick.resource_lumber_production'),
                        'dominions.stat_total_mana_production' => DB::raw('dominions.stat_total_mana_production + dominion_tick.resource_mana_production'),
                        'dominions.stat_total_wild_yeti_production' => DB::raw('dominions.stat_total_wild_yeti_production + dominion_tick.resource_wild_yeti_production'),
                        'dominions.stat_total_ore_production' => DB::raw('dominions.stat_total_ore_production + dominion_tick.resource_ore'),
                        'dominions.stat_total_gem_production' => DB::raw('dominions.stat_total_gem_production + dominion_tick.resource_gems'),
                        'dominions.stat_total_tech_production' => DB::raw('dominions.stat_total_tech_production + dominion_tick.resource_tech'),
                        'dominions.stat_total_boat_production' => DB::raw('dominions.stat_total_boat_production + dominion_tick.resource_boats'),

                        'dominions.stat_total_food_decayed' => DB::raw('dominions.stat_total_food_decayed + dominion_tick.resource_food_decay'),
                        'dominions.stat_total_food_consumed' => DB::raw('dominions.stat_total_food_consumed + dominion_tick.resource_food_consumption'),
                        'dominions.stat_total_lumber_rotted' => DB::raw('dominions.stat_total_lumber_rotted + dominion_tick.resource_lumber_rot'),
                        'dominions.stat_total_mana_drained' => DB::raw('dominions.stat_total_mana_drained + dominion_tick.resource_mana_drain'),

                        'dominions.protection_ticks' => DB::raw('dominions.protection_ticks + dominion_tick.protection_ticks'),

                        'dominions.last_tick_at' => DB::raw('now()')
                    ]);

                // Update spells
                  DB::table('active_spells')
                      ->join('dominions', 'active_spells.dominion_id', '=', 'dominions.id')
                      ->where('dominions.round_id', $round->id)
                      ->where('dominions.protection_ticks', '=', 0)
                      ->update([
                          'duration' => DB::raw('`duration` - 1'),
                          'active_spells.updated_at' => $this->now,
                      ]);

                // Update queues
                  DB::table('dominion_queue')
                      ->join('dominions', 'dominion_queue.dominion_id', '=', 'dominions.id')
                      ->where('dominions.round_id', $round->id)
                      ->where('dominions.protection_ticks', '=', 0)
                      ->update([
                          'hours' => DB::raw('`hours` - 1'),
                          'dominion_queue.updated_at' => $this->now,
                      ]);

                // Update queues

            }, 10);

            Log::info(sprintf(
                'Ticked %s dominions in %s ms in %s',
                number_format($round->activeDominions->count()),
                number_format($this->now->diffInMilliseconds(now())),
                $round->name
            ));

            $this->now = now();
        }

        foreach ($activeRounds as $round) {
            $dominions = $round->activeDominions()
                ->with([
                    'race',
                    'race.perks',
                    'race.units',
                    'race.units.perks',
                ])
                ->get();

            foreach ($dominions as $dominion)
            {

                // Afflicted: Abomination generation
                if(!empty($dominion->tick->pestilence_units))
                {
                    $caster = Dominion::findorfail($dominion->tick->pestilence_units['caster_dominion_id']);
                    if ($caster)
                    {
                        $this->queueService->queueResources('training', $caster, ['military_unit1' => $dominion->tick->pestilence_units['units']['military_unit1']], 12);
                    }
                }

                // Myconid: Land generation
                if(!empty($dominion->tick->generated_land) and $dominion->protection_ticks == 0)
                {
                    $homeLandType = 'land_' . $dominion->race->home_land_type;
                    $this->queueService->queueResources('exploration', $dominion, [$homeLandType => $dominion->tick->generated_land], 12);
                }

                // Myconid: Unit generation
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

                DB::transaction(function () use ($dominion) {
                    if (!empty($dominion->tick->starvation_casualties)) {
                        $this->notificationService->queueNotification(
                            'starvation_occurred',
                            $dominion->tick->starvation_casualties
                        );
                    }

                    $this->cleanupActiveSpells($dominion);
                    $this->cleanupQueues($dominion);

                    $this->notificationService->sendNotifications($dominion, 'hourly_dominion');

                    $this->precalculateTick($dominion, true);

                }, 5);

            }

            Log::info(sprintf(
                'Cleaned up queues, sent notifications, and precalculated %s dominions in %s ms in %s',
                number_format($round->activeDominions->count()),
                number_format($this->now->diffInMilliseconds(now())),
                $round->name
            ));

            $this->now = now();
        }

        // Update rankings
        if (($this->now->hour % 6) === 0) {
            foreach ($activeRounds as $round) {
                $this->updateDailyRankings($round->dominions);

                Log::info(sprintf(
                    'Updated rankings in %s ms in %s',
                    number_format($this->now->diffInMilliseconds(now())),
                    $round->name
                ));

                $this->now = now();
            }
        }
    }

    /**
     * Does a daily tick on all active dominions and rounds.
     *
     * @throws Exception|Throwable
     */
    public function tickDaily()
    {
        Log::debug('Daily tick started');

        DB::transaction(function () {
            foreach (Round::with('dominions')->active()->get() as $round) {
                // Ignore the first hour 0 of the round
                #if ($this->now->diffInHours($round->start_date) === 0) {
                #    continue;
                #}

                // toBase required to prevent ambiguous updated_at column in query
                $round->dominions()->toBase()->update([
                    'daily_platinum' => false,
                    'daily_land' => false,
                ], [
                    'event' => 'tick',
                ]);
            }
        });

        Log::info('Daily tick finished');
    }

    protected function cleanupActiveSpells(Dominion $dominion)
    {
        $finished = DB::table('active_spells')
            ->where('dominion_id', $dominion->id)
            ->where('duration', '<=', 0)
            ->get();

        $beneficialSpells = [];
        $harmfulSpells = [];

        foreach ($finished as $row) {
            if ($row->cast_by_dominion_id == $dominion->id) {
                $beneficialSpells[] = $row->spell;
            } else {
                $harmfulSpells[] = $row->spell;
            }
        }

        if (!empty($beneficialSpells)) {
            $this->notificationService->queueNotification('beneficial_magic_dissipated', $beneficialSpells);
        }

        if (!empty($harmfulSpells)) {
            $this->notificationService->queueNotification('harmful_magic_dissipated', $harmfulSpells);
        }

        DB::table('active_spells')
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

        foreach ($finished->groupBy('source') as $source => $group) {
            $resources = [];
            foreach ($group as $row) {
                $resources[$row->resource] = $row->amount;
            }

            if ($source === 'invasion') {
                $notificationType = 'returning_completed';
            } else {
                $notificationType = "{$source}_completed";
            }

            $this->notificationService->queueNotification($notificationType, $resources);
        }

        // Cleanup
        DB::table('dominion_queue')
            ->where('dominion_id', $dominion->id)
            ->where('hours', '<=', 0)
            ->delete();
    }

    public function precalculateTick(Dominion $dominion, ?bool $saveHistory = false): void
    {

        /** @var Tick $tick */
        $tick = Tick::firstOrCreate(
            ['dominion_id' => $dominion->id]
        );

        if ($saveHistory) {
            // Save a dominion history record
            $dominionHistoryService = app(HistoryService::class);

            $changes = array_filter($tick->getAttributes(), static function ($value, $key) {
                return (
                    !in_array($key, [
                        'id',
                        'dominion_id',
                        'created_at',
                    ], true) &&
                    ($value != 0) // todo: strict type checking?
                );
            }, ARRAY_FILTER_USE_BOTH);

            $dominionHistoryService->record($dominion, $changes, HistoryService::EVENT_TICK);
        }

        // Reset tick values
        foreach ($tick->getAttributes() as $attr => $value)
        {
            if (!in_array($attr, ['id', 'dominion_id', 'updated_at', 'starvation_casualties', 'pestilence_units', 'generated_land', 'generated_unit1', 'generated_unit2', 'generated_unit3', 'generated_unit4'], true))
            {
                  $tick->{$attr} = 0;
            }
            elseif (in_array($attr, ['starvation_casualties', 'pestilence_units', 'generated_land', 'generated_unit1', 'generated_unit2', 'generated_unit3', 'generated_unit4'], true))
            {
                $tick->{$attr} = [];
            }
          }

        // Hacky refresh for dominion
        $dominion->refresh();
        $this->spellCalculator->getActiveSpells($dominion, true);

        // Queues
        $incomingQueue = DB::table('dominion_queue')
            ->where('dominion_id', $dominion->id)
            ->where('hours', '=', 1)
            ->get();

        # NPC Barbarian: invasion
        if($dominion->race->alignment === 'npc')
        {
          $invade = false;
          // Are we invading?

          // Make sure all units1 and unit4 are at home.
          if($dominion->military_unit1 > 0 and
             $dominion->military_unit4 > 0 and
             $this->queueService->getInvasionQueueTotalByResource($dominion, 'military_unit1') == 0 and
             $this->queueService->getInvasionQueueTotalByResource($dominion, 'military_unit4') == 0
             )
          {
            $currentDay = $dominion->round->start_date->subDays(1)->diffInDays(now());
            $chanceOneIn = 32 - (14 - min($currentDay, 14));
            if(rand(1,$chanceOneIn) == 1)
            {
              $invade = true;
            }
          }

          if($invade)
          {
            # Grow by 5-12.5% (random), skewed to lower.
            $landGainRatio = max(500,rand(400,1250))/10000;

            # Calculate the amount of acres to grow.
            $totalLandToGain = $this->landCalculator->getTotalLand($dominion) * $landGainRatio;

            # Split the land gained evenly across all 6 land types.
            $landGained['land_plain'] = intval($totalLandToGain/6);
            $landGained['land_mountain'] = intval($totalLandToGain/6);
            $landGained['land_forest'] = intval($totalLandToGain/6);
            $landGained['land_swamp'] = intval($totalLandToGain/6);
            $landGained['land_hill'] = intval($totalLandToGain/6);
            $landGained['land_water'] = intval($totalLandToGain/6);

            # Add the land gained to the $dominion.
            $dominion->stat_total_land_conquered = $totalLandToGain;
            $dominion->stat_attacking_success += 1;

            # Send out 80-100% of all units. Rand over 100 but capped at 100
            # to make it more likely 100% are sent.
            $sentRatio = 1 - $landGainRatio;

            # Casualties between 8.5% and 12% (random).
            $casualtiesRatio = rand(85,120)/1000;

            # Calculate how many Unit1 and Unit4 are sent.
            $unitsSent['military_unit1'] = $dominion->military_unit1 * $sentRatio;
            $unitsSent['military_unit4'] = $dominion->military_unit4 * $sentRatio;

            # Remove the sent units from the dominion.
            $dominion->military_unit1 -= $unitsSent['military_unit1'];
            $dominion->military_unit4 -= $unitsSent['military_unit4'];

            # Calculate losses by applying casualties ratio to units sent.
            $unitsLost['military_unit1'] = $unitsSent['military_unit1'] * $casualtiesRatio;
            $unitsLost['military_unit4'] = $unitsSent['military_unit4'] * $casualtiesRatio;

            # Calculate amount of returning units.
            $unitsReturning['military_unit1'] = max($unitsSent['military_unit1'] - $unitsLost['military_unit1'],0);
            $unitsReturning['military_unit4'] = max($unitsSent['military_unit4'] - $unitsLost['military_unit4'],0);

            # Queue the returning units.
            foreach($unitsReturning as $unit => $amountReturning)
            {
               $dominion->{$unit} - $unitsSent[$unit];
               $this->queueService->queueResources(
                   'invasion',
                   $dominion,
                   [$unit => $amountReturning],
                   12
               );
            }

            # Queue the incoming land.
            foreach($landGained as $type => $amount)
            {
               $data = [$type => $amount];
               $this->queueService->queueResources(
                   'invasion',
                   $dominion,
                   $data
               );

               $dominion->save(['event' => HistoryService::EVENT_ACTION_INVADE]);

            }
         }
        }

        foreach ($incomingQueue as $row)
        {
            $tick->{$row->resource} += $row->amount;
            // Temporarily add next hour's resources for accurate calculations
            $dominion->{$row->resource} += $row->amount;
        }

        # NPC Barbarian: training
        if($dominion->race->alignment === 'npc')
        {
          /*
           Every tick, NPCs:
           1) Train until they reach the DPA requirement
           2) Train until they reach the OPA requirement
           3) Have a chance to quasi-invade.
              Invade = send out between 80% and 100% of the OP and queue land.
           */

           // Calculate DPA required / target DPA
           $constant = 25;
           #$day = $this->now->diffInDays($dominion->round->start_date);
           $hours = now()->startOfHour()->diffInHours(Carbon::parse($dominion->round->start_date)->startOfHour()); # Borrowed from Void OP from MilitaryCalculator

           # Linear hourly
           $dpa = $constant + ($hours * 0.40);
           $dpa *= ($dominion->npc_modifier / 1000);
           $dpa = intval($dpa);
           $opa = intval($dpa * 0.75);

           $dpRequired = $this->landCalculator->getTotalLand($dominion) * $dpa;
           $opRequired = $this->landCalculator->getTotalLand($dominion) * $opa;

           // Determine current DP and OP
           # Unit 1: 3 OP, 0 DP
           # Unit 2: 3 DP, 0 OP
           # Unit 3: 5 DP, 0 OP
           # Unit 4: 5 OP, 2 DP (turtle)

           $dpUnit1 = 0;
           $dpUnit2 = 3;
           $dpUnit3 = 5;
           $dpUnit4 = 0; # Has turtle but ignored here

           $opUnit1 = 3;
           $opUnit2 = 0;
           $opUnit3 = 0;
           $opUnit4 = 5;

           $dpTrained = $this->militaryCalculator->getTotalUnitsForSlot($dominion, 2) * $dpUnit2;
           $dpTrained += $this->militaryCalculator->getTotalUnitsForSlot($dominion, 3) * $dpUnit3;

           $dpInTraining = $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit2') * $dpUnit2;
           $dpInTraining += $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit3') * $dpUnit3;

           $dpPaid = $dpTrained + $dpInTraining;

           $opTrained = $this->militaryCalculator->getTotalUnitsForSlot($dominion, 1) * $opUnit1;
           $opTrained += $this->militaryCalculator->getTotalUnitsForSlot($dominion, 4) * $opUnit4;

           $opInTraining = $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit1') * $opUnit1;
           $opInTraining += $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit4') * $opUnit4;

           $opReturning = $this->queueService->getInvasionQueueTotalByResource($dominion, 'military_unit1') * $opUnit1;
           $opReturning += $this->queueService->getInvasionQueueTotalByResource($dominion, 'military_unit4') * $opUnit4;

           $opPaid = $opTrained + $opInTraining + $opReturning;

           // Determine what (if any) training is required
           $dpToTrain = max(0, $dpRequired - $dpPaid);
           $opToTrain = max(0, $opRequired - $opPaid);

           # Randomly train between 10% and 30% of units as specs.
           $specsRatio = rand(10,30)/100;
           $elitesRatio = 1 - $specsRatio;

           $units = [
             'military_unit1' => intval(($opToTrain * $specsRatio) / $opUnit1),
             'military_unit2' => intval(($dpToTrain * $specsRatio) / $dpUnit2),
             'military_unit3' => intval(($dpToTrain * $elitesRatio) / $dpUnit3),
             'military_unit4' => intval(($opToTrain * $elitesRatio) / $opUnit4),
           ];

           // 50% chance the Barbarian train
           if(rand(1,2) == 1)
           {
             foreach($units as $unit => $amountToTrain)
             {
                $data = [$unit => $amountToTrain];

                $hours = 12;

                $this->queueService->queueResources('training', $dominion, $data, $hours);
             }
           }

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
            $caster = $this->spellCalculator->getCaster($dominion, 'pestilence');

            $amountToDie = intval($dominion->peasants * 0.01);
            $amountToDie *= $this->rangeCalculator->getDominionRange($caster, $dominion) / 100;
            $amountToDie *= (1 - $dominion->race->getPerkMultiplier('reduced_conversions'));

            $tick->pestilence_units = ['caster_dominion_id' => $caster->id, 'units' => ['military_unit1' => $amountToDie]];
            $populationPeasantGrowth -= $amountToDie;
        }

        $tick->peasants_sacrificed = $this->populationCalculator->getPeasantsSacrificed($dominion) * -1;
        $tick->peasants = $populationPeasantGrowth;
        $tick->military_draftees = $drafteesGrowthRate;

        // Void: Improvements Decay - Lower all improvements by improvements_decay%.
        if($dominion->race->getPerkValue('improvements_decay'))
        {
            foreach($this->improvementHelper->getImprovementTypes($dominion) as $improvementType)
            {
                $percentageDecayed = $dominion->race->getPerkValue('improvements_decay') / 100;
                $tick->{'improvement_' . $improvementType} -= $dominion->{'improvement_' . $improvementType} * $percentageDecayed;
            }
        }

        // Resources

        # Max storage

        $maxStorageTicks = 24 * 4; # Store at most 24 hours (96 ticks) per building.
        $acres = $this->landCalculator->getTotalLand($dominion);
        $maxPlatinumPerAcre = 10000;

        $maxStorage = [];
        $maxStorage['platinum'] = $acres * $maxPlatinumPerAcre;
        #$maxStorage['food'] = $maxStorageTicks * (($dominion->building_farm * 80) + ($dominion->building_dock * 35) + $dominion->getUnitPerkProductionBonus('food_production'));
        $maxStorage['lumber'] = max($acres * 100, $maxStorageTicks * ($dominion->building_lumberyard * 50 + $dominion->getUnitPerkProductionBonus('lumber_production')));
        $maxStorage['ore'] = max($acres * 100, $maxStorageTicks * ($dominion->building_ore_mine * 60 + $dominion->getUnitPerkProductionBonus('ore_production')));
        $maxStorage['gems'] = max($acres * 50, $maxStorageTicks * ($dominion->building_diamond_mine * 15 + $dominion->getUnitPerkProductionBonus('gem_production')));
        if($dominion->race->name == 'Myconid')
        {
          $maxStorage['gems'] += $dominion->getUnitPerkProductionBonus('tech_production') * 10;
        }

        $tick->resource_platinum += min($this->productionCalculator->getPlatinumProduction($dominion), max(0, ($maxStorage['platinum'] - $dominion->resource_platinum)));

        $tick->resource_lumber_production += $this->productionCalculator->getLumberProduction($dominion);
        $tick->resource_lumber += min($this->productionCalculator->getLumberNetChange($dominion), max(0, ($maxStorage['lumber'] - $dominion->resource_lumber)));

        $tick->resource_mana_production += $this->productionCalculator->getManaProduction($dominion);
        $tick->resource_mana += $this->productionCalculator->getManaNetChange($dominion);

        $tick->resource_ore += min($this->productionCalculator->getOreProduction($dominion), max(0, ($maxStorage['ore'] - $dominion->resource_ore)));

        $tick->resource_gems += min($this->productionCalculator->getGemProduction($dominion), max(0, ($maxStorage['gems'] - $dominion->resource_gems)));

        $tick->resource_tech += $this->productionCalculator->getTechProduction($dominion);
        $tick->resource_boats += $this->productionCalculator->getBoatProduction($dominion);

        # ODA: wild yeti production
        $tick->resource_wild_yeti_production += $this->productionCalculator->getWildYetiProduction($dominion);
        $tick->resource_wild_yeti += $this->productionCalculator->getWildYetiNetChange($dominion);

        $tick->resource_soul += $this->productionCalculator->getSoulProduction($dominion);
        $tick->resource_blood += $this->productionCalculator->getBloodProduction($dominion);

        # Decay, rot, drain
        $tick->resource_food_consumption += $this->productionCalculator->getFoodConsumption($dominion);
        $tick->resource_food_decay += $this->productionCalculator->getFoodDecay($dominion);
        $tick->resource_lumber_rot += $this->productionCalculator->getLumberDecay($dominion);
        $tick->resource_mana_drain += $this->productionCalculator->getManaDecay($dominion);

        # Contribution: how much is LOST (GIVEN AWAY)
        echo $dominion->name;
        $tick->resource_food_contribution = $this->productionCalculator->getContribution($dominion, 'food');
        $tick->resource_lumber_contribution = $this->productionCalculator->getContribution($dominion, 'lumber');
        $tick->resource_ore_contribution = $this->productionCalculator->getContribution($dominion, 'ore');

        # Contributed: how much is RECEIVED (GIVEN TO)
        if($dominion->race->name == 'Monster')
        {
            $totalContributions = $this->realmCalculator->getTotalContributions($dominion->realm);
            $tick->resource_food_contributed = $totalContributions['food'];
            $tick->resource_lumber_contributed = $totalContributions['lumber'];
            $tick->resource_ore_contributed = $totalContributions['ore'];
        }
        else
        {
            $tick->resource_food_contributed = 0;
            $tick->resource_lumber_contributed = 0;
            $tick->resource_ore_contributed = 0;
        }

        // Check for starvation before adjusting food
        $foodNetChange = $this->productionCalculator->getFoodNetChange($dominion);

        $tick->resource_food_production += $this->productionCalculator->getFoodProduction($dominion);

        // Starvation casualties
        if (($dominion->resource_food + $foodNetChange) < 0)
        {
            $isStarving = true;
            $casualties = $this->casualtiesCalculator->getStarvationCasualtiesByUnitType(
                $dominion,
                ($dominion->resource_food + $foodNetChange)
            );

            $tick->starvation_casualties = $casualties;

            foreach ($casualties as $unitType => $unitCasualties) {
                $tick->{$unitType} -= $unitCasualties;
            }

            // Decrement to zero
            $tick->resource_food = -$dominion->resource_food;
            $tick->resource_food = max(0, $tick->resource_food); # TEMPORARY
        }
        else
        {
            // Food production
            $isStarving = false;
            $tick->resource_food += $foodNetChange;
        }

        // Morale

        $baseMorale = 100;
        $baseMoraleModifier = $this->militaryCalculator->getBaseMoraleModifier($dominion, $this->populationCalculator->getPopulation($dominion));
        $baseMorale *= (1 + $baseMoraleModifier);
        $baseMorale = intval($baseMorale);

        if ($isStarving)
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
              $tick->morale -= min(2, $dominion->morale - $baseMorale);
            }
        }

        // Spy Strength
        if ($dominion->spy_strength < 100) {
            $spyStrengthAdded = 4;
            $spyStrengthAdded += $dominion->getTechPerkValue('spy_strength_recovery');

            $tick->spy_strength = min($spyStrengthAdded, 100 - $dominion->spy_strength);
        }

        // Wizard Strength
        if ($dominion->wizard_strength < 100) {
            $wizardStrengthAdded = 4;

            $wizardStrengthPerWizardGuild = 0.1;
            $wizardStrengthPerWizardGuildMax = 2;

            $wizardStrengthAdded += min(
                (($dominion->building_wizard_guild / $this->landCalculator->getTotalLand($dominion)) * (100 * $wizardStrengthPerWizardGuild)),
                $wizardStrengthPerWizardGuildMax
            );

            $wizardStrengthAdded += $dominion->getTechPerkValue('wizard_strength_recovery');

            $tick->wizard_strength = min($wizardStrengthAdded, 100 - $dominion->wizard_strength);
        }

        $generatedLand = 0;
        $generatedUnit1 = 0;
        $generatedUnit2 = 0;
        $generatedUnit3 = 0;
        $generatedUnit4 = 0;
        for ($slot = 1; $slot <= 4; $slot++)
        {
            // Myconid: Land generation
            if($dominion->race->getUnitPerkValueForUnitSlot($slot, 'land_per_tick'))
            {
                $generatedLand += $dominion->{"military_unit".$slot} * $dominion->race->getUnitPerkValueForUnitSlot($slot, 'land_per_tick');
            }

            // Myconid: Unit generation
            if($unitGenerationPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'unit_production'))
            {
                $unitToGenerateSlot = $unitGenerationPerk[0];
                $unitAmountToGeneratePerGeneratingUnit = $unitGenerationPerk[1];
                $unitAmountToGenerate = $dominion->{'military_unit'.$slot} * $unitAmountToGeneratePerGeneratingUnit;

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
            }
        }
        $tick->generated_land += intval($generatedLand) + (rand()/getrandmax() < fmod($generatedLand, 1) ? 1 : 0);

        $tick->generated_unit1 += intval($generatedUnit1) + (rand()/getrandmax() < fmod($generatedUnit1, 1) ? 1 : 0);
        $tick->generated_unit2 += intval($generatedUnit2) + (rand()/getrandmax() < fmod($generatedUnit2, 1) ? 1 : 0);
        $tick->generated_unit3 += intval($generatedUnit3) + (rand()/getrandmax() < fmod($generatedUnit3, 1) ? 1 : 0);
        $tick->generated_unit4 += intval($generatedUnit4) + (rand()/getrandmax() < fmod($generatedUnit4, 1) ? 1 : 0);

        #$capGeneratedUnitsMilitary = $this->populationCalculator->getMaxPopulation($dominion);

        foreach ($incomingQueue as $row)
        {
            // Reset current resources in case object is saved later
            $dominion->{$row->resource} -= $row->amount;
        }

        $tick->save();
    }

    protected function updateDailyRankings(Collection $activeDominions): void
    {
        $dominionIds = $activeDominions->pluck('id')->toArray();

        // First pass: Saving land and networth
        Dominion::with(['race', 'realm'])
            ->whereIn('id', $dominionIds)
            ->chunk(50, function ($dominions) {
                foreach ($dominions as $dominion) {
                    $where = [
                        'round_id' => (int)$dominion->round_id,
                        'dominion_id' => $dominion->id,
                    ];

                    $data = [
                        'dominion_name' => $dominion->name,
                        'race_name' => $dominion->race->name,
                        'realm_number' => $dominion->realm->number,
                        'realm_name' => $dominion->realm->name,
                        'land' => $this->landCalculator->getTotalLand($dominion),
                        'networth' => $this->networthCalculator->getDominionNetworth($dominion),
                    ];

                    $result = DB::table('daily_rankings')->where($where)->get();

                    if ($result->isEmpty()) {
                        $row = $where + $data + [
                                'created_at' => $dominion->created_at,
                                'updated_at' => $this->now,
                            ];

                        DB::table('daily_rankings')->insert($row);
                    } else {
                        $row = $data + [
                                'updated_at' => $this->now,
                            ];

                        DB::table('daily_rankings')->where($where)->update($row);
                    }
                }
            });

        // Second pass: Calculating ranks
        $result = DB::table('daily_rankings')
            ->orderBy('land', 'desc')
            ->orderBy(DB::raw('COALESCE(land_rank, created_at)'))
            ->get();

        //Getting all rounds
        $rounds = DB::table('rounds')
            ->where('start_date', '<=', $this->now)
            ->where('end_date', '>', $this->now)
            ->get();

        foreach ($rounds as $round) {
            $rank = 1;

            foreach ($result as $row) {
                if ($row->round_id == (int)$round->id) {
                    DB::table('daily_rankings')
                        ->where('id', $row->id)
                        ->where('round_id', $round->id)
                        ->update([
                            'land_rank' => $rank,
                            'land_rank_change' => (($row->land_rank !== null) ? ($row->land_rank - $rank) : 0),
                        ]);

                    $rank++;
                }
            }

            $result = DB::table('daily_rankings')
                ->orderBy('networth', 'desc')
                ->orderBy(DB::raw('COALESCE(networth_rank, created_at)'))
                ->get();

            $rank = 1;

            foreach ($result as $row) {
                if ($row->round_id == (int)$round->id) {
                    DB::table('daily_rankings')
                        ->where('id', $row->id)
                        ->update([
                            'networth_rank' => $rank,
                            'networth_rank_change' => (($row->networth_rank !== null) ? ($row->networth_rank - $rank) : 0),
                        ]);

                    $rank++;
                }
            }
        }
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
            'Manual tick started for %s.',
            $dominion->name
        ));

        $this->precalculateTick($dominion, true);

            DB::transaction(function () use ($dominion)
            {

                // Update dominions
                DB::table('dominions')
                    ->join('dominion_tick', 'dominions.id', '=', 'dominion_tick.dominion_id')
                    ->where('dominions.id', $dominion->id)
                    ->where('dominions.protection_ticks', '>', 0)
                    ->where('dominions.is_locked', false)
                    ->update([
                        'dominions.prestige' => DB::raw('dominions.prestige + dominion_tick.prestige'),
                        'dominions.peasants' => DB::raw('dominions.peasants + dominion_tick.peasants + dominion_tick.peasants_sacrificed'),
                        'dominions.peasants_last_hour' => DB::raw('dominion_tick.peasants'),
                        'dominions.morale' => DB::raw('dominions.morale + dominion_tick.morale'),
                        'dominions.spy_strength' => DB::raw('dominions.spy_strength + dominion_tick.spy_strength'),
                        'dominions.wizard_strength' => DB::raw('dominions.wizard_strength + dominion_tick.wizard_strength'),

                        'dominions.resource_platinum' => DB::raw('dominions.resource_platinum + dominion_tick.resource_platinum'),
                        'dominions.resource_food' => DB::raw('dominions.resource_food + dominion_tick.resource_food'),
                        'dominions.resource_lumber' => DB::raw('dominions.resource_lumber + dominion_tick.resource_lumber'),
                        'dominions.resource_mana' => DB::raw('dominions.resource_mana + dominion_tick.resource_mana'),
                        'dominions.resource_ore' => DB::raw('dominions.resource_ore + dominion_tick.resource_ore'),
                        'dominions.resource_gems' => DB::raw('dominions.resource_gems + dominion_tick.resource_gems'),
                        'dominions.resource_tech' => DB::raw('dominions.resource_tech + dominion_tick.resource_tech'),
                        'dominions.resource_boats' => DB::raw('dominions.resource_boats + dominion_tick.resource_boats'),

                        # Improvements
                        'dominions.improvement_markets' => DB::raw('dominions.improvement_markets + dominion_tick.improvement_markets'),
                        'dominions.improvement_keep' => DB::raw('dominions.improvement_keep + dominion_tick.improvement_keep'),
                        'dominions.improvement_forges' => DB::raw('dominions.improvement_forges + dominion_tick.improvement_forges'),
                        'dominions.improvement_walls' => DB::raw('dominions.improvement_walls + dominion_tick.improvement_walls'),
                        'dominions.improvement_armory' => DB::raw('dominions.improvement_armory + dominion_tick.improvement_armory'),
                        'dominions.improvement_infirmary' => DB::raw('dominions.improvement_infirmary + dominion_tick.improvement_infirmary'),
                        'dominions.improvement_workshops' => DB::raw('dominions.improvement_workshops + dominion_tick.improvement_workshops'),
                        'dominions.improvement_observatory' => DB::raw('dominions.improvement_observatory + dominion_tick.improvement_observatory'),
                        'dominions.improvement_cartography' => DB::raw('dominions.improvement_cartography + dominion_tick.improvement_cartography'),
                        'dominions.improvement_towers' => DB::raw('dominions.improvement_towers + dominion_tick.improvement_towers'),
                        'dominions.improvement_hideouts' => DB::raw('dominions.improvement_hideouts + dominion_tick.improvement_hideouts'),
                        'dominions.improvement_granaries' => DB::raw('dominions.improvement_granaries + dominion_tick.improvement_granaries'),
                        'dominions.improvement_harbor' => DB::raw('dominions.improvement_harbor + dominion_tick.improvement_harbor'),
                        'dominions.improvement_forestry' => DB::raw('dominions.improvement_forestry + dominion_tick.improvement_forestry'),
                        'dominions.improvement_refinery' => DB::raw('dominions.improvement_refinery + dominion_tick.improvement_refinery'),
                        'dominions.improvement_tissue' => DB::raw('dominions.improvement_tissue + dominion_tick.improvement_tissue'),

                        # ODA resources
                        'dominions.resource_wild_yeti' => DB::raw('dominions.resource_wild_yeti + dominion_tick.resource_wild_yeti'),
                        'dominions.resource_champion' => DB::raw('dominions.resource_champion + dominion_tick.resource_champion'),
                        'dominions.resource_soul' => DB::raw('dominions.resource_soul + dominion_tick.resource_soul'),
                        'dominions.resource_blood' => DB::raw('dominions.resource_blood + dominion_tick.resource_blood'),

                        'dominions.military_draftees' => DB::raw('dominions.military_draftees + dominion_tick.military_draftees'),
                        'dominions.military_unit1' => DB::raw('dominions.military_unit1 + dominion_tick.military_unit1'),
                        'dominions.military_unit2' => DB::raw('dominions.military_unit2 + dominion_tick.military_unit2'),
                        'dominions.military_unit3' => DB::raw('dominions.military_unit3 + dominion_tick.military_unit3'),
                        'dominions.military_unit4' => DB::raw('dominions.military_unit4 + dominion_tick.military_unit4'),
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

                        'dominions.discounted_land' => DB::raw('dominions.discounted_land + dominion_tick.discounted_land'),
                        'dominions.building_home' => DB::raw('dominions.building_home + dominion_tick.building_home'),
                        'dominions.building_alchemy' => DB::raw('dominions.building_alchemy + dominion_tick.building_alchemy'),
                        'dominions.building_farm' => DB::raw('dominions.building_farm + dominion_tick.building_farm'),
                        'dominions.building_smithy' => DB::raw('dominions.building_smithy + dominion_tick.building_smithy'),
                        'dominions.building_masonry' => DB::raw('dominions.building_masonry + dominion_tick.building_masonry'),
                        'dominions.building_ore_mine' => DB::raw('dominions.building_ore_mine + dominion_tick.building_ore_mine'),
                        'dominions.building_gryphon_nest' => DB::raw('dominions.building_gryphon_nest + dominion_tick.building_gryphon_nest'),
                        'dominions.building_tower' => DB::raw('dominions.building_tower + dominion_tick.building_tower'),
                        'dominions.building_wizard_guild' => DB::raw('dominions.building_wizard_guild + dominion_tick.building_wizard_guild'),
                        'dominions.building_temple' => DB::raw('dominions.building_temple + dominion_tick.building_temple'),
                        'dominions.building_diamond_mine' => DB::raw('dominions.building_diamond_mine + dominion_tick.building_diamond_mine'),
                        'dominions.building_school' => DB::raw('dominions.building_school + dominion_tick.building_school'),
                        'dominions.building_lumberyard' => DB::raw('dominions.building_lumberyard + dominion_tick.building_lumberyard'),
                        'dominions.building_forest_haven' => DB::raw('dominions.building_forest_haven + dominion_tick.building_forest_haven'),
                        'dominions.building_factory' => DB::raw('dominions.building_factory + dominion_tick.building_factory'),
                        'dominions.building_guard_tower' => DB::raw('dominions.building_guard_tower + dominion_tick.building_guard_tower'),
                        'dominions.building_shrine' => DB::raw('dominions.building_shrine + dominion_tick.building_shrine'),
                        'dominions.building_barracks' => DB::raw('dominions.building_barracks + dominion_tick.building_barracks'),
                        'dominions.building_dock' => DB::raw('dominions.building_dock + dominion_tick.building_dock'),

                        'dominions.building_ziggurat' => DB::raw('dominions.building_ziggurat + dominion_tick.building_ziggurat'),
                        'dominions.building_tissue' => DB::raw('dominions.building_tissue + dominion_tick.building_tissue'),
                        'dominions.building_mycelia' => DB::raw('dominions.building_mycelia + dominion_tick.building_mycelia'),

                        'dominions.stat_total_platinum_production' => DB::raw('dominions.stat_total_platinum_production + dominion_tick.resource_platinum'),
                        'dominions.stat_total_food_production' => DB::raw('dominions.stat_total_food_production + dominion_tick.resource_food_production'),
                        'dominions.stat_total_lumber_production' => DB::raw('dominions.stat_total_lumber_production + dominion_tick.resource_lumber_production'),
                        'dominions.stat_total_mana_production' => DB::raw('dominions.stat_total_mana_production + dominion_tick.resource_mana_production'),
                        'dominions.stat_total_wild_yeti_production' => DB::raw('dominions.stat_total_wild_yeti_production + dominion_tick.resource_wild_yeti_production'),
                        'dominions.stat_total_ore_production' => DB::raw('dominions.stat_total_ore_production + dominion_tick.resource_ore'),
                        'dominions.stat_total_gem_production' => DB::raw('dominions.stat_total_gem_production + dominion_tick.resource_gems'),
                        'dominions.stat_total_tech_production' => DB::raw('dominions.stat_total_tech_production + dominion_tick.resource_tech'),
                        'dominions.stat_total_boat_production' => DB::raw('dominions.stat_total_boat_production + dominion_tick.resource_boats'),

                        'dominions.stat_total_food_decayed' => DB::raw('dominions.stat_total_food_decayed + dominion_tick.resource_food_decay'),
                        'dominions.stat_total_food_consumed' => DB::raw('dominions.stat_total_food_consumed + dominion_tick.resource_food_consumption'),
                        'dominions.stat_total_lumber_rotted' => DB::raw('dominions.stat_total_lumber_rotted + dominion_tick.resource_lumber_rot'),
                        'dominions.stat_total_mana_drained' => DB::raw('dominions.stat_total_mana_drained + dominion_tick.resource_mana_drain'),

                        'dominions.protection_ticks' => DB::raw('dominions.protection_ticks + dominion_tick.protection_ticks'),

                        'dominions.last_tick_at' => DB::raw('now()')
                    ]);

                // Update spells
                  DB::table('active_spells')
                      ->join('dominions', 'active_spells.dominion_id', '=', 'dominions.id')
                      ->where('dominions.id', $dominion->id)
                      ->update([
                          'duration' => DB::raw('`duration` - 1'),
                          'active_spells.updated_at' => $this->now,
                      ]);

                // Update queues
                  DB::table('dominion_queue')
                      ->join('dominions', 'dominion_queue.dominion_id', '=', 'dominions.id')
                      ->where('dominions.id', $dominion->id)
                      ->update([
                          'hours' => DB::raw('`hours` - 1'),
                          'dominion_queue.updated_at' => $this->now,
                      ]);


            }, 10);

            Log::info(sprintf(
                'Ticked dominion %s in %s ms.',
                $dominion->name,
                number_format($this->now->diffInMilliseconds(now()))
            ));

            $this->now = now();

            # Starvation
            DB::transaction(function () use ($dominion) {
                if (!empty($dominion->tick->starvation_casualties)) {
                    $this->notificationService->queueNotification(
                        'starvation_occurred',
                        $dominion->tick->starvation_casualties
                    );
                }

            # Clean up
            $this->cleanupActiveSpells($dominion);
            $this->cleanupQueues($dominion);

            $this->notificationService->sendNotifications($dominion, 'hourly_dominion');

            $this->precalculateTick($dominion, true);

            }, 5);

            // Myconid: Land generation
            if(!empty($dominion->tick->generated_land) and $dominion->protection_ticks > 0)
            {
                $homeLandType = 'land_' . $dominion->race->home_land_type;
                $this->queueService->queueResources('exploration', $dominion, [$homeLandType => $dominion->tick->generated_land], 12);
            }

            // Myconid: Unit generation
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
                'Cleaned up queues, sent notifications, and precalculated dominion %s in %s ms.',
                $dominion->name,
                number_format($this->now->diffInMilliseconds(now()))
            ));

            $this->now = now();
        }

}
