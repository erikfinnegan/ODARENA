<?php

namespace OpenDominion\Services\Dominion\Actions;

use DB;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Improvement;
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

        if ($totalResourcesToInvest <= 0)
        {
            throw new GameException('Investment aborted due to bad input.');
        }

        if (!\in_array($resource, ['gold', 'lumber', 'ore', 'gems', 'mana', 'food', 'soul'], true))
        {
            throw new GameException('Investment aborted due to bad resource type.');
        }

        if(
              ($resource == 'mana' and !$dominion->race->getPerkValue('can_invest_mana')) or
              ($resource == 'soul' and !$dominion->race->getPerkValue('can_invest_soul')) or
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
            throw new GameException("You do not have enough {$resource}. You have " . number_format($dominion->{'resource_' . $resource}) . ' ' . $resource . ' and tried to invest ' . number_format($totalResourcesToInvest) . '.');
        }

        #dd($data);

        foreach ($data as $improvementKey => $amount)
        {

            $improvement = Improvement::where('key', $improvementKey)->first();

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
                throw new GameException('Improvement key ' . $improvement->name .  ' not available to ' . $dominion->race->name . '.');
            }

            $points = $amount * $worth;
            $data[$improvement->key] = $points;
            $returnData[$improvement->name] = $points;

        }

        $this->improvementCalculator->createOrIncrementImprovements($dominion, $data);

        $dominion->{'resource_' . $resource} -= $totalResourcesToInvest;
        $dominion->most_recent_improvement_resource = $resource;

        $resourceNameForStats = $resource;

        $this->statsService->updateStat($dominion, ($resource . '_improvements'), $totalResourcesToInvest);

        $dominion->save(['event' => HistoryService::EVENT_ACTION_IMPROVE]);

        return [
            'message' => $this->getReturnMessageString($resource, $returnData, $totalResourcesToInvest, $dominion),
            'data' => [
                'totalResourcesInvested' => $totalResourcesToInvest,
                'resourceInvested' => $resource,
            ],
        ];
    }

}
