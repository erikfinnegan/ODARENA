<?php

namespace OpenDominion\Services\Dominion;

Use DB;
use Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\GameEvent;
use OpenDominion\Models\Realm;
use OpenDominion\Models\Round;
use OpenDominion\Models\User;
use OpenDominion\Models\Race;
use OpenDominion\Models\Title;

use OpenDominion\Factories\DominionFactory;

use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;

class BarbarianService
{

    protected const DPA_CONSTANT = 25;
    protected const DPA_PER_HOUR = 0.55;

    # Train % of new units as specs. /1000
    protected const SPECS_RATIO_MIN = 50;
    protected const SPECS_RATIO_MAX = 500;

    # One in x chance to hit
    protected const ONE_IN_CHANCE_TO_HIT = 1;

    # Gain % of land between these two values when hitting. /1000
    protected const LAND_GAIN_MIN = 100;
    protected const LAND_GAIN_MAX = 200;

    # Send between these two values when hitting. /100
    protected const SENT_RATIO_MIN = 80;
    protected const SENT_RATIO_MAX = 100;

    # Lose % of units between these two values when hitting. /1000
    protected const CASUALTIES_MIN = 50;
    protected const CASUALTIES_MAX = 100;

    # Train between these two values % of required units per tick. /100
    // Disabled, always training 100%.
    protected const UNITS_TRAINED_MIN = 90;
    protected const UNITS_TRAINED_MAX = 125;


    /** @var MilitaryCalculator */
    protected $militaryCalculator;

    /** @var LandCalculator */
    protected $landCalculator;

    /** @var QueueService */
    protected $queueService;

    /**
     * BarbarianService constructor.
     */
    public function __construct()
    {
        #$this->now = now();
        $this->landCalculator = app(LandCalculator::class);
        $this->queueService = app(QueueService::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->dominionFactory = app(DominionFactory::class);
    }

    private function getDpaTarget(Dominion $dominion): int
    {
        #$constant = 25;

        $calculateDate = max($dominion->round->start_date, $dominion->created_at);

        $hoursIntoTheRound = now()->startOfHour()->diffInHours(Carbon::parse($calculateDate)->startOfHour());
        $dpa = static::DPA_CONSTANT + ($hoursIntoTheRound * static::DPA_PER_HOUR);
        return $dpa *= ($dominion->npc_modifier / 1000);
    }

    private function getOpaTarget(Dominion $dominion): int
    {
        return $this->getDpaTarget($dominion) * 0.75;
    }

    # Includes units out on attack.
    private function getDpCurrent(Dominion $dominion): int
    {
        $dp = $this->militaryCalculator->getTotalUnitsForSlot($dominion, 2) * 3;
        $dp += $this->militaryCalculator->getTotalUnitsForSlot($dominion, 3) * 5;

        return $dp;
    }

    # Includes units at home and out on attack.
    private function getOpCurrent(Dominion $dominion): int
    {
        $op = $this->militaryCalculator->getTotalUnitsForSlot($dominion, 1) * 3;
        $op += $this->militaryCalculator->getTotalUnitsForSlot($dominion, 4) * 5;

        return $op;
    }

    # Includes units at home and out on attack.
    private function getOpAtHome(Dominion $dominion): int
    {
        $op = $dominion->military_unit1 * 3;
        $op += $dominion->military_unit4 * 5;

        return $op;
    }

    private function getDpPaid(Dominion $dominion): int
    {
        $dp = $this->getDpCurrent($dominion);
        $dp += $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit2') * 3;
        $dp += $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit3') * 5;

        return $dp;
    }

    private function getOpPaid(Dominion $dominion): int
    {
        $op = $this->getOpCurrent($dominion);
        $op += $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit1') * 3;
        $op += $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit4') * 5;

        return $op;
    }


    private function getDpaCurrent(Dominion $dominion): int
    {
        return $this->getDpCurrent($dominion) / $this->landCalculator->getTotalLand($dominion);
    }

    private function getOpaCurrent(Dominion $dominion): int
    {
        return $this->getOpCurrent($dominion) / $this->landCalculator->getTotalLand($dominion);
    }


    private function getDpaPaid(Dominion $dominion): int
    {
        return $this->getDpPaid($dominion) / $this->landCalculator->getTotalLand($dominion);
    }

    private function getOpaPaid(Dominion $dominion): int
    {
        return $this->getOpPaid($dominion) / $this->landCalculator->getTotalLand($dominion);
    }

    private function getOpaAtHome(Dominion $dominion): int
    {
        return $this->getOpAtHome($dominion) / $this->landCalculator->getTotalLand($dominion);
    }


    public function handleBarbarianTraining(Dominion $dominion): void
    {
        if($dominion->race->name === 'Barbarian')
        {
            $land = $this->landCalculator->getTotalLand($dominion);

            $land += $this->queueService->getInvasionQueueTotalByResource($dominion, 'land_plain');
            $land += $this->queueService->getInvasionQueueTotalByResource($dominion, 'land_mountain');
            $land += $this->queueService->getInvasionQueueTotalByResource($dominion, 'land_swamp');
            $land += $this->queueService->getInvasionQueueTotalByResource($dominion, 'land_forest');
            $land += $this->queueService->getInvasionQueueTotalByResource($dominion, 'land_hill');
            $land += $this->queueService->getInvasionQueueTotalByResource($dominion, 'land_water');

            $units = [
              'military_unit1' => 0,
              'military_unit2' => 0,
              'military_unit3' => 0,
              'military_unit4' => 0,
            ];

            $dpaDelta = $this->getDpaTarget($dominion) - $this->getDpaPaid($dominion);

            if($dpaDelta > 0)
            {
                //echo "[DP] Need to train DP. DPA delta is: $dpaDelta (current: " . $this->getDpaTarget($dominion) . " - paid: " . $this->getDpaPaid($dominion) . ")\n";
                //Log::Debug("[DP] Need to train DP. DPA delta is: $dpaDelta (current: " . $this->getDpaTarget($dominion) . " - paid: " . $this->getDpaPaid($dominion) . ")");

                $dpToTrain = $dpaDelta * $land;

                $specsRatio = rand(static::SPECS_RATIO_MIN, static::SPECS_RATIO_MAX)/1000;
                $elitesRatio = 1-$specsRatio;

                $units['military_unit2'] = intval(($dpToTrain*$specsRatio)/3);
                $units['military_unit3'] = intval(($dpToTrain*$specsRatio)/5);
            }
            else
            {
                //echo "[DP] No need to train DP. DPA delta is: $dpaDelta (current: " . $this->getDpaTarget($dominion) . " - paid: " . $this->getDpaPaid($dominion) . ")\n";
                //Log::Debug("[DP] No need to train DP. DPA delta is: $dpaDelta (current: " . $this->getDpaTarget($dominion) . " - paid: " . $this->getDpaPaid($dominion) .  ")");
            }

            $opaDelta = $this->getOpaTarget($dominion) - $this->getOpaPaid($dominion);

            if($opaDelta > 0)
            {
                //echo "[OP] Need to train OP. OPA delta is: $opaDelta (current: " . $this->getOpaTarget($dominion) . " - paid: " . $this->getOpaPaid($dominion) . ")\n";
                //Log::Debug("[OP] Need to train OP. OPA delta is: $opaDelta (current: " . $this->getOpaTarget($dominion) . " - paid: " . $this->getOpaPaid($dominion) .  ")");

                $opToTrain = $opaDelta * $land;

                $specsRatio = rand(static::SPECS_RATIO_MIN, static::SPECS_RATIO_MAX)/1000;
                $elitesRatio = 1-$specsRatio;

                # Overtrain OP by 10%.
                $units['military_unit1'] = intval(($opToTrain*$specsRatio)/3 * 1.1);
                $units['military_unit4'] = intval(($opToTrain*$specsRatio)/5 * 1.1);
            }
            else
            {
                //echo "[OP] No need to train OP. OPA delta is: $opaDelta (current: " . $this->getOpaTarget($dominion) . " - paid: " . $this->getOpaPaid($dominion) . ")\n";
                //Log::Debug("[OP] No need to train OP. OPA delta is: $opaDelta (current: " . $this->getOpaTarget($dominion) . " - paid: " . $this->getOpaPaid($dominion));
            }

            foreach($units as $unit => $amountToTrain)
            {
                if($amountToTrain > 0)
                {
                    $amountToTrain *= rand(static::UNITS_TRAINED_MIN, static::UNITS_TRAINED_MAX)/100;
                    $amountToTrain = max(1, $amountToTrain); # WTF?
                    //echo "[TRAINING] " . number_format($amountToTrain) . ' ' . $unit. "\n";
                    //Log::Debug("[TRAINING] " . number_format($amountToTrain) . ' ' . $unit);
                    $data = [$unit => $amountToTrain];
                    $hours = 12;
                    $this->queueService->queueResources('training', $dominion, $data, $hours);
                }
            }

            $logString = '[BARBARIAN] ' . $dominion->name . ' is ' . $land . ' acres and has a target DPA of ' . $this->getDpaTarget($dominion) . ' and a has DPA delta of ' . $dpaDelta . ', and an OPA delta of ' . $opaDelta . '. ';
            if(isset($dpToTrain))
            {
                $logString .= 'DP to train: ' . number_format($dpToTrain) . '. ';
            }
            else
            {
                $logString .= 'No need train additional DP. ';
            }
            if(isset($opToTrain))
            {
                $logString .= 'OP to train: ' . number_format($opToTrain) . '. ';
            }
            else
            {
                $logString .= 'No need train additional OP. ';
            }

            $logString .= 'They will train ' . $units['military_unit1'] . ' unit1, ' . $units['military_unit2'] . ' unit2, ' . $units['military_unit3'] . ' unit3, ' . $units['military_unit4'] . ' unit4.';

            $logString .= 'Target DPA: ' . $this->getDpaTarget($dominion) . '. Paid DPA: ' . $this->getDpaPaid($dominion) . '. Target OPA: ' . $this->getOpaTarget($dominion) . '. Paid OPA: ' . $this->getOpaPaid($dominion) . '. ';

            Log::Debug($logString);

        }

    }

    public function handleBarbarianInvasion(Dominion $dominion): void
    {
        $invade = false;

        $logString = "[INVADE] Handling invasion check for " . $dominion->name . ": ";

        if($dominion->race->name === 'Barbarian')
        {
            # Make sure we have the expected OPA to hit.
            if($this->getOpaAtHome($dominion) >= $this->getOpaTarget($dominion))
            {
                $currentDay = $dominion->round->start_date->subDays(1)->diffInDays(now());

                if(rand(1, static::ONE_IN_CHANCE_TO_HIT) == 1)
                {
                    $invade = true;
                    $logString .= " ✅ Invasion confirmed to take place.";
                }
                else
                {
                    $logString .= " ❌ Chance of invasion did not occur.";
                }
            }
            else
            {
                $logString .= "Not enough OPA to invade. (home: " . $this->getOpaAtHome($dominion) . ", target:" . $this->getOpaTarget($dominion) . ", paid: " . $this->getOpaPaid($dominion) ."). ";
            }

            if($invade === true)
            {
                $landGainRatio = rand(static::LAND_GAIN_MIN, static::LAND_GAIN_MAX)/1000;

                $logString .= 'Land gain ratio: ' . $landGainRatio*100 . '%. ';

                # Calculate the amount of acres to grow.
                $totalLandToGain = intval($this->landCalculator->getTotalLand($dominion) * $landGainRatio);

                $logString .= 'Acres gained: ' . $totalLandToGain . '. ';

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

                $sentRatio = rand(static::SENT_RATIO_MIN, static::SENT_RATIO_MAX)/100;

                $casualtiesRatio = rand(static::CASUALTIES_MIN, static::CASUALTIES_MAX)/1000;

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
                $unitsReturning['military_unit1'] = intval(max($unitsSent['military_unit1'] - $unitsLost['military_unit1'],0));
                $unitsReturning['military_unit4'] = intval(max($unitsSent['military_unit4'] - $unitsLost['military_unit4'],0));

                # Queue the incoming land.
                $this->queueService->queueResources(
                    'invasion',
                    $dominion,
                    $landGained
                );

                # Queue the returning units.
                $this->queueService->queueResources(
                    'invasion',
                    $dominion,
                    $unitsReturning
                );

               $invasionTypes = ['attacked', 'raided', 'pillaged', 'ransacked', 'looted', 'devastated', 'plundered', 'sacked'];
               $invasionTargets = ['settlement', 'village', 'town', 'hamlet', 'plot of unclaimed land', 'community', 'trading hub', 'merchant outpost'];

               $data = [
                    'type' => $invasionTypes[rand(0,count($invasionTypes)-1)],
                    'target' => $invasionTargets[rand(0,count($invasionTargets)-1)],
                    'land' => $totalLandToGain,
                  ];

                $barbarianInvasionEvent = GameEvent::create([
                    'round_id' => $dominion->round_id,
                    'source_type' => Dominion::class,
                    'source_id' => $dominion->id,
                    'target_type' => Realm::class,
                    'target_id' => $dominion->realm_id,
                    'type' => 'barbarian_invasion',
                    'data' => $data,
                ]);
                $dominion->save(['event' => HistoryService::EVENT_ACTION_INVADE]);

                Log::Debug($logString);
            }
        }
    }


    public function createBarbarian(Round $round): void
    {
        # Get Bandit/Barbarian users.
        $barbarianUsers = DB::table('users')
            ->where('users.email', 'like', 'bandit%@lykanthropos.com')
            ->pluck('users.id')
            ->toArray();

        $currentBarbarians = DB::table('users')
            ->join('dominions','dominions.user_id', 'users.id')
            ->whereIn('users.id', $barbarianUsers)
            ->where('dominions.round_id', '=' , $round->id)
            ->pluck('users.id')
            ->toArray();

        $availableUsers = array_diff($barbarianUsers, $currentBarbarians);

        if(!empty($availableUsers))
        {
            $barbarian = $availableUsers[array_rand($availableUsers, 1)];

            # Get Barbarian realm.
            $realm = Realm::query()
                ->where('alignment', '=' , 'npc')
                ->where('round_id', '=' , $round->id)
                ->first();

            # Get Barbarian race.
            $race = Race::query()
                ->where('name', '=', 'Barbarian')
                ->first();

            # Get title.
            $title = Title::query()
                ->where('name', '=', 'Commander')
                ->first();

            # Barbarian tribe names
            $tribeTypes = [
              'Crew',
              'Gang',
              'Tribe',
              'Band',
              'Rovers',
              'Raiders',
              'Ruffians',
              'Roughnecks',
              'Mongrels',
              'Clan',
              'Scofflaws',
              'Mob',
              'Scoundrels',
              'Rascals',
              'Outlaws',
              'Savages',
              'Vandals',
              'Coterie',
              'Muggers',
              'Brutes',
              'Pillagers',
              'Thieves',
              'Crooks',
              'Junta',
              'Bruisers',
              'Guerilla',
              'Posse',
              'Herd',
              'Hooligans',
              'Hoodlums',
              'Rapscallions',
              'Scallywags',
              'Wretches',
              'Knaves',
              'Scamps',
              'Miscreants',
              'Misfits',
              'Good-For-Nothings',
              'Murderers',
            ];

            $user = User::findorfail($barbarian);

            # Get ruler name.
            $rulerName = $user->display_name;

            # Get the corresponding dominion name.
            $dominionName = $rulerName . "'s " . $tribeTypes[array_rand($tribeTypes, 1)];

            #echo "[BARBARIAN] Creating $dominionName.";

            $barbarian = $this->dominionFactory->create($user, $realm, $race, $title, $rulerName, $dominionName, NULL);

            $this->newDominionEvent = GameEvent::create([
                'round_id' => $barbarian->round_id,
                'source_type' => Dominion::class,
                'source_id' => $barbarian->id,
                'target_type' => Realm::class,
                'target_id' => $barbarian->realm_id,
                'type' => 'new_dominion',
                'data' => NULL,
            ]);
        }

    }

}
