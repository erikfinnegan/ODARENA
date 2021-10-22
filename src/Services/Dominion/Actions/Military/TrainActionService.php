<?php

namespace OpenDominion\Services\Dominion\Actions\Military;

use DB;
use Throwable;

use OpenDominion\Calculators\Dominion\Actions\TrainingCalculator;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Models\Building;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Resource;
use OpenDominion\Models\Tech;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Services\Dominion\ResourceService;
use OpenDominion\Services\Dominion\StatsService;
use OpenDominion\Traits\DominionGuardsTrait;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\BuildingCalculator;
use OpenDominion\Calculators\Dominion\PopulationCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Calculators\Dominion\Actions\TechCalculator;
use OpenDominion\Helpers\RaceHelper;

class TrainActionService
{
    use DominionGuardsTrait;

    public function __construct(
        ImprovementCalculator $improvementCalculator,
        SpellCalculator $spellCalculator,
        MilitaryCalculator $militaryCalculator,
        LandCalculator $landCalculator,
        PopulationCalculator $populationCalculator,
        TechCalculator $techCalculator
        )
    {
        $this->queueService = app(QueueService::class);
        $this->trainingCalculator = app(TrainingCalculator::class);
        $this->unitHelper = app(UnitHelper::class);
        $this->raceHelper = app(RaceHelper::class);
        $this->buildingCalculator = app(BuildingCalculator::class);
        $this->resourceCalculator = app(ResourceCalculator::class);
        $this->resourceService = app(ResourceService::class);
        $this->statsService = app(StatsService::class);

        $this->improvementCalculator = $improvementCalculator;
        $this->spellCalculator = $spellCalculator;
        $this->militaryCalculator = $militaryCalculator;
        $this->landCalculator = $landCalculator;
        $this->populationCalculator = $populationCalculator;
        $this->techCalculator = $techCalculator;
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

        // Qur: Statis
        if($dominion->getSpellPerkValue('stasis'))
        {
            throw new GameException('You cannot train while you are in stasis.');
        }

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
            'gold' => 0,
            'ore' => 0,
            'draftees' => 0,
            'wizards' => 0,
            'food' => 0,
            'mana' => 0,
            'gems' => 0,
            'lumber' => 0,
            'prestige' => 0,
            'champion' => 0,
            'soul' => 0,
            'blood' => 0,
            'morale' => 0,
            'wizard_strength' => 0,
            'spy_strength' => 0,
            'peasant' => 0,
            'unit1' => 0,
            'unit2' => 0,
            'unit3' => 0,
            'unit4' => 0,
            'brimmer' => 0,
            'prisoner' => 0,
            'horse' => 0,

            'spy' => 0,
            'wizard' => 0,
            'archmage' => 0,

        ];

        $unitsToTrain = [];

        $trainingCostsPerUnit = $this->trainingCalculator->getTrainingCostsPerUnit($dominion);

        foreach ($data as $unitType => $amountToTrain)
        {
            if (!$amountToTrain || $amountToTrain === 0)
            {
                continue;
            }

            if ($amountToTrain < 0)
            {
                throw new GameException('Training aborted due to bad input.');
            }

            $unitType = str_replace('military_', '', $unitType);

            $costs = $trainingCostsPerUnit[$unitType];

            foreach ($costs as $costType => $costAmount)
            {
                if($costType === 'draftees')
                {
                    $totalCosts[$costType] += ceil($amountToTrain * $costAmount);
                }
                else
                {
                    $totalCosts[$costType] += ($amountToTrain * $costAmount);
                }
            }

            $unitsToTrain[$unitType] = $amountToTrain;
        }

        /*
          Look for:
          pairing_limit
          cannot_be_trained
          land_limit
          amount_limit
          building_limit
          minimum_wpa_to_train
          victories_limit
          housing_count
          advancements_required_to_train
        */
        foreach($unitsToTrain as $unitType => $amountToTrain)
        {
            if (!$amountToTrain)
            {
                continue;
            }

            $unitSlot = intval(str_replace('unit', '', $unitType));

            $unitToTrain = $dominion->race->units->filter(function ($unit) use ($unitSlot) {
                return ($unit->slot === $unitSlot);
            })->first();

            # Cannot be trained
            if($dominion->race->getUnitPerkValueForUnitSlot($unitSlot,'cannot_be_trained') and $amountToTrain > 0)
            {
              throw new GameException('This unit cannot be trained.');
            }

            # OK, unit can be trained. Let's check for pairing limits.
            if(!$this->unitHelper->checkUnitLimitForTraining($dominion, $unitSlot, $amountToTrain))
            {
                $unit = $dominion->race->units->filter(function ($unit) use ($unitSlot) {
                    return ($unit->slot === $unitSlot);
                })->first();

                throw new GameException('You can at most control ' . number_format($this->unitHelper->getUnitMaxCapacity($dominion, $unitSlot)) . ' ' . str_plural($unit->name) . '. To control more, you need to first have more of their superior unit.');
            }

            # Check for minimum WPA to train.
            $minimumWpaToTrain = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'minimum_wpa_to_train');
            if($minimumWpaToTrain)
            {
                if($this->militaryCalculator->getWizardRatio($dominion, 'offense') < $minimumWpaToTrain)
                {
                  throw new GameException('You need at least ' . $minimumWpaToTrain . ' wizard ratio (on offense) to train this unit. You only have ' . $this->militaryCalculator->getWizardRatio($dominion) . '.');
                }
            }
            # Minimum WPA check complete.

            # Check for advancements required limit.
            $advancementsLimit = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot,'advancements_required_to_train');
            if($advancementsLimit)
            {
                $advancementKeys = explode(';',$advancementsLimit);
                $advancements = [];

                foreach ($advancementKeys as $index => $advancementKey)
                {
                    $advancement = Tech::where('key', $advancementKey)->firstOrFail();
                    if(!$this->techCalculator->hasTech($dominion, $advancement))
                    {
                        throw new GameException('You do not have the required advancements to train this unit.');
                    }
                }
            }
            # Advancements check complete.
        }

      foreach($totalCosts as $resourceKey => $amount)
      {
          if(in_array($resourceKey, $dominion->race->resources))
          {
              $resource = Resource::where('key', $resourceKey)->first();
              if($totalCosts[$resourceKey] > $this->resourceCalculator->getAmount($dominion, $resourceKey))
              {
                  throw new GameException('Training failed due to insufficient ' . $resource->name . '. You tried to spend ' . number_format($totalCosts[$resourceKey]) .  ' but only have ' . number_format($this->resourceCalculator->getAmount($dominion, $resourceKey)) . '.');
              }
          }

          if($totalCosts['unit1'] > $dominion->military_unit1)
          {
              $unitNeeded = $dominion->race->units->filter(function ($unit) {
                  return ($unit->slot === 1);
              })->first();

              throw new GameException('Insufficient ' . str_plural($unitNeeded->name) .  ' to train ' . number_format($amountToTrain) . ' ' . str_plural($unitToTrain->name, $amountToTrain) . '.');
          }

          if($totalCosts['unit2'] > $dominion->military_unit2)
          {
              $unitNeeded = $dominion->race->units->filter(function ($unit) {
                  return ($unit->slot === 2);
              })->first();

              throw new GameException('Insufficient ' . str_plural($unitNeeded->name) .  ' to train ' . number_format($amountToTrain) . ' ' . str_plural($unitToTrain->name, $amountToTrain) . '.');
          }

          if($totalCosts['unit3'] > $dominion->military_unit3)
          {
              $unitNeeded = $dominion->race->units->filter(function ($unit) {
                  return ($unit->slot === 3);
              })->first();

              throw new GameException('Insufficient ' . str_plural($unitNeeded->name) .  ' to train ' . number_format($amountToTrain) . ' ' . str_plural($unitToTrain->name, $amountToTrain) . '.');
          }

          if($totalCosts['unit4'] > $dominion->military_unit4)
          {
              $unitNeeded = $dominion->race->units->filter(function ($unit) {
                  return ($unit->slot === 4);
              })->first();

              throw new GameException('Insufficient ' . str_plural($unitNeeded->name) .  ' to train ' . number_format($amountToTrain) . ' ' . str_plural($unitToTrain->name, $amountToTrain) . '.');
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
              throw new GameException('Training aborted due to lack of ' . str_plural($this->raceHelper->getDrafteesTerm($dominion->race)) . '.');
          }

          if($totalCosts['spy_strength'] > $dominion->spy_strength)
          {
            throw new GameException('Training failed due to insufficient spy strength.');
          }

          if($totalCosts['wizard_strength'] > $dominion->wizard_strength)
          {
            throw new GameException('Training failed due to insufficient wizard strength.');
          }
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

        if (($dominion->race->name !== 'Cult' and $dominion->race->name !== 'Yeti') and ($newDraftelessUnitsToHouse > 0) and ($newDraftelessUnitsToHouse + $this->populationCalculator->getPopulationMilitary($dominion)) > $this->populationCalculator->getMaxPopulation($dominion))
        {
            throw new GameException('Training failed as training would exceed your max population');
        }

        DB::transaction(function () use ($dominion, $data, $totalCosts, $unitSlot, $unitAmountToTrain) {
            $dominion->military_draftees -= $totalCosts['draftees'];
            $dominion->military_wizards -= $totalCosts['wizards'];
            $dominion->prestige -= $totalCosts['prestige'];
            $dominion->morale = max(0, ($dominion->morale - $totalCosts['morale']));
            $dominion->peasants -= $totalCosts['peasant'];
            $dominion->military_unit1 -= $totalCosts['unit1'];
            $dominion->military_unit2 -= $totalCosts['unit2'];
            $dominion->military_unit3 -= $totalCosts['unit3'];
            $dominion->military_unit4 -= $totalCosts['unit4'];
            $dominion->military_spies -= $totalCosts['spy'];
            $dominion->military_wizards -= $totalCosts['wizard'];
            $dominion->military_archmages -= $totalCosts['archmage'];
            $dominion->spy_strength -= $totalCosts['spy_strength'];
            $dominion->wizard_strength -= $totalCosts['wizard_strength'];

            # Update spending statistics.
            foreach($totalCosts as $resource => $amount)
            {
                if($amount > 0)
                {
                    $resourceString = $resource;

                    if($resourceString == 'peasant')
                    {
                        $resourceString = 'peasants';
                    }
                    if($resourceString == 'spy')
                    {
                        $resourceString = 'spies';
                    }
                    if($resourceString == 'wizard')
                    {
                        $resourceString = 'wizards';
                    }
                    if($resourceString == 'archmage')
                    {
                        $resourceString = 'archmages';
                    }

                    $this->statsService->updateStat($dominion, ($resourceString . '_training'), abs($totalCosts[$resource]));
                }
            }

            # Resources 2.0
            $resourceCosts = [];
            foreach($totalCosts as $resourceKey => $cost)
            {
                if(in_array($resourceKey, $dominion->race->resources))
                {
                    $resourceCosts[$resourceKey] = $cost*-1;
                }
            }
            $this->resourceService->updateResources($dominion, $resourceCosts);

            foreach($data as $unitType => $amountToTrain)
            {
                if($amountToTrain > 0)
                {
                    $unitStatsName = str_replace('military_','',$unitType);
                    $slot = (int)str_replace('military_unit','',$unitType);

                    $unit = $dominion->race->units->filter(function ($unit) use ($slot) {
                        return ($unit->slot === $slot);
                    })->first();

                    if(isset($unit))
                    {
                        $ticks = $unit->training_time;
                    }
                    else
                    {
                        $ticks = 12; # WTF?
                    }

                    if($unitType == 'military_wizards' and $dominion->race->getPerkValue('wizard_training_time'))
                    {
                        $ticks = $dominion->race->getPerkValue('wizard_training_time');
                    }

                    // Spell
                    $ticks += $dominion->getSpellPerkValue('training_time_raw');
                    $ticks += $dominion->title->getPerkValue('training_time_raw');

                    // Spell: Spawning Pool (increase units trained, for free)
                    if ($this->spellCalculator->isSpellActive($dominion, 'spawning_pool') and $unitType == 'military_unit1')
                    {
                        $amountToTrainMultiplier = ($dominion->land_swamp / $this->landCalculator->getTotalLand($dominion));
                        $amountToTrain = floor($amountToTrain * (1 + $amountToTrainMultiplier));
                    }

                    if(in_array($slot, [1,2,3,4]))
                    {
                        $amountToTrain *= (1 + $dominion->getBuildingPerkMultiplier('extra_units_trained'));
                    }

                    # Multiplier
                    $ticksMultiplier = 1;
                    $ticksMultiplier += $dominion->getImprovementPerkMultiplier('training_time_mod');
                    $ticksMultiplier += $dominion->getBuildingPerkMultiplier('training_time_mod');
                    $ticksMultiplier += $dominion->getBuildingPerkMultiplier('training_time_mod');

                    $ticks = (int)ceil($ticks * $ticksMultiplier);

                    $this->statsService->updateStat($dominion, ($unitStatsName . '_trained'), $amountToTrain);

                    // Look for instant training.
                    if($ticks === 0 and $amountToTrain > 0)
                    {
                        $dominion->{"$unitType"} += $amountToTrain;
                        $dominion->save(['event' => HistoryService::EVENT_ACTION_TRAIN]);
                    }
                    // If not instant training, queue resource.
                    else
                    {
                        # Default state
                        $data = array($unitType => $amountToTrain);

                        // $hours must always be at least 1.
                        $ticks = max($ticks,1);

                        $this->queueService->queueResources('training', $dominion, $data, $ticks);

                        $dominion->save(['event' => HistoryService::EVENT_ACTION_TRAIN]);
                    }
                }
            }
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
                    'berserk' => 'berserkir',
                    'norn' => 'nornir',
                    'valkyrja' => 'valkyrjur',
                    'einherjar' => 'einherjar',
                    'huskarl' => 'huskarlar',
                    'jötunn' => 'jötnar',
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
        foreach ($totalCosts as $costType => $cost)
        {
            if ($cost === 0)
            {
                continue;
            }

            $costType = str_singular($costType);

            if(in_array($costType, ['unit1','unit2','unit3','unit4']))
            {
                $slot = (int)str_replace('unit','',$costType);

                $unit = $dominion->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $costType = str_plural($unit->name, $cost);
            }

#            if (!\in_array($costType, ['gold', 'ore'], true)) {
            if (!\in_array($costType, ['gold', 'ore', 'food', 'mana', 'gems', 'lumber', 'prestige', 'champion', 'soul', 'blood', 'morale', 'peasant'], true))
            {
                $costType = str_plural($costType, $cost);
            }

            $trainingCostsStringParts[] = (number_format($cost) . ' ' . $costType);

        }

        $trainingCostsString = generate_sentence_from_array($trainingCostsStringParts);

        $message = sprintf(
            'Training of %s begun at a cost of %s.',
            str_replace('And', 'and', ucwords($unitsToTrainString)),
            str_replace(' Spy_strengths', '% Spy Strength', str_replace(' Wizard_strengths', '% Wizard Strength', str_replace(' Morale', '% Morale', str_replace('And', 'and', ucwords($trainingCostsString)))))
        );

        return $message;
    }
}
