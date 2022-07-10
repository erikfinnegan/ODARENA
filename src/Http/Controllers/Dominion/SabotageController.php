<?php

namespace OpenDominion\Http\Controllers\Dominion;

use OpenDominion\Exceptions\GameException;
use OpenDominion\Http\Requests\Dominion\Actions\SabotageRequest;

use OpenDominion\Helpers\EspionageHelper;
use OpenDominion\Helpers\SabotageHelper;
use OpenDominion\Helpers\UnitHelper;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\Spyop;

use OpenDominion\Calculators\NetworthCalculator;
use OpenDominion\Calculators\Dominion\EspionageCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Calculators\Dominion\SabotageCalculator;


use OpenDominion\Services\Dominion\Actions\SabotageActionService;

class SabotageController extends AbstractDominionController
{
    public function getSabotage()
    {
        $dominion = $this->getSelectedDominion();

        $espionageHelper = app(EspionageHelper::class);
        $sabotageHelper = app(SabotageHelper::class);

        $spyops = $sabotageHelper->getSabotageOperationsForRace($dominion->race) ;#Spell::all()->where('scope','hostile')->whereIn('class',['active'/*,'passive'*/])->where('enabled',1)->sortBy('name');

        return view('pages.dominion.sabotage', [
            'spyops' => $spyops,

            'espionageHelper' => $espionageHelper,
            'sabotageHelper' => $sabotageHelper,
            'unitHelper' => app(UnitHelper::class),

            'landCalculator' => app(LandCalculator::class),
            'militaryCalculator' => app(MilitaryCalculator::class),
            'networthCalculator' => app(NetworthCalculator::class),
            'rangeCalculator' => app(RangeCalculator::class),
            'sabotageCalculator' => app(SabotageCalculator::class),

        ]);
    }

    public function postSabotage(SabotageRequest $request)
    {
        $unitHelper = app(UnitHelper::class);

        $saboteur = $this->getSelectedDominion();
        $target = Dominion::findOrFail($request->target_dominion);
        $spyop = Spyop::findOrFail($request->spyop);
        $units = $request->get('unit');

        $sabotageActionService = app(SabotageActionService::class);

        try {
            $result = $sabotageActionService->sabotage($saboteur, $target, $spyop, $units);
        } catch (GameException $e) {
            return redirect()->back()
                ->withInput($request->all())
                ->withErrors([$e->getMessage()]);
        }

        $request->session()->flash(('alert-' . ($result['alert-type'] ?? 'success')), $result['message']);
        return redirect()->to($result['redirect'] ?? route('dominion.sabotage'));

    }
}
