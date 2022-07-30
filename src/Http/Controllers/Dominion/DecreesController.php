<?php

namespace OpenDominion\Http\Controllers\Dominion;

use OpenDominion\Calculators\Dominion\GovernmentCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Calculators\NetworthCalculator;
use OpenDominion\Helpers\DecreeHelper;
use OpenDominion\Helpers\DeityHelper;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Http\Requests\Dominion\Actions\GovernmentActionRequest;
use OpenDominion\Services\Dominion\Actions\GovernmentActionService;
use OpenDominion\Services\Dominion\GovernmentService;
#use OpenDominion\Services\Dominion\DecreeService;
use OpenDominion\Services\Dominion\DeityService;
use OpenDominion\Calculators\RealmCalculator;

use OpenDominion\Models\Decree;
use OpenDominion\Models\DecreeState;
use OpenDominion\Models\Deity;


class DecreesController extends AbstractDominionController
{
    public function getIndex()
    {
        $dominion = $this->getSelectedDominion();
        $decreeHelper = app(DecreeHelper::class);
        $decrees = $decreeHelper->getDecreesByRace($dominion->race);

        return view('pages.dominion.decrees', [
            'decreeHelper' => $decreeHelper,
            'decrees' => $decrees
        ]);
    }


    public function postRevokeDecree(GovernmentActionRequest $request)
    {
        $dominion = $this->getSelectedDominion();
        $deityService = app(DeityService::class);
        $deity = $dominion->deity;

        try {
            $deityService->renounceDeity($dominion, $deity);
        } catch (GameException $e) {
            return redirect()
                ->back()
                ->withInput($request->all())
                ->withErrors([$e->getMessage()]);
        }

        $request->session()->flash('alert-danger', "You have renounced your devotion to {$deity->name}.");
        return redirect()->route('dominion.government');
    }

    public function postIssueDecree(GovernmentActionRequest $request)
    {
        $dominion = $this->getSelectedDominion();
        $decreeService = app(DecreeService::class);
        $decreeId = $request->get('decree_id');
        $decreeStateKey = $request->get('decree_state_key');

        $decree = Decree::findOrfail($decreeId);
        $decreeState = DecreeState::where('key', $decreeStateKey)->first();

        try {
            $decreeService->enactDecree($dominion, $decree, $decreeState);
        } catch (GameException $e) {
            return redirect()
                ->back()
                ->withInput($request->all())
                ->withErrors([$e->getMessage()]);
        }

        $request->session()->flash('alert-primary', "Your new decree regarding {$decree->name} has been enacted.");
        return redirect()->route('dominion.government');
    }

}
