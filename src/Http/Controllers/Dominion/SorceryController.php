<?php

namespace OpenDominion\Http\Controllers\Dominion;

use OpenDominion\Exceptions\GameException;

use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;

use OpenDominion\Helpers\SorceryHelper;
use OpenDominion\Helpers\SpellHelper;
use OpenDominion\Helpers\UnitHelper;

use OpenDominion\Http\Requests\Dominion\Actions\CastSpellRequest;
use OpenDominion\Http\Requests\Dominion\Actions\PerformEspionageRequest;
use OpenDominion\Http\Requests\Dominion\Actions\OffensiveOpsRequest;
use OpenDominion\Models\Dominion;
use OpenDominion\Services\Dominion\Actions\SpellActionService;
use OpenDominion\Services\Dominion\Actions\EspionageActionService;
use OpenDominion\Services\Dominion\ProtectionService;

# ODA
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\NetworthCalculator;
use OpenDominion\Calculators\Dominion\SpellDamageCalculator;
use OpenDominion\Models\Spell;

class SorceryController extends AbstractDominionController
{
    public function getSorcery()
    {
        $dominion = $this->getSelectedDominion();
        $sorceryHelper = app(SorceryHelper::class);

        $spells = $sorceryHelper->getSorcerySpellsForRace($dominion->race) ;#Spell::all()->where('scope','hostile')->whereIn('class',['active'/*,'passive'*/])->where('enabled',1)->sortBy('name');

        return view('pages.dominion.sorcery', [
            'spells' => $spells,
            'icon' => '',
            'boxClass' => '',

            'spellHelper' => app(SpellHelper::class),
            'sorceryHelper' => $sorceryHelper,
            'unitHelper' => app(UnitHelper::class),

            'landCalculator' => app(LandCalculator::class),
            'militaryCalculator' => app(MilitaryCalculator::class),
            'networthCalculator' => app(NetworthCalculator::class),
            'protectionService' => app(ProtectionService::class),
            'rangeCalculator' => app(RangeCalculator::class),
            'resourceCalculator' => app(ResourceCalculator::class),
            'spellCalculator' => app(SpellCalculator::class),
            'spellDamageCalculator' => app(SpellDamageCalculator::class),
        ]);
    }

    public function postSorcery(OffensiveOpsRequest $request)
    {

        $dominion = $this->getSelectedDominion();
        $spellActionService = app(SpellActionService::class);

        $spell = Spell::where('key', $request->get('operation'))->first();

        $target = Dominion::findOrFail($request->get('target_dominion'));

        try
        {
            $result = $spellActionService->castSpell(
                $dominion,
                $spell->key,
                $target
            );
        }
        catch (GameException $e)
        {
            return redirect()->back()
                ->withInput($request->all())
                ->withErrors([$e->getMessage()]);
        }

        $request->session()->flash(('alert-' . ($result['alert-type'] ?? 'success')), $result['message']);

        return redirect()
            ->to($result['redirect'] ?? route('dominion.sorcery'))
            ->with('target_dominion', $request->get('target_dominion'));

    }
}
