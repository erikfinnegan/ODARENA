<?php

namespace OpenDominion\Http\Controllers\Dominion;

use OpenDominion\Exceptions\GameException;
use OpenDominion\Http\Requests\Dominion\Actions\TheftRequest;

use OpenDominion\Helpers\TheftHelper;
use OpenDominion\Helpers\UnitHelper;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\Resource;

use OpenDominion\Calculators\NetworthCalculator;
use OpenDominion\Calculators\Dominion\EspionageCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Calculators\Dominion\TheftCalculator;


use OpenDominion\Services\Dominion\Actions\TheftActionService;

class TheftController extends AbstractDominionController
{
    public function getTheft()
    {
        $dominion = $this->getSelectedDominion();

        return view('pages.dominion.theft', [
            'unitHelper' => app(UnitHelper::class),
            'theftHelper' => app(TheftHelper::class),

            'landCalculator' => app(LandCalculator::class),
            'militaryCalculator' => app(MilitaryCalculator::class),
            'networthCalculator' => app(NetworthCalculator::class),
            'rangeCalculator' => app(RangeCalculator::class),
            'theftCalculator' => app(TheftCalculator::class),

        ]);
    }

    public function postTheft(TheftRequest $request)
    {
        $unitHelper = app(UnitHelper::class);

        $thief = $this->getSelectedDominion();
        $target = Dominion::findOrFail($request->target_dominion);
        $resource = Resource::findOrFail($request->resource);
        $units = $request->get('unit');

        $theftActionService = app(TheftActionService::class);

        try {
            $result = $theftActionService->steal($thief, $target, $resource, $units);

        } catch (GameException $e) {
            return redirect()->back()
                ->withInput($request->all())
                ->withErrors([$e->getMessage()]);
        }

        // analytics event

        $request->session()->flash(('alert-' . ($result['alert-type'] ?? 'success')), $result['message']);
        return redirect()->to($result['redirect'] ?? route('dominion.theft'));


    }
}
