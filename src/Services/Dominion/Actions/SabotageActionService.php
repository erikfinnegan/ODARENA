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
use OpenDominion\Models\Spyop;
use OpenDominion\Models\Unit;

use OpenDominion\Helpers\UnitHelper;

use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Calculators\Dominion\SabotageCalculator;

use OpenDominion\Services\NotificationService;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Services\Dominion\ProtectionService;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Services\Dominion\ResourceService;
use OpenDominion\Services\Dominion\StatsService;

class SabotageActionService
{

    public function __construct()
    {
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->rangeCalculator = app(RangeCalculator::class);
        $this->resourceCalculator = app(ResourceCalculator::class);
        $this->sabotageCalculator = app(SabotageCalculator::class);

        $this->notificationService = app(NotificationService::class);
        $this->protectionService = app(ProtectionService::class);
        $this->queueService = app(QueueService::class);
        $this->resourceService = app(ResourceService::class);
        $this->statsService = app(StatsService::class);


        $this->unitHelper = app(UnitHelper::class);
    }

    public function steal(Dominion $saboteur, Dominion $target, Resource $resource, array $units): array
    {

        DB::transaction(function () use ($saboteur, $target, $resource, $units)
        {
            // Sanitize input
            $units = array_map('intval', array_filter($units));
            $landRatio = $this->rangeCalculator->getDominionRange($saboteur, $target) / 100;

            // Checks
            if (array_sum($units) <= 0)
            {
                throw new GameException('You need to send at least some units.');
            }

            if ($this->protectionService->isUnderProtection($saboteur))
            {
                throw new GameException('You cannot steal while under protection.');
            }

            if ($this->protectionService->isUnderProtection($saboteur))
            {
                throw new GameException('You cannot steal from dominions which are under protection.');
            }

            if (!$this->rangeCalculator->isInRange($saboteur, $target))
            {
                throw new GameException('You cannot steal from dominions outside of your range.');
            }

            if ($saboteur->round->id !== $target->round->id)
            {
                throw new GameException('Nice try, but you cannot steal cross-round.');
            }

            if ($saboteur->realm->id === $target->realm->id and ($saboteur->round->mode == 'standard' or $saboteur->round->mode == 'standard-duration'))
            {
                throw new GameException('You cannot steal from other dominions in the same realm as you in standard rounds.');
            }

            if ($saboteur->id == $target->id)
            {
                throw new GameException('Nice try, but you steal from invade yourself.');
            }

            if ($resource->key == 'mana')
            {
                throw new GameException('You do not currently have the ability to steal ' . $resource->name . '.');
            }

            if (!in_array($resource->key, $saboteur->race->resources))
            {
                throw new GameException($resource->name . ' is not used by ' . $saboteur->race->name . ', so you cannot steal it.');
            }

            if (!in_array($resource->key, $target->race->resources))
            {
                throw new GameException($target->race->name . ' does not use ' . $resource->name . '.');
            }

            if (!$this->passes43RatioRule($saboteur, $target, $landRatio, $units))
            {
                throw new GameException('You are sending out too much OP, based on your new home DP (4:3 rule).');
            }


            if (!$this->hasEnoughUnitsAtHome($saboteur, $units))
            {
                throw new GameException('You don\'t have enough units at home to send this many units.');
            }

            if($saboteur->race->getPerkValue('no_' . $resource->key .'_sabotage'))
            {
                throw new GameException($saboteur->race->name . ' cannot steal ' . $resource->name . '.');
            }

            if($target->race->getPerkValue('no_' . $resource->key .'_sabotage'))
            {
                throw new GameException('Cannot steal ' . $resource->name . ' from ' . $target->race->name . '.');
            }

            foreach($units as $slot => $amount)
            {
                $unit = $saboteur->race->units->filter(function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                if($amount < 0)
                {
                    throw new GameException('Sabotage was canceled due to bad input.');
                }

                if($slot !== 'spies')
                {
                    if(!$this->unitHelper->isUnitOffensiveSpy($unit))
                    {
                        throw new GameException($unit->name . ' is not a spy unit and cannot be sent on sabotage missions.');
                    }

                    # OK, unit can be trained. Let's check for pairing limits.
                    if($this->unitHelper->unitHasCapacityLimit($saboteur, $slot) and !$this->unitHelper->checkUnitLimitForInvasion($saboteur, $slot, $amount))
                    {
                        throw new GameException('You can at most control ' . number_format($this->unitHelper->getUnitMaxCapacity($saboteur, $slot)) . ' ' . str_plural($unit->name) . '. To control more, you need to first have more of their superior unit.');
                    }

                    if(!$this->unitHelper->isUnitSendableByDominion($unit, $saboteur))
                    {
                        throw new GameException('You cannot send ' . $unit->name . ' on invasion.');
                    }
                }
             }

            if ($saboteur->race->getPerkValue('cannot_steal'))
            {
                throw new GameException($saboteur->race->name . ' cannot steal.');
            }

            // Spell: Rainy Season (cannot invade)
            if ($saboteur->getSpellPerkValue('cannot_steal'))
            {
                throw new GameException('A spell is preventing from you steal.');
            }

            // Cannot invade until round has started.
            if(!$saboteur->round->hasStarted())
            {
                throw new GameException('You cannot steal until the round has started.');
            }

            // Cannot invade after round has ended.
            if($saboteur->round->hasEnded())
            {
                throw new GameException('You cannot steal after the round has ended.');
            }

            // Qur: Statis cannot be invaded.
            if($target->getSpellPerkValue('stasis'))
            {
                throw new GameException('A magical stasis surrounds the Qurrian lands, making it impossible for your spies to steal.');
            }

            // Qur: Statis cannot invade.
            if($saboteur->getSpellPerkValue('stasis'))
            {
                throw new GameException('You cannot steal while you are in stasis.');
            }

            // Check that saboteur has enough SS
            if($saboteur->spy_strength <= 0)
            {
                throw new GameException('You do not have enough spy strength to steal.');
            }

            $spyStrengthCost = $this->sabotageCalculator->getSpyStrengthCost($saboteur, $units);

            if($spyStrengthCost > $saboteur->spy_strength)
            {
                throw new GameException('You do not have enough spy strength to send that many units. You have ' . $saboteur->spy_strength . '% and would need ' . ($this->sabotageCalculator->getSpyStrengthCost($saboteur, $units)) . '% to send that many units.');
            }

            # CHECKS COMPLETE

            # Calculate spy units
            $saboteur->spy_strength -= min($spyStrengthCost, $saboteur->spy_strength);

            $this->sabotage['units'] = $units;
            $this->sabotage['spy_units_sent_ratio'] = $spyStrengthCost;
            $this->sabotage['resource']['key'] = $resource->key;
            $this->sabotage['resource']['name'] = $resource->name;

            # Casualties
            $survivingUnits = $units;
            $killedUnits = $this->sabotageCalculator->getUnitsKilled($saboteur, $target, $units);

            foreach($killedUnits as $slot => $amountKilled)
            {
                $survivingUnits[$slot] -= $amountKilled;
            }

            $this->sabotage['killed_units'] = $killedUnits;
            $this->sabotage['returning_units'] = $survivingUnits;

            # Determine how much was stolen
            $this->sabotage['amount_owned'] = $this->resourceCalculator->getAmount($target, $resource->key);
            $amountStolen = $this->sabotageCalculator->getSabotageAmount($saboteur, $target, $resource, $survivingUnits);
            $this->sabotage['amount_stolen'] = $amountStolen;

            # Remove from target
            $this->resourceService->updateResources($target, [$resource->key => $amountStolen*-1]);

            # Queue returning resources
            $ticks = 6;

            $resourceQueueKey = 'resource_' . $resource->key;

            $this->queueService->queueResources(
                'sabotage',
                $saboteur,
                [$resourceQueueKey => $amountStolen],
                $ticks
            );

            # Remove units
            foreach($units as $slot => $amount)
            {
                if($slot == 'spies')
                {
                    $saboteur->military_spies -= $amount;
                }
                else
                {
                    $saboteur->{'military_unit' . $slot} -= $amount;
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
                    'sabotage',
                    $saboteur,
                    [$unitType => $amount],
                    $ticks
                );
            }

            $this->sabotageEvent = GameEvent::create([
                'round_id' => $saboteur->round_id,
                'source_type' => Dominion::class,
                'source_id' => $saboteur->id,
                'target_type' => Dominion::class,
                'target_id' => $target->id,
                'type' => 'sabotage',
                'data' => $this->sabotage,
                'tick' => $saboteur->round->ticks
            ]);

            $this->notificationService->queueNotification('sabotage', [
                '_routeParams' => [(string)$this->sabotageEvent->id],
                'saboteurDominionId' => $saboteur->id,
                'unitsKilled' => $this->sabotage['killed_units'],
                'resource' => $resource->id,
                'amountLost' => $this->sabotage['amount_stolen']
            ]);

            $this->statsService->updateStat($saboteur, ($resource->key .  '_stolen'), $amountStolen);
            $this->statsService->updateStat($target, ($resource->key . '_lost'), $amountStolen);

            $saboteur->most_recent_sabotage_resource = $resource->key;

            # Debug before saving:
            if(request()->getHost() === 'odarena.local')
            {
                dd($this->sabotage);
            }

            $target->save(['event' => HistoryService::EVENT_ACTION_THEFT]);
            $saboteur->save(['event' => HistoryService::EVENT_ACTION_THEFT]);

        });

        $this->notificationService->sendNotifications($target, 'irregular_dominion');

        $saboteur->most_recent_sabotage_resource = $resource->key;

        $message = sprintf(
            'Your %s infiltrate %s (#%s), stealing %s %s.',
            (isset($units['spies']) and array_sum($units) < $units['spies']) ? 'spies' : 'units',
            $target->name,
            $target->realm->number,
            number_format($this->sabotage['amount_stolen']),
            $this->sabotage['resource']['name']
        );

        $alertType = 'success';

        return [
            'message' => $message,
            'alert-type' => $alertType,
            'redirect' => route('dominion.event', [$this->sabotageEvent->id])
        ];
    }

    /**
     * Check if dominion has enough units at home to send out.
     *
     * @param Dominion $saboteur
     * @param array $units
     * @return bool
     */
    protected function hasEnoughUnitsAtHome(Dominion $saboteur, array $units): bool
    {
        foreach ($saboteur->race->units as $unit)
        {
            if (!isset($units[$unit->slot]) || ((int)$units[$unit->slot] === 0))
            {
                continue;
            }

            if ($units[$unit->slot] > $saboteur->{'military_unit' . $unit->slot})
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if an invasion passes the 4:3-rule.
     *
     * @param Dominion $saboteur
     * @param array $units
     * @return bool
     */
    protected function passes43RatioRule(Dominion $saboteur, Dominion $target, float $landRatio, array $units): bool
    {
        $unitsHome = [
            0 => $saboteur->military_draftees,
            1 => $saboteur->military_unit1 - (isset($units[1]) ? $units[1] : 0),
            2 => $saboteur->military_unit2 - (isset($units[2]) ? $units[2] : 0),
            3 => $saboteur->military_unit3 - (isset($units[3]) ? $units[3] : 0),
            4 => $saboteur->military_unit4 - (isset($units[4]) ? $units[4] : 0)
        ];
        $attackingForceOP = $this->militaryCalculator->getOffensivePower($saboteur, $target, $landRatio, $units);
        $newHomeForcesDP = $this->militaryCalculator->getDefensivePower($saboteur, null, null, $unitsHome, 0, false, false, false, null, true); # The "true" at the end excludes raw DP from annexed dominions

        $attackingForceMaxOP = (int)ceil($newHomeForcesDP * (4/3));

        return ($attackingForceOP <= $attackingForceMaxOP);
    }

    /**
     * Returns the amount of hours a military unit (with a specific slot) takes
     * to return home after battle.
     *
     * @param Dominion $saboteur
     * @param int $slot
     * @return int
     */
    protected function getUnitReturnHoursForSlot(Dominion $saboteur, int $slot): int
    {
        $ticks = 12;

        $unit = $saboteur->race->units->filter(function ($unit) use ($slot) {
            return ($unit->slot === $slot);
        })->first();

        if ($unit->getPerkValue('faster_return'))
        {
            $ticks -= (int)$unit->getPerkValue('faster_return');
        }

        return $ticks;
    }

    protected function getUnitReturnTicksForSlot(Dominion $saboteur, int $slot): int
    {
        $ticks = 12;

        $unit = $saboteur->race->units->filter(function ($unit) use ($slot) {
            return ($unit->slot === $slot);
        })->first();

        $ticks -= (int)$unit->getPerkValue('faster_return');
        $ticks -= (int)$saboteur->getSpellPerkValue('faster_return');
        $ticks -= (int)$saboteur->getTechPerkValue('faster_return');
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
     * @param Dominion $saboteur
     * @param array $units
     * @return int
     */
    protected function getSlowestUnitReturnHours(Dominion $saboteur, array $units): int
    {
        $hours = 12;

        foreach ($units as $slot => $amount) {
            if ($amount === 0) {
                continue;
            }

            $hoursForUnit = $this->getUnitReturnHoursForSlot($saboteur, $slot);

            if ($hoursForUnit < $hours) {
                $hours = $hoursForUnit;
            }
        }

        return $hours;
    }
}
