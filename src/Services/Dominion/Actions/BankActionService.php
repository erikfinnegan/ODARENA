<?php

namespace OpenDominion\Services\Dominion\Actions;

use LogicException;
use OpenDominion\Calculators\Dominion\Actions\BankingCalculator;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Resource;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Services\Dominion\ResourceService;
use OpenDominion\Services\Dominion\StatsService;
use OpenDominion\Traits\DominionGuardsTrait;

use OpenDominion\Calculators\Dominion\SpellCalculator;

class BankActionService
{
    use DominionGuardsTrait;

    /** @var BankingCalculator */
    protected $bankingCalculator;
    protected $spellCalculator;
    protected $statsService;

    /**
     * BankActionService constructor.
     *
     * @param BankingCalculator $bankingCalculator
     */
    public function __construct()
    {
        $this->bankingCalculator = app(BankingCalculator::class);
        $this->resourceService = app(ResourceService::class);
        $this->spellCalculator = app(SpellCalculator::class);
        $this->statsService = app(StatsService::class);
    }

    /**
     * Does a bank action for a Dominion.
     *
     * @param Dominion $dominion
     * @param string $source
     * @param string $target
     * @param int $amount
     * @return array
     * @throws LogicException
     * @throws GameException
     */
    public function exchange(Dominion $dominion, string $source, string $target, int $amount): array
    {
        $this->guardLockedDominion($dominion);

        // Qur: Statis
        if($dominion->getSpellPerkValue('stasis'))
        {
            throw new GameException('You cannot exchange resources while you are in stasis.');
        }

        // Perk: cannot_exchange
        if($dominion->race->getPerkValue('cannot_exchange'))
        {
            throw new GameException($dominion->race->name . ' cannot exchange resources.');
        }

        if($amount < 0)
        {
            throw new LogicException('Amount less than 0.');
        }

        // Get the resource information.
        $resources = $this->bankingCalculator->getResources($dominion);
        if (empty($resources[$source])) {
            throw new LogicException('Failed to find resource ' . $source);
        }
        if (empty($resources[$target])) {
            throw new LogicException('Failed to find resource ' . $target);
        }
        $sourceResource = $resources[$source];
        $targetResource = $resources[$target];

        $sourceResourceKey = str_replace('resource_','', $source);
        $targetResourceKey = str_replace('resource_','', $target);

        if(!in_array($sourceResourceKey, $dominion->race->resources))
        {
            throw new GameException($dominion->race->name . ' cannot use ' . Resource::where('key', $sourceResourceKey)->first()->name . '.');
        }

        if(!in_array($targetResourceKey, $dominion->race->resources))
        {
            throw new GameException($dominion->race->name . ' cannot use ' . Resource::where('key', $targetResourceKey)->first()->name . '.');
        }

        if ($amount > $dominion->{$source}) {
            throw new GameException(sprintf(
                'You do not have %s %s to exchange.',
                number_format($amount),
                $sourceResource['label']
            ));
        }

        $targetAmount = floor($amount * $sourceResource['sell'] * $targetResource['buy']);

        #$dominion->{$source} -= $amount;
        #$dominion->{$target} += $targetAmount;

        $this->resourceService->updateResources($dominion, [$sourceResourceKey => $amount]);
        $this->resourceService->updateResources($dominion, [$targetResourceKey => $targetAmount]);

        $dominion->most_recent_exchange_from = $source;
        $dominion->most_recent_exchange_to = $target;

        $this->statsService->updateStat($dominion, (str_replace('resource_', '', $source).'_sold'), $amount);
        $this->statsService->updateStat($dominion, (str_replace('resource_', '', $target).'_bought'), $targetAmount);

        $dominion->save(['event' => HistoryService::EVENT_ACTION_BANK]);

        $message = 'Your resources have been exchanged.';

        return [
            'message' => $message,
        ];
    }
}
