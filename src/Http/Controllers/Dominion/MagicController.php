<?php

namespace OpenDominion\Http\Controllers\Dominion;

use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Helpers\SpellHelper;
use OpenDominion\Http\Requests\Dominion\Actions\CastSpellRequest;
use OpenDominion\Models\Dominion;
use OpenDominion\Services\Analytics\AnalyticsEvent;
use OpenDominion\Services\Analytics\AnalyticsService;
use OpenDominion\Services\Dominion\Actions\SpellActionService;
use OpenDominion\Services\Dominion\ProtectionService;

# ODA
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\NetworthCalculator;
use OpenDominion\Calculators\Dominion\SpellDamageCalculator;
use OpenDominion\Models\Spell;

class MagicController extends AbstractDominionController
{
    public function getMagic()
    {

        $friendlyAuras = Spell::all()->where('scope','friendly')->where('class','passive')->sortBy('key');
        $hostileAuras = Spell::all()->where('scope','hostile')->where('class','passive')->sortBy('key');
        $selfAuras = Spell::all()->where('scope','self')->where('class','passive')->sortBy('key');

        $friendlyImpacts = Spell::all()->where('scope','friendly')->where('class','active')->sortBy('key');
        $hostileImpacts = Spell::all()->where('scope','hostile')->where('class','active')->sortBy('key');
        $selfImpacts = Spell::all()->where('scope','self')->where('class','active')->sortBy('key');


        $hostileInfos = Spell::all()->where('scope','hostile')->where('class','info')->sortBy('key');

        return view('pages.dominion.magic', [
            'landCalculator' => app(LandCalculator::class),
            'protectionService' => app(ProtectionService::class),
            'rangeCalculator' => app(RangeCalculator::class),
            'spellCalculator' => app(SpellCalculator::class),
            'spellHelper' => app(SpellHelper::class),
            'militaryCalculator' => app(MilitaryCalculator::class),
            'networthCalculator' => app(NetworthCalculator::class),
            'spellDamageCalculator' => app(SpellDamageCalculator::class),
            'friendlyAuras' => $friendlyAuras,
            'hostileAuras' => $hostileAuras,
            'selfAuras' => $selfAuras,
            'friendlyImpacts' => $friendlyImpacts,
            'hostileImpacts' => $hostileImpacts,
            'selfImpacts' => $selfImpacts,
            'hostileInfos' => $hostileInfos,
        ]);
    }

    public function postMagic(CastSpellRequest $request)
    {
        $dominion = $this->getSelectedDominion();
        $spellActionService = app(SpellActionService::class);

        try {
            $result = $spellActionService->castSpell(
                $dominion,
                $request->get('spell'),
                ($request->has('target_dominion') ? Dominion::findOrFail($request->get('target_dominion')) : null)
            );

        } catch (GameException $e) {
            return redirect()->back()
                ->withInput($request->all())
                ->withErrors([$e->getMessage()]);
        }

        // todo: fire laravel event
        $analyticsService = app(AnalyticsService::class);
        $analyticsService->queueFlashEvent(new AnalyticsEvent(
            'dominion',
            'magic.cast',
            $result['data']['spell'],
            $result['data']['manaCost']
        ));

        $request->session()->flash(('alert-' . ($result['alert-type'] ?? 'success')), $result['message']);

        return redirect()
            ->to($result['redirect'] ?? route('dominion.magic'))
            ->with('target_dominion', $request->get('target_dominion'));
    }
}
