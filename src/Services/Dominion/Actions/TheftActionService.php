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
use OpenDominion\Models\Unit;

use OpenDominion\Helpers\UnitHelper;

use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Calculators\Dominion\TheftCalculator;

use OpenDominion\Services\NotificationService;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Services\Dominion\ProtectionService;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Services\Dominion\ResourceService;

class TheftActionService
{

    public function __construct()
    {
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->rangeCalculator = app(RangeCalculator::class);
        $this->resourceCalculator = app(ResourceCalculator::class);
        $this->theftCalculator = app(TheftCalculator::class);

        $this->notificationService = app(NotificationService::class);
        $this->protectionService = app(ProtectionService::class);
        $this->queueService = app(QueueService::class);
        $this->resourceService = app(ResourceService::class);


        $this->unitHelper = app(UnitHelper::class);
    }

    public function steal(Dominion $thief, Dominion $target, Resource $resource, array $units): array
    {

        DB::transaction(function () use ($thief, $target, $resource, $units)
        {
            // Sanitize input
            $units = array_map('intval', array_filter($units));
            $landRatio = $this->rangeCalculator->getDominionRange($thief, $target) / 100;

            // Checks
            if (array_sum($units) <= 0)
            {
                throw new GameException('You need to send at least some units.');
            }

            if ($this->protectionService->isUnderProtection($thief))
            {
                throw new GameException('You cannot steal while under protection.');
            }

            if ($this->protectionService->isUnderProtection($thief))
            {
                throw new GameException('You cannot steal from dominions which are under protection.');
            }

            if (!$this->rangeCalculator->isInRange($thief, $target))
            {
                throw new GameException('You cannot steal from dominions outside of your range.');
            }

            if ($thief->round->id !== $target->round->id)
            {
                throw new GameException('Nice try, but you cannot steal cross-round.');
            }

            if ($thief->realm->id === $target->realm->id)
            {
                throw new GameException('Nice try, but you cannot steal from other dominions of the same realm.');
            }

            if ($thief->id == $target->id)
            {
                throw new GameException('Nice try, but you steal from invade yourself.');
            }

            if ($resource->key == 'mana')
            {
                throw new GameException('You do not currently have the ability to steal ' . $resource->name . '.');
            }

            if (!in_array($resource->key, $thief->race->resources))
            {
                throw new GameException($resource->name . ' is not used by ' . $thief->race->name . ', so you cannot steal it.');
            }

            if (!in_array($resource->key, $target->race->resources))
            {
                throw new GameException($target->race->name . ' does not use ' . $resource->name . '.');
            }

            if (!$this->passes43RatioRule($thief, $target, $landRatio, $units))
            {
                throw new GameException('You are sending out too much OP, based on your new home DP (4:3 rule).');
            }


            if (!$this->hasEnoughUnitsAtHome($thief, $units))
            {
                throw new GameException('You don\'t have enough units at home to send this many units.');
            }

            foreach($units as $slot => $amount)
            {
                $unit = $thief->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                if($amount < 0)
                {
                    throw new GameException('Theft was canceled due to bad input.');
                }

                if($slot !== 'spies')
                {
                    if(!$this->unitHelper->isUnitOffensiveSpy($unit))
                    {
                        throw new GameException($unit->name . ' is not a spy unit and cannot be sent on theft missions.');
                    }

                    # OK, unit can be trained. Let's check for pairing limits.
                    if($this->unitHelper->unitHasCapacityLimit($thief, $slot) and !$this->unitHelper->checkUnitLimitForInvasion($thief, $slot, $amount))
                    {
                        throw new GameException('You can at most control ' . number_format($this->unitHelper->getUnitMaxCapacity($thief, $slot)) . ' ' . str_plural($unit->name) . '. To control more, you need to first have more of their superior unit.');
                    }
                }
             }

            if ($thief->race->getPerkValue('cannot_steal'))
            {
                throw new GameException($thief->race->name . ' cannot steal.');
            }

            // Spell: Rainy Season (cannot invade)
            if ($thief->getSpellPerkValue('cannot_steal'))
            {
                throw new GameException('A spell is preventing from you steal.');
            }

            // Cannot invade until round has started.
            if(!$thief->round->hasStarted())
            {
                throw new GameException('You cannot steal until the round has started.');
            }

            // Cannot invade after round has ended.
            if($thief->round->hasEnded())
            {
                throw new GameException('You cannot steal after the round has ended.');
            }

            // Qur: Statis cannot be invaded.
            if($target->getSpellPerkValue('stasis'))
            {
                throw new GameException('A magical stasis surrounds the Qurrian lands, making it impossible for your spies to steal.');
            }

            // Qur: Statis cannot invade.
            if($thief->getSpellPerkValue('stasis'))
            {
                throw new GameException('You cannot steal while you are in stasis.');
            }

            # CHECKS COMPLETE

            # Calculate spy units
            $spyUnits = $thief->military_spies;
            foreach ($thief->race->units as $unit)
            {
                if($this->unitHelper->isUnitOffensiveSpy($unit))
                {
                    $spyUnits += $this->militaryCalculator->getTotalUnitsForSlot($thief, $unit->slot);
                }
            }

            $spyUnitsSentRatio = (int)ceil(array_sum($units) / $spyUnits * 100);

            $thief->spy_strength -= min($spyUnitsSentRatio, $thief->spy_strength);

            $this->theft['units'] = $units;
            $this->theft['spy_units_sent_ratio'] = $spyUnitsSentRatio;
            $this->theft['resource']['key'] = $resource->key;
            $this->theft['resource']['name'] = $resource->name;

            # Casualties ???
            $killedUnits = $this->theftCalculator->getUnitsKilled($thief, $target, $units);
            $survivingUnits = $units;

            foreach($killedUnits as $slot => $amountKilled)
            {
                $survivingUnits[$slot] -= $amountKilled;
            }

            $this->theft['killed_units'] = $killedUnits;
            $this->theft['returning_units'] = $survivingUnits;

            # Determine how much was stolen
            $this->theft['amount_owned'] = $this->resourceCalculator->getAmount($target, $resource->key);
            $amountStolen = $this->theftCalculator->getTheftAmount($thief, $target, $resource, $survivingUnits);
            $this->theft['amount_stolen'] = $amountStolen;

            # Queue returning resources
            $ticks = 6;

            $this->queueService->queueResources(
                'theft',
                $thief,
                [$resource->key => $amountStolen],
                $ticks
            );

            # Remove units
            foreach($units as $slot => $amount)
            {
                if($slot == 'spies')
                {
                    $thief->military_spies -= $amount;
                }
                else
                {
                    $thief->{'military_unit' . $slot} -= $amount;
                }
            }

            # Queue returning units
            $ticks = 6;

            foreach($survivingUnits as $slot => $amount)
            {
                if($slot == 'spies')
                {
                    $unitType = 'military_spies';
                }
                else
                {
                    $unitType = 'military_unit' . $slot;
                }

                $this->queueService->queueResources(
                    'theft',
                    $thief,
                    [$unitType => $amount],
                    $ticks
                );
            }

            #dd('Steal ' . $resource->name . ' from ' . $target->name, $units, $spyUnits, $this->theft);

            $this->theftEvent = GameEvent::create([
                'round_id' => $thief->round_id,
                'source_type' => Dominion::class,
                'source_id' => $thief->id,
                'target_type' => Dominion::class,
                'target_id' => $target->id,
                'type' => 'theft',
                'data' => $this->theft,
                'tick' => $thief->round->ticks
            ]);

            $this->notificationService->queueNotification('theft', [
                '_routeParams' => [(string)$this->theftEvent->id],
                'thiefDominionId' => $thief->id,
                'unitsKilled' => $this->theft['killed_units'],
                'resource' => $resource->id,
                'amountLost' => $this->theft['amount_stolen']
            ]);

            $target->save(['event' => HistoryService::EVENT_ACTION_THEFT]);
            $thief->save(['event' => HistoryService::EVENT_ACTION_THEFT]);

        });

        $this->notificationService->sendNotifications($target, 'irregular_dominion');

        $message = sprintf(
            'Your %s infiltrate %s (#%s), stealing %s %s.',
            (array_sum($units) > $units['spies']) ? 'units' : 'spies',
            $target->name,
            $target->realm->number,
            number_format($this->theft['amount_stolen']),
            $this->theft['resource']['name']
        );

        $alertType = 'success';

        return [
            'message' => $message,
            'alert-type' => $alertType,
            'redirect' => route('dominion.event', [$this->theftEvent->id])
        ];
    }

    /**
     * Check if dominion has enough units at home to send out.
     *
     * @param Dominion $thief
     * @param array $units
     * @return bool
     */
    protected function hasEnoughUnitsAtHome(Dominion $thief, array $units): bool
    {
        foreach ($thief->race->units as $unit)
        {
            if (!isset($units[$unit->slot]) || ((int)$units[$unit->slot] === 0))
            {
                continue;
            }

            if ($units[$unit->slot] > $thief->{'military_unit' . $unit->slot})
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if an invasion passes the 4:3-rule.
     *
     * @param Dominion $thief
     * @param array $units
     * @return bool
     */
    protected function passes43RatioRule(Dominion $thief, Dominion $target, float $landRatio, array $units): bool
    {
        $unitsHome = [
            0 => $thief->military_draftees,
            1 => $thief->military_unit1 - (isset($units[1]) ? $units[1] : 0),
            2 => $thief->military_unit2 - (isset($units[2]) ? $units[2] : 0),
            3 => $thief->military_unit3 - (isset($units[3]) ? $units[3] : 0),
            4 => $thief->military_unit4 - (isset($units[4]) ? $units[4] : 0)
        ];
        $attackingForceOP = $this->militaryCalculator->getOffensivePower($thief, $target, $landRatio, $units);
        $newHomeForcesDP = $this->militaryCalculator->getDefensivePower($thief, null, null, $unitsHome, 0, false, false, false, null, null, true); # The "true" at the end excludes raw DP from annexed dominions

        $attackingForceMaxOP = (int)ceil($newHomeForcesDP * (4/3));

        return ($attackingForceOP <= $attackingForceMaxOP);
    }

    /**
     * Returns the amount of hours a military unit (with a specific slot) takes
     * to return home after battle.
     *
     * @param Dominion $thief
     * @param int $slot
     * @return int
     */
    protected function getUnitReturnHoursForSlot(Dominion $thief, int $slot): int
    {
        $ticks = 12;

        $unit = $thief->race->units->filter(function ($unit) use ($slot) {
            return ($unit->slot === $slot);
        })->first();

        if ($unit->getPerkValue('faster_return'))
        {
            $ticks -= (int)$unit->getPerkValue('faster_return');
        }

        return $ticks;
    }

    protected function getUnitReturnTicksForSlot(Dominion $thief, int $slot): int
    {
        $ticks = 12;

        $unit = $thief->race->units->filter(function ($unit) use ($slot) {
            return ($unit->slot === $slot);
        })->first();

        $ticks -= (int)$unit->getPerkValue('faster_return');
        $ticks -= (int)$thief->getSpellPerkValue('faster_return');
        $ticks -= (int)$thief->getTechPerkValue('faster_return');

        return min(max(1, $ticks), 12);
    }

    /**
     * Gets the amount of hours for the slowest unit from an array of units
     * takes to return home.
     *
     * Primarily used to bring prestige home earlier if you send only 9hr
     * attackers. (Land always takes 12 hrs)
     *
     * @param Dominion $thief
     * @param array $units
     * @return int
     */
    protected function getSlowestUnitReturnHours(Dominion $thief, array $units): int
    {
        $hours = 12;

        foreach ($units as $slot => $amount) {
            if ($amount === 0) {
                continue;
            }

            $hoursForUnit = $this->getUnitReturnHoursForSlot($thief, $slot);

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
        $dpMultiplierReduction += $attacker->getSpellPerkMultiplier('target_defensive_power_mod');
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
