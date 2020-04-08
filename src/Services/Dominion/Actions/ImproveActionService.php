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

        if ($totalResourcesToInvest < 0) {
            throw new GameException('Investment aborted due to bad input.');
        }

        if (!\in_array($resource, ['platinum','lumber','ore', 'gems','mana','food','soul'], true)) {
            throw new GameException('Investment aborted due to bad resource type.');
        }

        if ($dominion->race->getPerkValue('cannot_improve_castle') == 1)
        {
            throw new GameException('Your faction is unable to use castle improvements.');
        }

        if ($totalResourcesToInvest > $dominion->{'resource_' . $resource}) {
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

            $points = $amount * $worth;

            $dominion->{'improvement_' . $improvementType} += $points;
        }

        $dominion->{'resource_' . $resource} -= $totalResourcesToInvest;
        $dominion->most_recent_improvement_resource = (string)$resource;

        $resourceForStats = $resource;
        if($resourceForStats == 'gems')
        {
          $resourceForStats = 'gem';
        }
        $dominion->{'stat_total_' . $resourceForStats . '_spent_improving'} += $totalResourcesToInvest;

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
        #$worth = $this->getImprovementWorth($dominion);
        $worth = $this->getResourceWorth->($resource, $dominion);

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

    /**
     * Returns the amount of points per resource type invested.
     *
     * @return array
     */
    public function getImprovementWorth(Dominion $dominion = NULL): array
    {

        $worth = [
            'platinum' => 1,
            'lumber' => 2,
            'ore' => 2,
            'mana' => 5,
            'gems' => 12,
            'food' => 1,
            'soul' => 6,
        ];

        if(isset($dominion) and $dominion->race->getPerkValue('ore_improvement_points'))
        {
          $worth['ore'] *= (1 + $dominion->race->getPerkValue('ore_improvement_points') / 100);
        }

        if(isset($dominion) and $dominion->race->getPerkValue('lumber_improvement_points'))
        {
          $worth['lumber'] *= (1 + $dominion->race->getPerkValue('lumber_improvement_points') / 100);
        }

        if(isset($dominion) and $dominion->getTechPerkMultiplier('gemcutting'))
        {
          $worth['gems'] *= (1 + $dominion->getTechPerkMultiplier('gemcutting'));
        }

        return $worth;
    }
}
