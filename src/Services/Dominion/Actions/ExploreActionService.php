<?php

namespace OpenDominion\Services\Dominion\Actions;

use DB;
use OpenDominion\Calculators\Dominion\Actions\ExplorationCalculator;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Helpers\LandHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Traits\DominionGuardsTrait;

# ODA
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;
use OpenDominion\Services\Dominion\ProtectionService;

class ExploreActionService
{

    use DominionGuardsTrait;

    /** @var ExplorationCalculator */
    protected $explorationCalculator;

    /** @var LandHelper */
    protected $landHelper;

    /** @var QueueService */
    protected $queueService;

    /** @var ImprovementCalculator */
    protected $improvementCalculator;

    /** @var LandCalculator */
    protected $landCalculator;

    /** @var SpellCalculator */
    protected $spellCalculator;

    /** @var ProtectionService */
    protected $protectionService;

    /**
     * @var int The minimum morale required to explore
     */
    protected const MIN_MORALE = 0;

    /**
     * ExplorationActionService constructor.
     */
    public function __construct(
          ImprovementCalculator $improvementCalculator,
          SpellCalculator $spellCalculator,
          LandCalculator $landCalculator,
          ProtectionService $protectionService
      )
    {
        $this->explorationCalculator = app(ExplorationCalculator::class);
        $this->landHelper = app(LandHelper::class);
        $this->queueService = app(QueueService::class);
        $this->spellCalculator = $spellCalculator;
        $this->improvementCalculator = $improvementCalculator;
        $this->landCalculator = $landCalculator;
        $this->protectionService = $protectionService;
    }

    /**
     * Does an explore action for a Dominion.
     *
     * @param Dominion $dominion
     * @param array $data
     * @return array
     * @throws GameException
     */
    public function explore(Dominion $dominion, array $data): array
    {
        $this->guardLockedDominion($dominion);


        if(!$dominion->round->isExploringAllowed())
        {
            throw new GameException('Exploration has been disabled for this round.');
        }

        #if ($this->protectionService->isUnderProtection($dominion))
        #{
        #    throw new GameException('You cannot explore while under protection.');
        #}

        if($dominion->round->hasOffensiveActionsDisabled())
        {
            throw new GameException('Exploration has been disabled for the remainder of the round.');
        }

        $data = array_only($data, array_map(function ($value) {
            return "land_{$value}";
        }, $this->landHelper->getLandTypes()));

        $data = array_map('\intval', $data);

        $totalLandToExplore = array_sum($data);

        if ($totalLandToExplore <= 0) {
            throw new GameException('Exploration was not begun due to bad input.');
        }

        foreach($data as $amount) {
            if ($amount < 0) {
                throw new GameException('Exploration was not completed due to bad input.');
            }
        }

        if ($dominion->race->getPerkValue('cannot_explore') == 1)
        {
            throw new GameException('Your faction is unable to explore.');
        }

        if ($totalLandToExplore > $this->explorationCalculator->getMaxAfford($dominion))
        {
            throw new GameException('You do not have enough platinum and/or draftees to explore for ' . number_format($totalLandToExplore) . ' acres.');
        }

        $maxAllowed = $this->landCalculator->getTotalLand($dominion) * 1.5;
        if($totalLandToExplore > $maxAllowed)
        {
            throw new GameException('You cannot explore more than ' . number_format($maxAllowed) . ' acres.');
        }

        # ODA
        // Spell: Rainy Season (cannot explore)
        if ($this->spellCalculator->isSpellActive($dominion, 'rainy_season'))
        {
            throw new GameException('You cannot explore during Rainy Season.');
        }

        if ($dominion->morale <= static::MIN_MORALE)
        {
            throw new GameException('You do not have enough morale to explore.');
        }

        $moraleDrop = $this->explorationCalculator->getMoraleDrop($dominion, $totalLandToExplore);
        if($moraleDrop > $dominion->morale)
        {
            throw new GameException('Exploring that much land would lower your morale by ' . $moraleDrop . '%. You currently have ' . $dominion->morale . '% morale.');
        }

        // todo: refactor. see training action service. same with other action services
        #$newMorale = max(0, ($dominion->morale - $this->explorationCalculator->getMoraleDrop($totalLandToExplore)));
        #$moraleDrop = ($dominion->morale - $newMorale);

        $newMorale = $dominion->morale - $moraleDrop;

        $platinumCost = ($this->explorationCalculator->getPlatinumCost($dominion) * $totalLandToExplore);
        $newPlatinum = ($dominion->resource_platinum - $platinumCost);

        $drafteeCost = ($this->explorationCalculator->getDrafteeCost($dominion) * $totalLandToExplore);
        $newDraftees = ($dominion->military_draftees - $drafteeCost);

        if($dominion->race->getPerkValue('cannot_tech'))
        {
          $researchPointsPerAcre = 0;
        }
        else
        {
          $researchPointsPerAcre = 10;
        }

        # Observatory
        $researchPointsPerAcreMultiplier = $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'observatory');
        $researchPointsPerAcre *= (1 + $researchPointsPerAcreMultiplier);
        $researchPointsGained = $researchPointsPerAcre * $totalLandToExplore;

        DB::transaction(function () use ($dominion, $data, $newMorale, $newPlatinum, $newDraftees, $totalLandToExplore, $researchPointsGained) {
            $this->queueService->queueResources('exploration', $dominion, $data);
            $this->queueService->queueResources('exploration',$dominion,['resource_tech' => $researchPointsGained]);


            $dominion->stat_total_land_explored += $totalLandToExplore;
            $dominion->fill([
                'morale' => $newMorale,
                'resource_platinum' => $newPlatinum,
                'military_draftees' => $newDraftees,
            ])->save(['event' => HistoryService::EVENT_ACTION_EXPLORE]);
        });

        return [
            'message' => sprintf(
                'Exploration begun at a cost of %s platinum and %s %s. When exploration is completed, you will earn %s experience points. Your orders for exploration disheartens the military, and morale drops by %d%%.',
                number_format($platinumCost),
                number_format($drafteeCost),
                str_plural('draftee', $drafteeCost),
                number_format($researchPointsGained),
                $moraleDrop
            ),
            'data' => [
                'platinumCost' => $platinumCost,
                'drafteeCost' => $drafteeCost,
                'moraleDrop' => $moraleDrop,
            ]
        ];
    }
}
