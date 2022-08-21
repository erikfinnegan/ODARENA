<?php

namespace OpenDominion\Http\Controllers\Dominion;

use OpenDominion\Models\Realm;
use OpenDominion\Models\Dominion;
use OpenDominion\Services\GameEventService;
use OpenDominion\Services\Dominion\WorldNewsService;
use OpenDominion\Helpers\RaceHelper;
use OpenDominion\Helpers\RoundHelper;
use OpenDominion\Helpers\WorldNewsHelper;
use OpenDominion\Calculators\Dominion\LandCalculator;

class WorldNewsController extends AbstractDominionController
{

    public function getIndex(int $realmNumber = null)
    {
        $gameEventService = app(GameEventService::class);
        $dominion = $this->getSelectedDominion();
        $this->updateDominionNewsLastRead($dominion);

        if ($realmNumber !== null) {
            $realm = Realm::where([
                'round_id' => $dominion->round_id,
                'number' => $realmNumber,
            ])
            ->first();
        } else {
            $realm = null;
        }

        $worldNewsService = app(WorldNewsService::class);

        $townCrierData = $gameEventService->getTownCrier($dominion, $realm);
        $worldNewsData = $worldNewsService->getWorldNewsForDominion($dominion);

        $gameEvents = $townCrierData['gameEvents'];
        $dominionIds = $townCrierData['dominionIds'];

        $realmCount = Realm::where('round_id', $dominion->round_id)->count();

        $landCalculator = app(LandCalculator::class);
        $raceHelper = app(RaceHelper::class);
        $roundHelper = app(RoundHelper::class);
        $worldNewsHelper = app(WorldNewsHelper::class);

        return view('pages.dominion.world-news', compact(
            'worldNewsHelper',
            'dominionIds',
            'gameEvents',
            'realm',
            'realmCount',
            'raceHelper',
            'roundHelper',
            'landCalculator'
        ))->with('fromOpCenter', false);
    }

    protected function updateDominionNewsLastRead(Dominion $dominion): void
    {
        $dominion->news_last_read = now();
        $dominion->save();
    }

}
