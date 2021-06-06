<?php

namespace OpenDominion\Services\Dominion\Actions;

use DB;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Models\Dominion;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Services\Dominion\StatsService;
use OpenDominion\Traits\DominionGuardsTrait;
use OpenDominion\Helpers\ImprovementHelper;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;

class ImproveActionService
{
    use DominionGuardsTrait;
    /** @var ImprovementCalculator */
    protected $improvementCalculator;

    /** @var SpellCalculator */
    protected $spellCalculator;

    public function __construct()
    {
        $this->improvementCalculator = app(ImprovementCalculator::class);
        $this->improvementHelper = app(ImprovementHelper::class);
        $this->landCalculator = app(LandCalculator::class);
        $this->statsService = app(StatsService::class);
    }

    public function improve(Dominion $dominion, string $resource, array $data): array
    {
        $this->guardLockedDominion($dominion);

        // Qur: Statis
        if($dominion->getSpellPerkValue('stasis'))
        {
            throw new GameException('You cannot invest in improvements while you are in stasis.');
        }

        $data = array_map('\intval', $data);

        $totalResourcesToInvest = array_sum($data);

        $worth = $this->improvementCalculator->getResourceWorth($resource, $dominion);

        if ($totalResourcesToInvest < 0)
        {
            throw new GameException('Investment aborted due to bad input.');
        }

        if (!\in_array($resource, ['gold','lumber','ore', 'gems','mana','food','soul'], true))
        {
            throw new GameException('Investment aborted due to bad resource type.');
        }

        if($resource == 'mana' and !$dominion->race->getPerkValue('can_invest_mana'))
        {
            throw new GameException('You cannot use mana for improvements.');
        }

        if($resource == 'food' and (!$dominion->race->getPerkValue('tissue_improvement') and !$dominion->race->getPerkValue('can_invest_food')))
        {
            throw new GameException('You cannot use food for improvements.');
        }

        if ($dominion->race->getPerkValue('cannot_improve_castle'))
        {
            throw new GameException('Your faction is unable to use improvements.');
        }

        if ($totalResourcesToInvest > $dominion->{'resource_' . $resource})
        {
            throw new GameException("You do not have enough {$resource} to invest.");
        }

        foreach ($data as $improvementType => $amount)
        {
            if ($amount === 0)
            {
                continue;
            }

            if ($amount < 0)
            {
                throw new GameException('Investment aborted due to bad input.');
            }

            $points = $amount * $worth;

        }


        if($dominion->race->getPerkValue('tissue_improvement'))
        {
            $dominion->fill([
              'improvement_tissue' => ($dominion->improvement_tissue + ($data['tissue'] * $worth)),
            ])->save(['event' => HistoryService::EVENT_ACTION_IMPROVE]);
        }
        else
        {
            $dominion->fill([
              'improvement_markets' => ($dominion->improvement_markets + ($data['markets'] * $worth)),
              'improvement_keep' => ($dominion->improvement_keep + ($data['keep'] * $worth)),
              #'improvement_towers' => ($dominion->improvement_towers + ($data['towers'] * $worth)),
              'improvement_spires' => ($dominion->improvement_spires + ($data['spires'] * $worth)),
              'improvement_forges' => ($dominion->improvement_forges + ($data['forges'] * $worth)),
              'improvement_walls' => ($dominion->improvement_walls + ($data['walls'] * $worth)),
              'improvement_harbor' => ($dominion->improvement_harbor + ($data['harbor'] * $worth)),
              'improvement_armory' => ($dominion->improvement_armory + ($data['armory'] * $worth)),
              'improvement_infirmary' => ($dominion->improvement_infirmary + ($data['infirmary'] * $worth)),
              'improvement_workshops' => ($dominion->improvement_workshops + ($data['workshops'] * $worth)),
              'improvement_observatory' => ($dominion->improvement_observatory + ($data['observatory'] * $worth)),
              'improvement_cartography' => ($dominion->improvement_cartography + ($data['cartography'] * $worth)),
              'improvement_hideouts' => ($dominion->improvement_hideouts + ($data['hideouts'] * $worth)),
              'improvement_forestry' => ($dominion->improvement_forestry + ($data['forestry'] * $worth)),
              'improvement_refinery' => ($dominion->improvement_refinery + ($data['refinery'] * $worth)),
              'improvement_granaries' => ($dominion->improvement_granaries + ($data['granaries'] * $worth)),
            ])->save(['event' => HistoryService::EVENT_ACTION_IMPROVE]);
        }

        $dominion->{'resource_' . $resource} -= $totalResourcesToInvest;
        $dominion->most_recent_improvement_resource = $resource;

        $this->statsService->updateStat($dominion, ($resource . '_improvements'), $totalResourcesToInvest);

        $dominion->save(['event' => HistoryService::EVENT_ACTION_IMPROVE]);

        return [
            'message' => $this->getReturnMessageString($resource, $data, $totalResourcesToInvest, $dominion),
            'data' => [
                'totalResourcesInvested' => $totalResourcesToInvest,
                'resourceInvested' => $resource,
            ],
        ];
    }

    /**
     * Returns the message for a improve action.
     *
     * @param string $resource
     * @param array $data
     * @param int $totalResourcesToInvest
     * @return string
     */
    protected function getReturnMessageString(string $resource, array $data, int $totalResourcesToInvest, Dominion $dominion): string
    {
        $worth = $this->improvementCalculator->getResourceWorth($resource, $dominion);

        $investmentStringParts = [];

        foreach ($data as $improvementType => $amount) {
            if ($amount === 0) {
                continue;
            }

            #$points = ($amount * $worth);
            $investmentStringParts[] = (number_format($amount) . ' ' . $improvementType);
        }

        $investmentString = generate_sentence_from_array($investmentStringParts);

        return sprintf(
            'You invest %s %s into %s.',
            number_format($totalResourcesToInvest),
            ($resource === 'gems') ? str_plural('gem', $totalResourcesToInvest) : $resource,
            $investmentString
        );
    }

    # IMPS 2.0



    public function improve2(Dominion $dominion, string $resource, array $data): array
    {
        $this->guardLockedDominion($dominion);

        // Qur: Statis
        if($dominion->getSpellPerkValue('stasis'))
        {
            throw new GameException('You cannot invest in improvements while you are in stasis.');
        }

        $data = array_map('\intval', $data);

        $totalResourcesToInvest = array_sum($data);

        $worth = $this->improvementCalculator->getResourceWorth($resource, $dominion);

        if ($totalResourcesToInvest < 0)
        {
            throw new GameException('Investment aborted due to bad input.');
        }

        if (!\in_array($resource, ['gold', 'lumber', 'ore', 'gems', 'mana', 'food', 'soul'], true))
        {
            throw new GameException('Investment aborted due to bad resource type.');
        }

        if(
              ($resource == 'mana' and !$dominion->race->getPerkValue('can_invest_mana')) or
              ($resource == 'food' and !$dominion->race->getPerkValue('can_invest_food'))
          )
        {
            throw new GameException('You cannot use ' . $resource .  ' for improvements.');
        }

        if ($dominion->race->getPerkValue('cannot_improve_castle'))
        {
            throw new GameException($dominion->race->name . ' cannot use improvements.');
        }

        if ($totalResourcesToInvest > $dominion->{'resource_' . $resource})
        {
            throw new GameException("You do not have enough {$resource} to invest this much. You have " . number_format($dominion->{'resource_' . $resource}) . ' ' . $resource . ' and tried to invest ' . number_format($totalResourcesToInvest) . '.');
        }

        foreach ($data as $improvementKey => $amount)
        {
            if ($amount === 0)
            {
                continue;
            }

            if ($amount < 0)
            {
                throw new GameException('Investment aborted due to bad input.');
            }

            if(!$this->improvementHelper->getImprovementsByRace($dominion->race)->where('key', $improvementKey))
            {
                throw new GameException('Improvement key ' . $improvementKey .  ' not available to ' . $dominion->race->name . '.');
            }

            $points = $amount * $worth;
            $data[$improvementKey] = $points;

        }

        $this->improvementCalculator->createOrIncrementImprovements($dominion, $data);

        $dominion->{'resource_' . $resource} -= $totalResourcesToInvest;
        $dominion->most_recent_improvement_resource = $resource;

        $resourceNameForStats = $resource;

        $this->statsService->updateStat($dominion, ($resource . '_improvements'), $totalResourcesToInvest);

        $dominion->save(['event' => HistoryService::EVENT_ACTION_IMPROVE]);

        return [
            'message' => $this->getReturnMessageString($resource, $data, $totalResourcesToInvest, $dominion),
            'data' => [
                'totalResourcesInvested' => $totalResourcesToInvest,
                'resourceInvested' => $resource,
            ],
        ];
    }

}
