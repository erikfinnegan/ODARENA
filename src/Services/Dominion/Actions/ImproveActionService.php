<?php

namespace OpenDominion\Services\Dominion\Actions;

use OpenDominion\Exceptions\GameException;
use OpenDominion\Models\Dominion;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Traits\DominionGuardsTrait;

// ODA
use OpenDominion\Calculators\Dominion\ImprovementCalculator;

class ImproveActionService
{
    use DominionGuardsTrait;

    // ODA
    /** @var ImprovementCalculator */
    protected $improvementCalculator;

    public function __construct(
        ImprovementCalculator $improvementCalculator
    ) {
        $this->improvementCalculator = $improvementCalculator;
    }


    public function improve(Dominion $dominion, string $resource, array $data): array
    {
        $this->guardLockedDominion($dominion);

        $data = array_map('\intval', $data);

        $totalResourcesToInvest = array_sum($data);

        if ($totalResourcesToInvest < 0)
        {
            throw new GameException('Investment aborted due to bad input.');
        }

        if (!\in_array($resource, ['platinum','lumber','ore', 'gems','mana','food','soul'], true))
        {
            throw new GameException('Investment aborted due to bad resource type.');
        }

        if($resource == 'mana' and !$dominion->race->getPerkValue('can_invest_mana'))
        {
            throw new GameException('You cannot use mana for improvements.');
        }

        if($resource == 'food' and !$dominion->race->getPerkValue('tissue_improvement'))
        {
            throw new GameException('You cannot use food for improvements.');
        }

        if ($dominion->race->getPerkValue('cannot_improve_castle') == 1)
        {
            throw new GameException('Your faction is unable to use improvements.');
        }

        if ($totalResourcesToInvest > $dominion->{'resource_' . $resource})
        {
            throw new GameException("You do not have enough {$resource} to invest.");
        }

        $worth = $this->improvementCalculator->getResourceWorth($resource, $dominion);

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

            $points = $amount * $worth; # OK

            var_dump($dominion->{'improvement_' . $improvementType});
            $dominion->{'improvement_' . $improvementType} += $points;
            dd($dominion->{'improvement_' . $improvementType});
        }

        $dominion->{'resource_' . $resource} -= $totalResourcesToInvest;
        $dominion->most_recent_improvement_resource = $resource;

        $resourceNameForStats = $resource;
        if($resourceNameForStats == 'gems')
        {
          $resourceNameForStats = 'gem';
        }
        $dominion->{'stat_total_' . $resourceNameForStats . '_spent_improving'} += $totalResourcesToInvest;

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

            $points = ($amount * $worth);
            $investmentStringParts[] = (number_format($points) . ' ' . $improvementType);
        }

        $investmentString = generate_sentence_from_array($investmentStringParts);

        return sprintf(
            'You invest %s %s into %s.',
            number_format($totalResourcesToInvest),
            ($resource === 'gems') ? str_plural('gem', $totalResourcesToInvest) : $resource,
            $investmentString
        );
    }
}
