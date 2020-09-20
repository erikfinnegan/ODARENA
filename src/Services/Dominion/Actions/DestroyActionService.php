<?php

namespace OpenDominion\Services\Dominion\Actions;

use OpenDominion\Exceptions\GameException;
use OpenDominion\Models\Dominion;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Traits\DominionGuardsTrait;

use OpenDominion\Calculators\Dominion\SpellCalculator;

class DestroyActionService
{
    use DominionGuardsTrait;

        /** @var SpellCalculator */
        protected $spellCalculator;

        public function __construct()
        {
            $this->spellCalculator = app(SpellCalculator::class);
        }

    /**
     * Does a destroy buildings action for a Dominion.
     *
     * @param Dominion $dominion
     * @param array $data
     * @return array
     * @throws GameException
     */
    public function destroy(Dominion $dominion, array $data): array
    {
        $this->guardLockedDominion($dominion);

        // Qur: Statis
        if($this->spellCalculator->isSpellActive($dominion, 'stasis'))
        {
            throw new GameException('You are in stasis and cannot destroy buildings.');
        }

        $data = array_map('\intval', $data);

        $totalBuildingsToDestroy = array_sum($data);

        if ($totalBuildingsToDestroy < 0) {
            throw new GameException('The destruction was not completed due to bad input.');
        }

        foreach ($data as $buildingType => $amount) {
            if ($amount === 0) {
                continue;
            }

            if ($amount < 0) {
                throw new GameException('Destruction was not completed due to bad input.');
            }

            if ($amount > $dominion->{'building_' . $buildingType}) {
                throw new GameException('The destruction was not completed due to bad input.');
            }
        }

        foreach ($data as $buildingType => $amount) {
            $dominion->{'building_' . $buildingType} -= $amount;
        }

        $dominion->save(['event' => HistoryService::EVENT_ACTION_DESTROY]);

        return [
            'message' => sprintf(
                'Destruction of %s %s is complete.',
                number_format($totalBuildingsToDestroy),
                str_plural('building', $totalBuildingsToDestroy)
            ),
            'data' => [
                'totalBuildingsDestroyed' => $totalBuildingsToDestroy,
            ],
        ];
    }
}
