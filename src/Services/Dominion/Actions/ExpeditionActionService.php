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
use OpenDominion\Helpers\LandHelper;
use OpenDominion\Helpers\SpellHelper;
use OpenDominion\Helpers\RaceHelper;
use OpenDominion\Helpers\UnitHelper;


use OpenDominion\Calculators\Dominion\ExpeditionCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;

use OpenDominion\Services\NotificationService;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Services\Dominion\ProtectionService;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Services\Dominion\ResourceService;
use OpenDominion\Services\Dominion\StatsService;

class ExpeditionActionService
{
    /**
     * @var int The minimum morale required to initiate an invasion
     */
    protected const MIN_MORALE = 50;

    /** @var array Invasion result array. todo: Should probably be refactored later to its own class */
    protected $expeditionResult = [];

    public function __construct()
    {
        $this->landCalculator = app(LandCalculator::class);
        $this->landHelper = app(LandHelper::class);

        $this->expeditionCalculator = app(ExpeditionCalculator::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->notificationService = app(NotificationService::class);
        $this->protectionService = app(ProtectionService::class);
        $this->statsService = app(StatsService::class);
        $this->queueService = app(QueueService::class);
        $this->spellCalculator = app(SpellCalculator::class);
        $this->spellHelper = app(SpellHelper::class);
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
    public function send(Dominion $dominion, array $units): array
    {

        DB::transaction(function () use ($dominion, $units)
        {

            if ($this->protectionService->isUnderProtection($dominion)) {
                throw new GameException('You cannot send out an expedition while under protection');
            }

            // Sanitize input
            $units = array_map('intval', array_filter($units));

            if (!$this->hasAnyOP($dominion, $units))
            {
                throw new GameException('You need to send at least some units with offensive power.');
            }

            if (!$this->allUnitsHaveOP($dominion, $units))
            {
                throw new GameException('You cannot send units that have no offensive power.');
            }

            if (!$this->hasEnoughUnitsAtHome($dominion, $units))
            {
                throw new GameException('You don\'t have enough units at home to send this many units.');
            }

            if ($dominion->morale < static::MIN_MORALE)
            {
                throw new GameException('You do not have enough morale to send out units on an expedition.');
            }

            if (!$this->passes43RatioRule($dominion, $units))
            {
                throw new GameException('You are sending out too much OP, based on your new home DP (4:3 rule).');
            }

            foreach($units as $amount)
            {
                 if($amount < 0)
                 {
                     throw new GameException('Expedition was canceled due to bad input.');
                 }
             }

            if ($dominion->race->getPerkValue('cannot_send_expeditions') == 1)
            {
                throw new GameException($dominion->race->name . ' cannot send out expeditions.');
            }

            foreach($units as $amount)
            {
                if($amount < 0)
                {
                    throw new GameException('Expedition was cancelled due to bad input.');
                }
            }

            // Spell: Rainy Season (cannot invade)
            if ($this->spellCalculator->isSpellActive($dominion, 'rainy_season'))
            {
                throw new GameException('You cannot send out expeditions during the Rainy Season.');
            }

            if ($dominion->getSpellPerkValue('cannot_invade') or $dominion->getSpellPerkValue('cannot_send_expeditions'))
            {
                throw new GameException('A spell is preventing from you sending out expeditions.');
            }


            $disallowedUnitAttributes = [
                'ammunition',
                'immobile'
              ];
            // Check building_limit and unit attributes
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

                # Get the $unit
                $unit = $dominion->race->units->filter(function ($unit) use ($unitSlot) {
                        return ($unit->slot == $unitSlot);
                    })->first();

                # Get the unit attributes
                $unitAttributes = $this->unitHelper->getUnitAttributes($unit);

                if (count(array_intersect($disallowedUnitAttributes, $unitAttributes)) !== 0)
                {
                    throw new GameException('Ammunition and immobile units cannot be used for expeditions.');
                }

                # Disallow units with fixed casualties perk
                if ($fixedCasualtiesPerk = $dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'fixed_casualties'))
                {
                    throw new GameException('Units with fixed casualties cannot be sent on expeditions.');
                }
            }

            // Cannot invade until round has started.
            if(!$dominion->round->hasStarted())
            {
                throw new GameException('You cannot send out expeditions until the round has started.');
            }

            // Dimensionalists: must have Portals open.
            if($dominion->race->name == 'Dimensionalists' and !$dominion->getSpellPerkValue('opens_portal'))
            {
                throw new GameException('You cannot send out expeditions unless a portal is open.');
            }

            // Qur: Statis cannot invade.
            if($dominion->getSpellPerkValue('stasis'))
            {
                throw new GameException('You cannot send out expeditions while you are in stasis.');
            }

            $this->expeditionResult['units_sent'] = $units;
            $this->expeditionResult['land_size'] = $this->landCalculator->getTotalLand($dominion);

            $this->expeditionResult['op_sent'] = $this->militaryCalculator->getOffensivePower($dominion, null, null, $units);

            if($this->expeditionResult['op_sent'] < $this->expeditionCalculator->getOpPerLand($dominion))
            {
                throw new GameException('Expeditions must discover at least one acre. You sent ' . number_format($this->expeditionResult['op_sent']) . ' while the minimum required per acre is ' . number_format($this->expeditionCalculator->getOpPerLand($dominion)) . '.');
            }

            $this->expeditionResult['land_discovered_amount'] = $this->expeditionCalculator->getLandDiscoveredAmount($dominion, $this->expeditionResult['op_sent']);
            $this->expeditionResult['land_discovered'] = $this->expeditionCalculator->getLandDiscovered($dominion, $this->expeditionResult['land_discovered_amount']);

            $this->queueService->queueResources(
                'expedition',
                $dominion,
                $this->expeditionResult['land_discovered']
            );

            $this->handlePrestigeChanges($dominion, $this->expeditionResult['land_discovered_amount'], $this->expeditionResult['land_size']);
            $this->handleXp($dominion, $this->expeditionResult['land_discovered_amount']);
            $this->handleReturningUnits($dominion, $units);

            $this->statsService->updateStat($dominion, 'land_discovered', $this->expeditionResult['land_discovered_amount']);
            $this->statsService->updateStat($dominion, 'expeditions', 1);

            # Debug before saving:
            if(request()->getHost() === 'odarena.local')
            {
                dd($this->expeditionResult);
            }

            $this->invasionEvent = GameEvent::create([
                'round_id' => $dominion->round_id,
                'source_type' => Dominion::class,
                'source_id' => $dominion->id,
                'target_type' => NULL,
                'target_id' => NULL,
                'type' => 'expedition',
                'data' => $this->expeditionResult,
            ]);

            $dominion->save(['event' => HistoryService::EVENT_ACTION_EXPEDITION]);
        });

        $message = sprintf(
                'Your units are sent out on an expedition and discover %s acres of land!',
                number_format($this->expeditionResult['land_discovered_amount'])
            );
            $alertType = 'success';

        return [
            'message' => $message,
            'alert-type' => $alertType,
            'redirect' => route('dominion.event', [$this->invasionEvent->id])
        ];
    }

    protected function handlePrestigeChanges(Dominion $dominion, int $landDiscovered, int $landSize): void
    {
        $prestigeChange = intval($landDiscovered / $this->landCalculator->getTotalLand($dominion) * 200);

        $this->queueService->queueResources(
            'expedition',
            $dominion,
            ['prestige' => $prestigeChange],
            12
        );

        $this->expeditionResult['prestige_change'] = $prestigeChange;
    }

    /**
     * Handles land grabs and losses upon successful invasion.
     *
     * todo: description
     *
     * @param Dominion $dominion
     * @param Dominion $target
     */
    protected function handleLandGains(Dominion $dominion, int $landDiscovered): void
    {
        /*

        $this->queueService->queueResources(
            'invasion',
            $dominion,
            $queueData
        );
        */
    }

    /**
     * Handles experience point (research point) generation for attacker.
     *
     * @param Dominion $dominion
     * @param array $units
     */
    protected function handleXp(Dominion $dominion, int $landDiscovered): void
    {

        $xpPerAcreMultiplier = 1;
        $xpPerAcreMultiplier += $dominion->race->getPerkMultiplier('research_points_per_acre');
        $xpPerAcreMultiplier += $dominion->getImprovementPerkMultiplier('research_points_per_acre');

        $xpGained = intval(25 * $xpPerAcreMultiplier * $landDiscovered);

        $this->queueService->queueResources(
            'expedition',
            $dominion,
            ['xp' => $xpGained],
            12
        );

        $this->expeditionResult['xp'] = $xpGained;

    }

    # Unit Return 2.0
    protected function handleReturningUnits(Dominion $dominion, array $units): void
    {
        # If instant return
        if(random_chance($dominion->getImprovementPerkMultiplier('chance_of_instant_return')) or $dominion->race->getPerkValue('instant_return') or $dominion->getSpellPerkValue('instant_return'))
        {
            $this->expeditionResult['attacker']['instantReturn'] = true;
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
              #'peasants' => array_fill(1, 12, 0),
            ];

            $someWinIntoUnits = array_fill(1, 4, 0);
            $someWinIntoUnits = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

            foreach($returningUnits as $unitKey => $values)
            {
                $slot = str_replace('military_unit', '', $unitKey);
                $amountReturning = 0;

                $returningUnitKey = $unitKey;

                # Remove the units from attacker and add them to $amountReturning.
                if (array_key_exists($slot, $units))
                {
                    $dominion->{$unitKey} -= $units[$slot];
                    $amountReturning += $units[$slot];
                }

                # Default return time is 12 ticks.
                $ticks = $this->getUnitReturnTicksForSlot($dominion, $slot);

                # Default all returners to tick 12
                $returningUnits[$returningUnitKey][$ticks] += $amountReturning;

                # Look for dies_into and variations amongst the dead attacking units.
                if(isset($this->expeditionResult['units_lost'][$slot]))
                {
                    $casualties = $this->expeditionResult['attacker']['unitsLost'][$slot];

                    if($diesIntoPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into'))
                    {
                        # Which unit do they die into?
                        $newUnitSlot = $diesIntoPerk[0];
                        $newUnitKey = "military_unit{$newUnitSlot}";
                        $newUnitSlotReturnTime = $this->getUnitReturnTicksForSlot($dominion, $newUnitSlot);

                        $returningUnits[$newUnitKey][$newUnitSlotReturnTime] += $casualties;
                    }

                    if($diesIntoPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_on_offense'))
                    {
                        # Which unit do they die into?
                        $newUnitSlot = $diesIntoPerk[0];
                        $newUnitKey = "military_unit{$newUnitSlot}";
                        $newUnitSlotReturnTime = $this->getUnitReturnTicksForSlot($dominion, $newUnitSlot);

                        $returningUnits[$newUnitKey][$newUnitSlotReturnTime] += $casualties;
                    }

                    if($diesIntoMultiplePerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple'))
                    {
                        # Which unit do they die into?
                        $newUnitSlot = $diesIntoMultiplePerk[0];
                        $newUnitAmount = (float)$diesIntoMultiplePerk[1];
                        $newUnitKey = "military_unit{$newUnitSlot}";
                        $newUnitSlotReturnTime = $this->getUnitReturnTicksForSlot($dominion, $newUnitSlot);

                        $returningUnits[$newUnitKey][$newUnitSlotReturnTime] += floor($casualties * $newUnitAmount);
                    }

                    if($diesIntoMultiplePerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple_on_offense'))
                    {
                        # Which unit do they die into?
                        $newUnitSlot = $diesIntoMultiplePerk[0];
                        $newUnitAmount = (float)$diesIntoMultiplePerk[1];
                        $newUnitKey = "military_unit{$newUnitSlot}";
                        $newUnitSlotReturnTime = $this->getUnitReturnTicksForSlot($dominion, $newUnitSlot);

                        $returningUnits[$newUnitKey][$newUnitSlotReturnTime] += floor($casualties * $newUnitAmount);
                    }

                    if($this->expeditionResult['result']['success'] and $diesIntoMultiplePerkOnVictory = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple_on_victory'))
                    {
                        # Which unit do they die into?
                        $newUnitSlot = $diesIntoMultiplePerkOnVictory[0];
                        $newUnitAmount = (float)$diesIntoMultiplePerkOnVictory[1];
                        $newUnitKey = "military_unit{$newUnitSlot}";
                        $newUnitSlotReturnTime = $this->getUnitReturnTicksForSlot($dominion, $newUnitSlot);

                        $returningUnits[$newUnitKey][$newUnitSlotReturnTime] += floor($casualties * $newUnitAmount);
                    }

                    if(!$this->expeditionResult['result']['success'] and $diesIntoMultiplePerkOnVictory = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'dies_into_multiple_on_victory'))
                    {
                        # Which unit do they die into?
                        $newUnitSlot = $diesIntoMultiplePerkOnVictory[0];
                        $newUnitAmount = $diesIntoMultiplePerkOnVictory[2];
                        $newUnitKey = "military_unit{$newUnitSlot}";
                        $newUnitSlotReturnTime = $this->getUnitReturnTicksForSlot($dominion, $newUnitSlot);

                        $returningUnits[$newUnitKey][$newUnitSlotReturnTime] += floor($casualties * $newUnitAmount);
                    }
                }

                # Check for faster_return_if_paired
                foreach($units as $slot => $amount)
                {
                    if($fasterReturnIfPairedPerk = $dominion->race->getUnitPerkValueForUnitSlot($slot, 'faster_return_if_paired'))
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
                if($buildingFasterReturnPerk = $dominion->getBuildingPerkMultiplier('faster_return'))
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
                if($buildingFasterReturnPerk = $dominion->getBuildingPerkValue('faster_returning_units') or $buildingFasterReturnPerk = $dominion->getBuildingPerkValue('faster_returning_units_increasing'))
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
                            'expedition',
                            $dominion,
                            [$unitKey => $amount],
                            $unitTypeTick
                        );
                    }
                }
                $slot = str_replace('military_unit', '', $unitKey);
                $this->expeditionResult['units_returning'][$slot] = array_sum($unitKeyTicks);
            }

            $dominion->save();
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
    protected function allUnitsHaveOP(Dominion $dominion, array $units): bool
    {
        foreach ($dominion->race->units as $unit)
        {
            if (!isset($units[$unit->slot]) || ((int)$units[$unit->slot] === 0))
            {
                continue;
            }

            if ($this->militaryCalculator->getUnitPowerWithPerks($dominion, null, null, $unit, 'offense', null, $units, null) === 0.0 and $unit->getPerkValue('sendable_with_zero_op') != 1)
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
    protected function passes43RatioRule(Dominion $dominion, array $units): bool
    {
        $unitsHome = [
            0 => $dominion->military_draftees,
            1 => $dominion->military_unit1 - (isset($units[1]) ? $units[1] : 0),
            2 => $dominion->military_unit2 - (isset($units[2]) ? $units[2] : 0),
            3 => $dominion->military_unit3 - (isset($units[3]) ? $units[3] : 0),
            4 => $dominion->military_unit4 - (isset($units[4]) ? $units[4] : 0)
        ];
        $attackingForceOP = $this->militaryCalculator->getOffensivePower($dominion, null, null, $units);
        $newHomeForcesDP = $this->militaryCalculator->getDefensivePower($dominion, null, null, $unitsHome, 0, false, false, false, null, null, true); # The "true" at the end excludes raw DP from annexed dominions

        $attackingForceMaxOP = (int)ceil($newHomeForcesDP * (4/3));

        return ($attackingForceOP <= $attackingForceMaxOP);
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

}
