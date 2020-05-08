<?php

namespace OpenDominion\Services\Dominion\Actions;

use OpenDominion\Calculators\Dominion\Actions\RezoningCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Models\Dominion;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Traits\DominionGuardsTrait;

class RezoneActionService
{
    use DominionGuardsTrait;

    /** @var LandCalculator */
    protected $landCalculator;

    /** @var RezoningCalculator */
    protected $rezoningCalculator;

    /**
     * RezoneActionService constructor.
     *
     * @param LandCalculator $landCalculator
     * @param RezoningCalculator $rezoningCalculator
     */
    public function __construct(LandCalculator $landCalculator, RezoningCalculator $rezoningCalculator)
    {
        $this->landCalculator = $landCalculator;
        $this->rezoningCalculator = $rezoningCalculator;
    }

    /**
     * Does a rezone action for a Dominion.
     *
     * @param Dominion $dominion
     * @param array $remove Land to remove
     * @param array $add Land to add.
     * @return array
     * @throws GameException
     */
    public function rezone(Dominion $dominion, array $remove, array $add): array
    {
        $this->guardLockedDominion($dominion);

        if ($dominion->race->getPerkValue('cannot_rezone'))
        {
            throw new GameException('Your faction is unable to rezone land.');
        }

        // Level out rezoning going to the same type.
        foreach (array_intersect_key($remove, $add) as $key => $value) {
            $sub = min($value, $add[$key]);
            $remove[$key] -= $sub;
            $add[$key] -= $sub;
        }

        // Filter out empties.
        $remove = array_filter($remove);
        $add = array_filter($add);

        $totalLand = array_sum($remove);

        if (($totalLand <= 0) || $totalLand !== array_sum($add)) {
            throw new GameException('Re-zoning was not completed due to bad input.');
        }

        // Check if the requested amount of land is barren.
        foreach ($remove as $landType => $landToRemove) {

            if($landToRemove < 0) {
                throw new GameException('Re-zoning was not completed due to bad input.');
            }

            $landAvailable = $this->landCalculator->getTotalBarrenLandByLandType($dominion, $landType);
            if ($landToRemove > $landAvailable) {
                throw new GameException('You do not have enough barren land to re-zone ' . $landToRemove . ' ' . str_plural($landType, $landAvailable));
            }
        }

        $platinumCost = $totalLand * $this->rezoningCalculator->getPlatinumCost($dominion);
        $foodCost = $totalLand * $this->rezoningCalculator->getFoodCost($dominion);
        $manaCost = $totalLand * $this->rezoningCalculator->getManaCost($dominion);


        if ($platinumCost > 0 and $platinumCost > $dominion->resource_platinum)
        {
            throw new GameException("You do not have enough platinum to re-zone {$totalLand} acres of land.");
        }

        if ($foodCost > 0 and $foodCost > $dominion->resource_food)
        {
            throw new GameException("You do not have enough food to re-zone {$totalLand} acres of land.");
        }

        if ($manaCost > 0 and $platinumCost > $dominion->resource_mana)
        {
            throw new GameException("You do not have enough mana to re-zone {$totalLand} acres of land.");
        }


        // All fine, perform changes.
        $dominion->resource_platinum -= $platinumCost;
        $dominion->resource_food -= $foodCost;
        $dominion->resource_mana -= $manaCost;

        # Update spending statistics.
        $dominion->stat_total_platinum_spent_rezoning += $platinumCost;
        $dominion->stat_total_food_spent_rezoning += $foodCost;
        $dominion->stat_total_mana_spent_rezoning += $manaCost;


        $dominion->stat_total_lumber_spent_rezoning += 0;
        $dominion->stat_total_ore_spent_rezoning += 0;
        $dominion->stat_total_gem_spent_rezoning += 0;
        #$dominion->stat_total_unit1_spent_rezoning += 0;
        #$dominion->stat_total_unit2_spent_rezoning += 0;
        #$dominion->stat_total_unit3_spent_rezoning += 0;
        #$dominion->stat_total_unit4_spent_rezoning += 0;
        #$dominion->stat_total_spies_spent_rezoning += 0;
        #$dominion->stat_total_wizards_spent_rezoning += 0;
        #$dominion->stat_total_wizards_spent_rezoning += 0;
        #$dominion->stat_total_archmages_spent_rezoning += 0;
        #$dominion->stat_total_wild_yeti_spent_rezoning += 0;
        #$dominion->stat_total_soul_spent_rezoning += 0;
        #$dominion->stat_total_champion_spent_rezoning += 0;

        foreach ($remove as $landType => $amount) {
            $dominion->{'land_' . $landType} -= $amount;
        }
        foreach ($add as $landType => $amount) {
            $dominion->{'land_' . $landType} += $amount;
        }

        $dominion->save(['event' => HistoryService::EVENT_ACTION_REZONE]);

        if($manaCost > 0)
        {
          $resource = 'mana';
          $cost = $manaCost;
        }
        elseif($foodCost > 0)
        {
          $resource = 'food';
          $cost = $foodCost;
        }
        else
        {
          $resource = 'platinum';
          $cost = $platinumCost;
        }

        return [
            'message' => sprintf(
                'Your land has been re-zoned at a cost of %1$s %2$s.',
                number_format($cost),
                $resource
            ),
            'data' => [
                'platinumCost' => $platinumCost,
            ]
        ];
    }
}
