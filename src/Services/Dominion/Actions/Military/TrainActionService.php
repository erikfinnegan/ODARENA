<?php

namespace OpenDominion\Services\Dominion\Actions\Military;

use DB;
use OpenDominion\Calculators\Dominion\Actions\TrainingCalculator;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Traits\DominionGuardsTrait;
use Throwable;

// ODA
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\PopulationCalculator;

class TrainActionService
{
    use DominionGuardsTrait;

    /** @var QueueService */
    protected $queueService;

    /** @var TrainingCalculator */
    protected $trainingCalculator;

    /** @var UnitHelper */
    protected $unitHelper;

    /** @var ImprovementCalculator */
    protected $improvementCalculator;

    // ODA
    /** @var SpellCalculator */
    protected $spellCalculator;

    /** @var MilitaryCalculator */
    protected $militaryCalculator;

    /** @var LandCalculator */
    protected $landCalculator;

    /** @var PopulationCalculator */
    protected $populationCalculator;

    /**
     * TrainActionService constructor.
     */
    public function __construct(
        ImprovementCalculator $improvementCalculator,
        SpellCalculator $spellCalculator,
        MilitaryCalculator $militaryCalculator,
        LandCalculator $landCalculator,
        PopulationCalculator $populationCalculator
        )
    {
        $this->queueService = app(QueueService::class);
        $this->trainingCalculator = app(TrainingCalculator::class);
        $this->unitHelper = app(UnitHelper::class);

        $this->improvementCalculator = $improvementCalculator;
        $this->spellCalculator = $spellCalculator;
        $this->militaryCalculator = $militaryCalculator;
        $this->landCalculator = $landCalculator;
        $this->populationCalculator = $populationCalculator;
    }

    /**
     * Does a military train action for a Dominion.
     *
     * @param Dominion $dominion
     * @param array $data
     * @return array
     * @throws Throwable
     */
    public function train(Dominion $dominion, array $data): array
    {
        $this->guardLockedDominion($dominion);

        $data = array_only($data, array_map(function ($value) {
            return "military_{$value}";
        }, $this->unitHelper->getUnitTypes()));

        $data = array_map('\intval', $data);

        $totalUnitsToTrain = array_sum($data);

        if ($totalUnitsToTrain <= 0) {
            throw new GameException('Training aborted due to bad input.');
        }

        # Poorly tested.
        if ($dominion->race->getPerkValue('cannot_train_spies') == 1 and isset($data['spies']) and $data['spies'] > 0)
        {
            throw new GameException($dominion->race->name . ' cannot train spies.');
        }
        if ($dominion->race->getPerkValue('cannot_train_wizards') == 1 and isset($data['wizards']) and $data['wizards'] > 0)
        {
            throw new GameException($dominion->race->name . ' cannot train wizards.');
        }
        if ($dominion->race->getPerkValue('cannot_train_archmages') == 1 and isset($data['archmages']) and $data['archmages'] > 0)
        {
            throw new GameException($dominion->race->name . ' cannot train Arch Mages.');
        }

        $totalCosts = [
            'platinum' => 0,
            'ore' => 0,
            'draftees' => 0,
            'wizards' => 0,

            //New unit cost resources
            'food' => 0,
            'mana' => 0,
            'gem' => 0,
            'lumber' => 0,
            'prestige' => 0,
            'boat' => 0,
            'champion' => 0,
            'soul' => 0,
            'wild_yeti' => 0,
            'morale' => 0,
            'unit1' => 0,
            'unit2' => 0,
            'unit3' => 0,
            'unit4' => 0,

            'spy' => 0,
            'wizard' => 0,
            'archmage' => 0,

        ];

        $unitsToTrain = [];

        $trainingCostsPerUnit = $this->trainingCalculator->getTrainingCostsPerUnit($dominion);

        foreach ($data as $unitType => $amountToTrain) {
            if (!$amountToTrain || $amountToTrain === 0) {
                continue;
            }

            if ($amountToTrain < 0) {
                throw new GameException('Training aborted due to bad input.');
            }

            $unitType = str_replace('military_', '', $unitType);

            $costs = $trainingCostsPerUnit[$unitType];

            foreach ($costs as $costType => $costAmount) {
                $totalCosts[$costType] += ($amountToTrain * $costAmount);
            }

            $unitsToTrain[$unitType] = $amountToTrain;
        }

        # Look for pairing_limit, cannot_be_trained, land_limit, and amount_limit
        foreach($unitsToTrain as $unitType => $amountToTrain)
        {
          if (!$amountToTrain)
          {
              continue;
          }

          $unitSlot = intval(str_replace('unit', '', $unitType));

          # Cannot be trained
          if($dominion->race->getUnitPerkValueForUnitSlot($unitSlot,'cannot_be_trained') and $amountToTrain > 0)
          {
            throw new GameException('This unit cannot be trained.');
          }

          # OK, unit can be trained. Let's check for pairing limits.
          $pairingLimit = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot,'pairing_limit');
          # [0] = unit limited by
          # [1] = limit

          if($pairingLimit)
          {

            // We have pairing limit for this unit.
            $pairingLimitedBy = intval($pairingLimit[0]);
            $pairingLimitedTo = $pairingLimit[1];

            // Evaluate the limit.

            # How many of the limiting unit does the dominion have?
            if($pairingLimitedBy == 1)
            {
              $pairingLimitedByTrained = $dominion->military_unit1;
            }
            elseif($pairingLimitedBy == 2)
            {
              $pairingLimitedByTrained = $dominion->military_unit2;
            }
            elseif($pairingLimitedBy == 3)
            {
              $pairingLimitedByTrained = $dominion->military_unit3;
            }
            elseif($pairingLimitedBy == 4)
            {
              $pairingLimitedByTrained = $dominion->military_unit4;
            }

            if( # Units trained + Units in Training + Units in Queue + Units to Train
                (($dominion->{'military_unit' . $unitSlot} +
                  $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit' . $unitSlot) +
                  $this->queueService->getInvasionQueueTotalByResource($dominion, 'military_unit' . $unitSlot) +
                  $amountToTrain))
                >
                ($pairingLimitedByTrained * $pairingLimitedTo)
              )
            {
              throw new GameException('You can at most have ' . number_format($pairingLimitedByTrained * $pairingLimitedTo) . ' of this unit. To train more, you need to first train more of their master unit.');
            }
          }

          # Pairing limit check complete.
          # Check for land limit.
          $landLimit = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot,'land_limit');
          if($landLimit)
          {
            // We have land limit for this unit.
            $landLimitedToLandType = 'land_'.$landLimit[0]; # Land type
            $landLimitedToAcres = (float)$landLimit[1]; # Acres per unit

            $acresOfLimitingLandType = $dominion->{$landLimitedToLandType};

            $upperLimit = intval($acresOfLimitingLandType / $landLimitedToAcres);

            if( # Units trained + Units in Training + Units in Queue + Units to Train
                (($dominion->{'military_unit' . $unitSlot} +
                  $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit' . $unitSlot) +
                  $this->queueService->getInvasionQueueTotalByResource($dominion, 'military_unit' . $unitSlot) +
                  $amountToTrain))
                >
                $upperLimit
              )
            {
              throw new GameException('You can at most have ' . number_format($upperLimit) . ' of this unit. To train more, you must have more acres of '. $landLimit[0] .'s.');
            }
          }
          # Land limit check complete.
          # Check for amount limit.
          $amountLimit = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot,'amount_limit');
          if($amountLimit)
          {

            if( # Units trained + Units in Training + Units in Queue + Units to Train
                (($dominion->{'military_unit' . $unitSlot} +
                  $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit' . $unitSlot) +
                  $this->queueService->getInvasionQueueTotalByResource($dominion, 'military_unit' . $unitSlot) +
                  $amountToTrain))
                >
                $amountLimit
              )
            {
              throw new GameException('You can at most have ' . number_format($amountLimit) . ' of this unit.');
            }
          }


          # Amount limit check complete.
          # Chcek for minimum WPA to train.
          $minimumWpaToTrain = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'minimum_wpa_to_train');
          if($minimumWpaToTrain)
          {
              if($this->militaryCalculator->getWizardRatio($dominion, 'offense') < $minimumWpaToTrain)
              {
                throw new GameException('You need at least ' . $minimumWpaToTrain . ' wizard ratio (on offense) to train this unit. You only have ' . $this->militaryCalculator->getWizardRatio($dominion) . '.');
              }
          }


        }

        if($totalCosts['platinum'] > $dominion->resource_platinum)
        {
          throw new GameException('Training failed due to insufficient platinum.');
        }
        if($totalCosts['ore'] > $dominion->resource_ore)
        {
          throw new GameException('Training failed due to insufficient ore.');
        }
        if($totalCosts['food'] > $dominion->resource_food)
        {
          throw new GameException('Training failed due to insufficient food.');
        }
        if($totalCosts['mana'] > $dominion->resource_mana)
        {
          throw new GameException('Training failed due to insufficient mana.');
        }
        if($totalCosts['gem'] > $dominion->resource_gems)
        {
          throw new GameException('Training failed due to insufficient gems.');
        }
        if($totalCosts['lumber'] > $dominion->resource_lumber)
        {
          throw new GameException('Training failed due to insufficient lumber.');
        }
        if($totalCosts['prestige'] > $dominion->prestige)
        {
          throw new GameException('Training failed due to insufficient prestige.');
        }
        if($totalCosts['boat'] > $dominion->resource_boats)
        {
          throw new GameException('Training failed due to insufficient boats.');
        }
        if($totalCosts['champion'] > $dominion->resource_champion)
        {
          throw new GameException('You do not have enough Champions.');
        }
        if($totalCosts['soul'] > $dominion->resource_soul)
        {
          throw new GameException('Insufficient souls. Collect more souls.');
        }
        if($totalCosts['wild_yeti'] > $dominion->resource_wild_yeti)
        {
          throw new GameException('You do not have enough wild yetis.');
        }
        if($totalCosts['morale'] > $dominion->morale)
        {
          # This is fine. We just have to make sure that morale doesn't dip below 0.
          #throw new GameException('Your morale is too low to train. Improve your morale or train fewer units.');
        }
        if(
            $totalCosts['unit1'] > $dominion->military_unit1 OR
            $totalCosts['unit2'] > $dominion->military_unit2 OR
            $totalCosts['unit3'] > $dominion->military_unit3 OR
            $totalCosts['unit4'] > $dominion->military_unit4
            )
        {
          throw new GameException('Insufficient units to train this unit.');
        }
        if($totalCosts['spy'] > $dominion->military_spies)
        {
          throw new GameException('Training failed due to insufficient spies.');
        }
        if($totalCosts['wizard'] > $dominion->military_wizards or $totalCosts['wizards'] > $dominion->military_wizards)
        {
          throw new GameException('Training failed due to insufficient wizards.');
        }
        if($totalCosts['archmage'] > $dominion->military_archmages)
        {
          throw new GameException('Training failed due to insufficient Arch Mages.');
        }


        if ($totalCosts['draftees'] > $dominion->military_draftees)
        {
            throw new GameException('Training aborted due to lack of draftees.');
        }

        $newDraftelessUnitsToHouse = 0;
        foreach($unitsToTrain as $unitSlot => $unitAmountToTrain)
        {
            $unitSlot = intval(str_replace('unit','',$unitSlot));
            # If a unit counts towards population, add to $unitsToTrainNeedingHousingWithoutDraftees
            if (
                  !$dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'does_not_count_as_population') and
                  $dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'no_draftee')
              )
            {
              $newDraftelessUnitsToHouse += $unitAmountToTrain;
            }

        }

        if ($newDraftelessUnitsToHouse > 0 and ($newDraftelessUnitsToHouse + $this->populationCalculator->getPopulationMilitary($dominion)) > $this->populationCalculator->getMaxPopulation($dominion))
        {
            throw new GameException('Training failed as training would exceed your max population');
        }

        # $unitXtoBeTrained must be set (including to 0) for Armada/IG stuff to work.
        if(isset($unitsToTrain['unit3']) or isset($unitsToTrain['unit4']))
        {
          // Wonky workaround.
          if(isset($unitsToTrain['unit3']))
          {
            $unit3toBeTrained = intval($unitsToTrain['unit3']);
          }
          else
          {
            $unit3toBeTrained = 0;
          }

          if(isset($unitsToTrain['unit4']))
          {
            $unit4toBeTrained = intval($unitsToTrain['unit4']);
          }
          else
          {
            $unit4toBeTrained = 0;
          }

          // If training elites, check if ARMADA or IMPERIAL GNOME to calculate unit housing (Docks / Factories)
          // ARMADA: Max 2 Boats per Dock (+ Harbour)
          if (
            $dominion->race->name == 'Armada'
            and (
                  ($dominion->military_unit3 + $dominion->military_unit4) +
                  ($unit3toBeTrained + $unit4toBeTrained) +
                  ($this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit3') + $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit4')) +
                  ($this->queueService->getInvasionQueueTotalByResource($dominion, 'military_unit3') + $this->queueService->getInvasionQueueTotalByResource($dominion, 'military_unit4'))

                )

                  // If all the above is greater than Docks*2*Harbor
                  > ($dominion->building_dock * 2 * (1 + $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'harbor'))))
          {
            throw new GameException('You cannot control that many ships. Max 2 ships per Dock. Increased by Harbor.');
          }
          // IMPERIAL GNOME: Max 2 Machines per Factory (+ Science)
          if (
            $dominion->race->name == 'Imperial Gnome'
            and (
                  ($dominion->military_unit3 + $dominion->military_unit4) +
                  ($unit3toBeTrained + $unit4toBeTrained) +
                  ($this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit3') + $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit4')) +
                  ($this->queueService->getInvasionQueueTotalByResource($dominion, 'military_unit3') + $this->queueService->getInvasionQueueTotalByResource($dominion, 'military_unit4'))
                )

                  // If all the above is greater than Factories*2*Science
                  > ($dominion->building_factory * 2 * (1 + $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'workshops'))))
          {
            throw new GameException('You cannot control that many machines. Max 2 machines per Factory. Increased by improvements into Workshops.');
          }
        }



        DB::transaction(function () use ($dominion, $data, $totalCosts) {
            $dominion->resource_platinum -= $totalCosts['platinum'];
            $dominion->resource_ore -= $totalCosts['ore'];
            $dominion->military_draftees -= $totalCosts['draftees'];
            $dominion->military_wizards -= $totalCosts['wizards'];

            // New unit cost resources.
            $dominion->resource_food -= $totalCosts['food'];
            $dominion->resource_mana -= $totalCosts['mana'];
            $dominion->resource_gems -= $totalCosts['gem'];
            $dominion->resource_lumber -= $totalCosts['lumber'];
            $dominion->prestige -= $totalCosts['prestige'];
            $dominion->resource_boats -= $totalCosts['boat'];
            $dominion->resource_champion -= $totalCosts['champion'];
            $dominion->resource_soul -= $totalCosts['soul'];
            $dominion->resource_wild_yeti -= $totalCosts['wild_yeti'];
            $dominion->morale = max(0, ($dominion->morale - $totalCosts['morale']));

            $dominion->military_unit1 -= $totalCosts['unit1'];
            $dominion->military_unit2 -= $totalCosts['unit2'];
            $dominion->military_unit3 -= $totalCosts['unit3'];
            $dominion->military_unit4 -= $totalCosts['unit4'];

            $dominion->military_spies -= $totalCosts['spy'];
            $dominion->military_wizards -= $totalCosts['wizard'];
            $dominion->military_archmages -= $totalCosts['archmage'];

            # Update spending statistics.
            $dominion->stat_total_platinum_spent_training += $totalCosts['platinum'];
            $dominion->stat_total_food_spent_training += $totalCosts['food'];
            $dominion->stat_total_lumber_spent_training += $totalCosts['lumber'];
            $dominion->stat_total_mana_spent_training += $totalCosts['mana'];
            $dominion->stat_total_ore_spent_training += $totalCosts['ore'];
            $dominion->stat_total_gem_spent_training += $totalCosts['gem'];
            $dominion->stat_total_unit1_spent_training += $totalCosts['unit1'];
            $dominion->stat_total_unit2_spent_training += $totalCosts['unit2'];
            $dominion->stat_total_unit3_spent_training += $totalCosts['unit3'];
            $dominion->stat_total_unit4_spent_training += $totalCosts['unit4'];
            $dominion->stat_total_spies_spent_training += $totalCosts['spy'];
            $dominion->stat_total_wizards_spent_training += $totalCosts['wizard'];
            $dominion->stat_total_wizards_spent_training += $totalCosts['wizards'];
            $dominion->stat_total_archmages_spent_training += $totalCosts['archmage'];
            $dominion->stat_total_wild_yeti_spent_training += $totalCosts['wild_yeti'];
            $dominion->stat_total_soul_spent_training += $totalCosts['soul'];
            $dominion->stat_total_champion_spent_training += $totalCosts['champion'];

            // $data:
            # unit1 => int
            # unit2 => int
            # et cetera

            foreach($data as $unit => $amountToTrain)
            {

              // Reset at each run of loop.
              $hoursSpecs = 9;
              $hoursElites = 12;
              $timeReductionSpecs = 0;
              $timeReductionElites = 0;

              // Lux: Spell (reduce training times by 2 ticks)
              if ($this->spellCalculator->isSpellActive($dominion, 'aurora'))
              {
                $timeReductionSpecs += 4;
                $timeReductionElites += 4;
              }
              // Legion: Spell (reduce training times by 4 ticks)
              if ($this->spellCalculator->isSpellActive($dominion, 'call_to_arms'))
              {
                $timeReductionSpecs += min($this->militaryCalculator->getRecentlyInvadedCount($dominion), 4) * 2;
                $timeReductionElites += min($this->militaryCalculator->getRecentlyInvadedCount($dominion), 4) * 2;
              }
              // Spell: Spawning Pool (increase units trained, for free)
              if ($this->spellCalculator->isSpellActive($dominion, 'spawning_pool') and $unit == 'military_unit1')
              {
                $amountToTrainMultiplier = ($dominion->land_swamp / $this->landCalculator->getTotalLand($dominion)) / 2;
                $amountToTrain = round($amountToTrain * (1 + $amountToTrainMultiplier));
              }
              // Legion and Elementals: all units train in 9 hours.
              if($dominion->race->getPerkValue('all_units_trained_in_9hrs'))
              {
                $timeReductionElites += 3;
              }
              // Look for faster training.
              if($fasterTraining = $dominion->race->getUnitPerkValueForUnitSlot(intval(str_replace('military_unit','',$unit)), 'faster_training') and $amountToTrain > 0)
              {
                $timeReductionSpecs += min($fasterTraining, $hoursSpecs-2);
                $timeReductionElites += min($fasterTraining, $hoursElites-2);
              }
              // Look for reduced training times.
              if($timeReductionSpecs > 0)
              {
                $hoursSpecs -= $timeReductionSpecs;
              }
              if($timeReductionElites > 0)
              {
                $hoursElites -= $timeReductionElites;
              }
              // Look for instant training.
              if($dominion->race->getUnitPerkValueForUnitSlot(intval(str_replace('military_unit','',$unit)), 'instant_training') and $amountToTrain > 0)
              {
                $dominion->{"$unit"} += $amountToTrain;
              }
              // If not instant training, queue resource.
              else
              {
                # Default state
                $data = array($unit => $amountToTrain);

                if($unit == 'military_unit1' or $unit == 'military_unit2')
                {
                  $hours = $hoursSpecs;
                }
                else
                {
                  $hours = $hoursElites;
                }

                // $hours must always be at least 1.
                $hours = max($hours,1);

                $this->queueService->queueResources('training', $dominion, $data, $hours);

                $dominion->save(['event' => HistoryService::EVENT_ACTION_TRAIN]);
              }

              #unset($hours);
              #unset($hoursSpecs);
              #unset($hoursElites);
              #unset($timeReductionSpecs);
              #unset($timeReductionElites);

            }

            #$this->queueService->queueResources('training', $dominion, $nineHourData, ($hoursSpecs + $hours_modifier));
            #$this->queueService->queueResources('training', $dominion, $data, ($hoursElites + $hours_modifier));
        });

        return [
            'message' => $this->getReturnMessageString($dominion, $unitsToTrain, $totalCosts),
            'data' => [
                'totalCosts' => $totalCosts,
            ],
        ];
    }

    /**
     * Returns the message for a train action.
     *
     * @param Dominion $dominion
     * @param array $unitsToTrain
     * @param array $totalCosts
     * @return string
     */
    protected function getReturnMessageString(Dominion $dominion, array $unitsToTrain, array $totalCosts): string
    {
        $unitsToTrainStringParts = [];

        foreach ($unitsToTrain as $unitType => $amount) {
            if ($amount > 0) {
                $unitName = strtolower($this->unitHelper->getUnitName($unitType, $dominion->race));

                // str_plural() isn't perfect for certain unit names. This array
                // serves as an override to use (see issue #607)
                // todo: Might move this to UnitHelper, especially if more
                //       locations need unit name overrides
                $overridePluralUnitNames = [
                    'shaman' => 'shamans',
                    'abscess' => 'abscesses',
                    'werewolf' => 'werewolves',
                    'snow witch' => 'snow witches',
                    'lich' => 'liches',
                    'progeny' => 'progenies',
                    'fallen' => 'fallen',
                    'goat witch' => 'goat witches',
                    'phoenix' => 'phoenix',
                    'master thief' => 'master thieves',
                    'cavalry' => 'cavalries',
                    'pikeman' => 'pikemen',
                    'norn' => 'nornir',
                    'berserk' => 'berserkir',
                    'valkyrja' => 'valkyrjur',
                    'einherjar' => 'einherjar',
                    'hex' => 'hex',
                    'vex' => 'vex',
                    'pax' => 'pax',
                ];

                $amountLabel = number_format($amount);

                if (array_key_exists($unitName, $overridePluralUnitNames)) {
                    if ($amount === 1) {
                        $unitLabel = $unitName;
                    } else {
                        $unitLabel = $overridePluralUnitNames[$unitName];
                    }
                } else {
                    $unitLabel = str_plural(str_singular($unitName), $amount);
                }

                $unitsToTrainStringParts[] = "{$amountLabel} {$unitLabel}";
            }
        }

        $unitsToTrainString = generate_sentence_from_array($unitsToTrainStringParts);

        $trainingCostsStringParts = [];
        foreach ($totalCosts as $costType => $cost) {
            if ($cost === 0) {
                continue;
            }

            $costType = str_singular($costType);
#            if (!\in_array($costType, ['platinum', 'ore'], true)) {
            if (!\in_array($costType, ['platinum', 'ore', 'food', 'mana', 'gem', 'lumber', 'prestige', 'boat', 'champion', 'soul', 'morale'], true))
            {
                $costType = str_plural($costType, $cost);
            }
            $trainingCostsStringParts[] = (number_format($cost) . ' ' . $costType);

        }

        $trainingCostsString = generate_sentence_from_array($trainingCostsStringParts);

        $message = sprintf(
            'Training of %s begun at a cost of %s.',
            str_replace('And', 'and', ucwords($unitsToTrainString)),
            str_replace('Wild_yeti','wild yeti',str_replace(' Morale', '% Morale', str_replace('And', 'and', ucwords($trainingCostsString))))
        );

        return $message;
    }
}
