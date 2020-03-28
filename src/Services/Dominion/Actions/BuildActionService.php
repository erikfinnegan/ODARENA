<?php

namespace OpenDominion\Services\Dominion\Actions;

use DB;
use OpenDominion\Calculators\Dominion\Actions\ConstructionCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Helpers\BuildingHelper;
use OpenDominion\Helpers\LandHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Traits\DominionGuardsTrait;

class BuildActionService
{
    use DominionGuardsTrait;

    /** @var BuildingHelper */
    protected $buildingHelper;

    /** @var ConstructionCalculator */
    protected $constructionCalculator;

    /** @var LandCalculator */
    protected $landCalculator;

    /** @var LandHelper */
    protected $landHelper;

    /** @var QueueService */
    protected $queueService;

    /**
     * ConstructionActionService constructor.
     */
    public function __construct()
    {
        $this->buildingHelper = app(BuildingHelper::class);
        $this->constructionCalculator = app(ConstructionCalculator::class);
        $this->landCalculator = app(LandCalculator::class);
        $this->landHelper = app(LandHelper::class);
        $this->queueService = app(QueueService::class);
    }

    /**
     * Does a construction action for a Dominion.
     *
     * @param Dominion $dominion
     * @param array $data
     * @return array
     * @throws GameException
     */
    public function construct(Dominion $dominion, array $data): array
    {
        $this->guardLockedDominion($dominion);

        $data = array_only($data, array_map(function ($value) {
            return "building_{$value}";
        }, $this->buildingHelper->getBuildingTypes($dominion)));

        $data = array_map('\intval', $data);

        $totalBuildingsToConstruct = array_sum($data);

        if ($totalBuildingsToConstruct <= 0)
        {
            throw new GameException('Construction was not started due to bad input.');
        }


        if ($dominion->race->getPerkValue('cannot_build') == 1)
        {
            throw new GameException('Your faction is unable to construct buildings.');
        }

        $maxAfford = $this->constructionCalculator->getMaxAfford($dominion);

        if ($totalBuildingsToConstruct > $maxAfford) {
            throw new GameException("You do not have enough platinum and/or lumber to construct {$totalBuildingsToConstruct} buildings.");
        }

        $buildingsByLandType = [];

        foreach ($data as $buildingType => $amount) {
            if ($amount === 0) {
                continue;
            }

            if ($amount < 0) {
                throw new GameException('Construction was not completed due to bad input.');
            }

            $landType = $this->landHelper->getLandTypeForBuildingByRace(
                str_replace('building_', '', $buildingType),
                $dominion->race
            );

            if (!isset($buildingsByLandType[$landType])) {
                $buildingsByLandType[$landType] = 0;
            }

            $buildingsByLandType[$landType] += $amount;
        }

        foreach ($buildingsByLandType as $landType => $amount) {
            if ($amount > $this->landCalculator->getTotalBarrenLandByLandType($dominion, $landType))
            {
                throw new GameException("You do not have enough barren land to construct {$totalBuildingsToConstruct} buildings.");
            }
        }


        $platinumCost = $this->constructionCalculator->getTotalPlatinumCost($dominion, $totalBuildingsToConstruct);
        $lumberCost = $this->constructionCalculator->getTotalLumberCost($dominion, $totalBuildingsToConstruct);
        $manaCost = $this->constructionCalculator->getTotalManaCost($dominion, $totalBuildingsToConstruct);
        $foodCost = $this->constructionCalculator->getTotalFoodCost($dominion, $totalBuildingsToConstruct);

        DB::transaction(function () use ($dominion, $data, $platinumCost, $lumberCost, $manaCost, $foodCost, $totalBuildingsToConstruct) {
            $hours = 12;
            # Gnome: increased construction speed
            if($dominion->race->getPerkValue('increased_construction_speed'))
            {
              $hours = 12 - $dominion->race->getPerkValue('increased_construction_speed');
            }

            $this->queueService->queueResources('construction', $dominion, $data, $hours);

            $dominion->fill([
                'resource_platinum' => ($dominion->resource_platinum - $platinumCost),
                'resource_lumber' => ($dominion->resource_lumber - $lumberCost),
                'resource_mana' => ($dominion->resource_mana - $manaCost),
                'resource_food' => ($dominion->resource_food - $foodCost),
                'discounted_land' => max(0, $dominion->discounted_land - $totalBuildingsToConstruct),
            ])->save(['event' => HistoryService::EVENT_ACTION_CONSTRUCT]);
        });

        if($platinumCost > 0 and $lumberCost > 0)
        {
          $return = [
              'message' => sprintf(
                  'Construction started at a cost of %s platinum and %s lumber.',
                  number_format($platinumCost),
                  number_format($lumberCost)
              ),
              'data' => [
                  'platinumCost' => $platinumCost,
                  'lumberCost' => $lumberCost,
              ],
          ];
        }
        elseif($platinumCost > 0)
        {
          $return = [
              'message' => sprintf(
                  'Construction started at a cost of %s platinum.',
                  number_format($platinumCost)
              ),
              'data' => [
                  'platinumCost' => $platinumCost
              ],
          ];
        }
        elseif($manaCost > 0)
        {
          $return = [
              'message' => sprintf(
                  'Conjuring of buildings started at a cost of %s mana.',
                  number_format($manaCost)
              ),
              'data' => [
                  'platinumCost' => $manaCost
              ],
          ];
        }
        elseif($foodCost > 0)
        {
          $return = [
              'message' => sprintf(
                  'Growth of building started at a cost of %s food.',
                  number_format($foodCost)
              ),
              'data' => [
                  'platinumCost' => $foodCost
              ],
          ];
        }
        return $return;
    }
}
