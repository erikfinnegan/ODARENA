<?php

namespace OpenDominion\Http\Controllers\Dominion;

use Illuminate\Http\Request;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Helpers\RaceHelper;
use OpenDominion\Helpers\RealmHelper;
use OpenDominion\Helpers\SpellHelper;
use OpenDominion\Helpers\UnitHelper;

use OpenDominion\Models\Deity;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\InfoOp;
use OpenDominion\Models\Race;
use OpenDominion\Models\Realm;
use OpenDominion\Models\Spell;
use OpenDominion\Models\Title;

class CalculationsController extends AbstractDominionController
{
    public function getIndex(Request $request)
    {
        $selectedDominion = $this->getSelectedDominion();

        $targetDominionId = $request->input('dominion');
        $targetDominion= null;
        $targetInfoOps = null;

        if ($targetDominionId !== null)
        {
            $dominion = $this->getSelectedDominion();
            $targetDominion = Dominion::find($targetDominionId);
            if ($targetDominion !== null)
            {
                $targetInfoOps = InfoOp::query()
                    ->where('target_dominion_id', $targetDominionId)
                    ->where('source_realm_id', $dominion->realm->id)
                    ->where('latest', true)
                    ->get()
                    ->filter(function($infoOp) {
                        if ($infoOp->type == 'barracks_spy') {
                            $hourTaken = $infoOp->created_at->startOfHour();
                            if ($hourTaken->diffInHours(now()) > 11) {
                                return false;
                            }
                        }
                        return true;
                    })
                    ->keyBy('type');
            }
        }
        $races = Race::with(['units', 'units.perks'])->where('playable',1)->orderBy('name')->get();

        $barbarian = Race::with(['units', 'units.perks'])->where('name','Barbarian')->get();

        $allRaces = $races->merge($barbarian)->sortBy('name');

        return view('pages.dominion.calculations', [
            'landCalculator' => app(LandCalculator::class),
            'targetDominion' => $targetDominion,
            'targetInfoOps' => $targetInfoOps,

            'deities' => Deity::all()->where('enabled',1)->sortBy('name'),
            'races' => $allRaces->all(),
            'realms' => Realm::all()->where('round_id', $selectedDominion->round->id),
            'spells' => Spell::all()->where('enabled',1)->where('type', '!=', 'active')->sortBy('name'),
            'titles' => Title::all()->where('enabled',1)->sortBy('name'),

            'raceHelper' => app(RaceHelper::class),
            'realmHelper' => app(RealmHelper::class),
            'spellHelper' => app(SpellHelper::class),
            'unitHelper' => app(UnitHelper::class),
        ]);
    }
}
