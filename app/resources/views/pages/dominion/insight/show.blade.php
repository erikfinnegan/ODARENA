@extends('layouts.master')

@section('page-header', 'Insight')

@section('content')
    <div class="row">
        <div class="col-sm-12 col-md-9">
            @component('partials.dominion.insight.box')
            @slot('title', ('The Dominion of ' . $dominion->name))
            @slot('titleIconClass', 'fa fa-chart-bar')
                @slot('tableResponsive', false)
                @slot('noPadding', true)

                <div class="row">
                    <div class="col-xs-12 col-sm-4">
                        <table class="table">
                            <colgroup>
                                <col width="50%">
                                <col width="50%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th colspan="2">Overview</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Ruler:</td>
                                    <td>
                                        @if(isset($dominion->title->name))
                                              <em>
                                                  <span data-toggle="tooltip" data-placement="top" title="{!! $titleHelper->getRulerTitlePerksForDominion($dominion) !!}">
                                                      {{ $dominion->title->name }}
                                                  </span>
                                              </em>
                                        @endif

                                        {{ $dominion->ruler_name }}

                                    </td>
                                </tr>
                                <tr>
                                    <td>Faction:</td>
                                    <td>{{ $dominion->race->name }}</td>
                                </tr>
                                <tr>
                                    <td>Land:</td>
                                    <td>
                                        {{ number_format($landCalculator->getTotalland($dominion)) }}
                                        <span class="{{ $rangeCalculator->getDominionRangeSpanClass($selectedDominion, $dominion) }}">
                                            ({{ number_format($rangeCalculator->getDominionRange($selectedDominion, $dominion), 2) }}%)
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{ str_plural($raceHelper->getPeasantsTerm($dominion->race)) }}:</td>
                                    <td>{{ number_format($dominion->peasants) }}</td>
                                </tr>
                                <tr>
                                    <td>Employment:</td>
                                    <td>{{ number_format($populationCalculator->getEmploymentPercentage($dominion), 2) }}%</td>
                                </tr>
                                <tr>
                                    <td>Networth:</td>
                                    <td>{{ number_format($networthCalculator->getDominionNetworth($dominion)) }}</td>
                                </tr>
                                <tr>
                                    <td>Prestige:</td>
                                    <td>
                                        <span data-toggle="tooltip" data-placement="top" title="<em>Effective: {{ number_format(floor($dominion->prestige)) }}<br>Actual: {{ number_format((float)$dominion->prestige,8) }}<br>Interest: {{ number_format((float)$productionCalculator->getPrestigeInterest($dominion),8) }}</em>">
                                            {{ number_format(floor($dominion->prestige)) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Victories:</td>
                                    <td>{{ $statsService->getStat($dominion, 'invasion_victories') }}</td>
                                </tr>
                                <tr>
                                    <td>Net Victories:</td>
                                    <td>{{ $militaryCalculator->getNetVictories($dominion) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-xs-12 col-sm-4">
                        <table class="table">
                            <colgroup>
                                <col width="50%">
                                <col width="50%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th colspan="2">Resources</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Gold:</td>
                                    <td>{{ number_format($dominion->resource_gold) }}</td>
                                </tr>
                                <tr>
                                    <td>Food:</td>
                                    <td>{{ number_format($dominion->resource_food) }}</td>
                                </tr>
                                <tr>
                                    <td>Lumber:</td>
                                    <td>{{ number_format($dominion->resource_lumber) }}</td>
                                </tr>
                                <tr>
                                    <td>Mana:</td>
                                    <td>{{ number_format($dominion->resource_mana) }}</td>
                                </tr>
                                <tr>
                                    <td>Ore:</td>
                                    <td>{{ number_format($dominion->resource_ore) }}</td>
                                </tr>
                                <tr>
                                    <td>Gems:</td>
                                    <td>{{ number_format($dominion->resource_gems) }}</td>
                                </tr>
                                <tr>
                                    <td>Experience Points:</td>
                                    <td>{{ number_format($dominion->resource_tech) }}</td>
                                </tr>

                                @if ($dominion->race->name == 'Norse')
                                <tr>
                                    <td>Champions:</td>
                                    <td>{{ number_format($dominion->resource_champion) }}</td>
                                </tr>
                                @endif
                                @if ($dominion->race->name == 'Demon')
                                    <tr>
                                        <td>Souls:</td>
                                        <td>{{ number_format($dominion->resource_soul) }}</td>
                                    </tr>
                                @endif
                                @if ($dominion->race->name == 'Demon' or $dominion->race->name == 'Beastfolk')
                                    <tr>
                                        <td>Blood:</td>
                                        <td>{{ number_format($dominion->resource_blood) }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    <div class="col-xs-12 col-sm-4">
                        <table class="table">
                            <colgroup>
                                <col width="50%">
                                <col width="50%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th colspan="2">Military</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Morale:</td>
                                    <td>{{ number_format($dominion->morale) }}%</td>
                                </tr>
                                <tr>
                                    <td>
                                        <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getDrafteeHelpString( $dominion->race) }}">
                                            {{ $raceHelper->getDrafteesTerm($dominion->race) }}:
                                        </span>
                                    </td>
                                    <td>{{ number_format($dominion->military_draftees) }}</td>
                                </tr>
                                <tr>
                                    <td>
                                      <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString('unit1', $dominion->race) }}">
                                          {{ $dominion->race->units->get(0)->name }}:
                                      </span>
                                    </td>
                                    <td>{{ number_format($dominion->military_unit1) }}</td>
                                </tr>
                                <tr>
                                    <td>
                                      <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString('unit2', $dominion->race) }}">
                                          {{ $dominion->race->units->get(1)->name }}:
                                      </span>
                                    </td>
                                    <td>{{ number_format($dominion->military_unit2) }}</td>
                                </tr>
                                <tr>
                                    <td>
                                      <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString('unit3', $dominion->race) }}">
                                          {{ $dominion->race->units->get(2)->name }}:
                                      </span>
                                    </td>
                                    <td>{{ number_format($dominion->military_unit3) }}</td>
                                </tr>
                                <tr>
                                    <td>
                                      <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString('unit4', $dominion->race) }}">
                                          {{ $dominion->race->units->get(3)->name }}:
                                      </span>
                                    </td>
                                    <td>{{ number_format($dominion->military_unit4) }}</td>
                                </tr>
                                <tr>
                                    <td>Spies:</td>
                                    <td>{{ number_format($dominion->military_spies) }}</td>
                                </tr>
                                <tr>
                                    <td>Wizards:</td>
                                    <td>{{ number_format($dominion->military_wizards) }}</td>
                                </tr>
                                <tr>
                                    <td>ArchMages:</td>
                                    <td>{{ number_format($dominion->military_archmages) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endcomponent
        </div>

        <div class="col-sm-12 col-md-3">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fas fa-pray"></i> Deity</h3>
                </div>
                <div class="box-body">
                    @if(!$dominion->hasDeity())
                        <p>This dominion is not currently devoted to a deity.</p>
                    @elseif($dominion->hasPendingDeitySubmission())
                        <p>This dominion is currently in the process of submitting to a deity.</p>
                    @else
                        @php
                            $perksList = '<ul>';
                            $perksList .= '<li>Devotion: ' . number_format($dominion->getDominionDeity()->duration) . ' ' . str_plural('tick', $dominion->getDominionDeity()->duration) . '</li>';
                            $perksList .= '<li>Range multiplier: ' . $dominion->getDeity()->range_multiplier . 'x</li>';
                            foreach($deityHelper->getDeityPerksString($dominion->getDeity(), $dominion->getDominionDeity()) as $effect)
                            {
                                $perksList .= '<li>' . ucfirst($effect) . '</li>';
                            }
                            $perksList .= '<ul>';
                        @endphp
                        <p>This dominion is devoted to <b>{{ $dominion->getDeity()->name }}</b>.</p>

                        <ul>
                        <li>Devotion: {{ number_format($dominion->getDominionDeity()->duration) . ' ' . str_plural('tick', $dominion->getDominionDeity()->duration) }}</li>
                        <li>Range multiplier: {{ $dominion->getDeity()->range_multiplier }}x</li>
                        @foreach($deityHelper->getDeityPerksString($dominion->getDeity(), $dominion->getDominionDeity()) as $effect)

                            <li>{{ ucfirst($effect) }}</li>

                        @endforeach
                        </ul>

                    @endif
                </div>
            </div>

            @if($spellCalculator->hasAnnexedDominions($dominion))
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="ra ra-castle-flag"></i> Annexed dominions</h3>
                    </div>
                    <div class="box-body">
                        <p>The Legion has has annexed Barbarians, providing <b>{{ number_format($militaryCalculator->getRawMilitaryPowerFromAnnexedDominions($dominion)) }}</b> additional raw military power.</p>

                        <ul>
                        @foreach($spellCalculator->getAnnexedDominions($dominion) as $barbarian)
                            <li><a href="{{ route('dominion.insight.show', $barbarian) }}">{{ $barbarian->name }}</a></li>
                        @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @if($spellCalculator->isAnnexed($dominion))
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="ra ra-castle-flag"></i> Annexation</h3>
                    </div>
                    <div class="box-body">
                        <p>This dominion is currently annexed, providing the Legion with <b>{{ number_format($militaryCalculator->getRawMilitaryPowerFromAnnexedDominion($dominion)) }}</b> additional raw military power.</p>
                    </div>
                </div>
            @endif
        </div>

    </div>

    <div class="row">

        <div class="col-sm-12 col-md-6">
            @component('partials.dominion.insight.box')

                @slot('title', 'Active Spells')
                @slot('titleIconClass', 'ra ra-fairy-wand')
                @slot('noPadding', true)

                <table class="table">
                    <colgroup>
                        <col width="150">
                        <col>
                        <col width="100">
                        <col width="200">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Spell</th>
                            <th>Effect</th>
                            <th class="text-center">Duration</th>
                            <th class="text-center">Cast By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($spellCalculator->getPassiveSpellsCastOnDominion($dominion) as $dominionSpell)
                            @php
                                $spell = OpenDominion\Models\Spell::where('id', $dominionSpell->spell_id)->first();
                                $caster = $spellCalculator->getCaster($dominion, $spell->key);
                            @endphp
                            <tr>
                                <td>{{ $spell->name }}</td>
                                <td>
                                    <ul>
                                    @foreach($spellHelper->getSpellEffectsString($spell, $selectedDominion->race) as $effect)
                                        <li>{{ $effect }}</li>
                                    @endforeach
                                    <ul>
                                </td>
                                <td class="text-center">{{ $dominionSpell->duration }} / {{ $spell->duration }} ticks</td>
                                <td class="text-center">
                                    <a href="{{ route('dominion.realm', $caster->realm->number) }}">{{ $caster->name }} (#{{ $caster->realm->number }})</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endcomponent
        </div>

        <div class="col-sm-12 col-md-6">
            @component('partials.dominion.insight.box')

                @slot('title', 'Improvements')
                @slot('titleIconClass', 'fa fa-arrow-up')
                @slot('noPadding', true)

                <table class="table">
                    <colgroup>
                        <col width="150">
                        <col>
                        <col width="200">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Improvement</th>
                            <th>Perks</th>
                            <th class="text-center">Invested</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($improvementHelper->getImprovementsByRace($dominion->race) as $improvement)
                            <tr>
                                <td><span data-toggle="tooltip" data-placement="top"> {{ $improvement->name }}</span></td>
                                <td>
                                    @foreach($improvement->perks as $perk)
                                        @php
                                            $improvementPerkMax = $dominion->extractImprovementPerkValues($perk->pivot->value)[0] * (1 + $dominion->getBuildingPerkMultiplier('improvements') + $dominion->getBuildingPerkMultiplier('improvements_capped') + $dominion->getTechPerkMultiplier('improvements') + $selectedDominion->getSpellPerkMultiplier('improvements') + $selectedDominion->race->getPerkMultiplier('improvements_max'));
                                            $improvementPerkCoefficient = $dominion->extractImprovementPerkValues($perk->pivot->value)[1];

                                            $spanClass = 'text-muted';

                                            if($improvementPerkMultiplier = $dominion->getImprovementPerkMultiplier($perk->key))
                                            {
                                                $spanClass = '';
                                            }
                                        @endphp

                                        <span class="{{ $spanClass }}" data-toggle="tooltip" data-placement="top" title="Max: {{ number_format($improvementPerkMax,2) }}%<br>Coefficient: {{ number_format($improvementPerkCoefficient) }}">

                                        @if($improvementPerkMultiplier > 0)
                                            +{{ number_format($improvementPerkMultiplier * 100, 2) }}%
                                        @else
                                            {{ number_format($improvementPerkMultiplier * 100, 2) }}%
                                        @endif

                                         {{ $improvementHelper->getImprovementPerkDescription($perk->key) }} <br></span>

                                    @endforeach
                                </td>
                                <td class="text-center">{{ number_format($improvementCalculator->getDominionImprovementAmountInvested($dominion, $improvement)) }}</td>
                            </tr>
                        @endforeach
                            <tr>
                                <td colspan="2" class="text-right"><strong>Total</strong></td>
                                <td class="text-center">{{ number_format($improvementCalculator->getDominionImprovementTotalAmountInvested($dominion)) }}</td>
                            </tr>

                        @php
                            $totalSabotaged = 0;
                        @endphp
                        @foreach($queueService->getSabotageQueue($dominion) as $sabotage)
                            @php
                                $totalSabotaged += $sabotage->amount;
                            @endphp
                        @endforeach
                        @if($totalSabotaged > 0)
                            <tr>
                                <td colspan="2" class="text-right"><strong>Sabotaged</strong><br><small class="text-muted">Will be restored automatically</small></td>
                                <td class="text-center">{{ number_format($totalSabotaged) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12 col-md-6">
            @component('partials.dominion.insight.box')

                @slot('title', 'Units in training and home')
                @slot('titleIconClass', 'ra ra-sword')

                @slot('noPadding', true)

                <table class="table">
                    <colgroup>
                        <col>
                        @for ($i = 1; $i <= 12; $i++)
                            <col width="20">
                        @endfor
                        <col width="100">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Unit</th>
                            @for ($i = 1; $i <= 12; $i++)
                                <th class="text-center">{{ $i }}</th>
                            @endfor
                            <th class="text-center">Home<br>(Training)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($unitHelper->getUnitTypes() as $unitType)
                            <tr>
                                <td>
                                  <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString($unitType, $dominion->race) }}">
                                    {{ $unitHelper->getUnitName($unitType, $dominion->race) }}
                                  </span>
                                </td>
                                @for ($i = 1; $i <= 12; $i++)
                                    @php
                                        $amount = $queueService->getTrainingQueueAmount($dominion, "military_{$unitType}", $i);
                                    @endphp
                                    <td class="text-center">
                                        @if ($amount === 0)
                                            -
                                        @else
                                            {{ number_format($amount) }}
                                        @endif
                                    </td>
                                @endfor
                                <td class="text-center">
                                    {{ number_format($dominion->{'military_' . $unitType}) }}
                                    ({{ number_format($queueService->getTrainingQueueTotalByResource($dominion, "military_{$unitType}")) }})
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endcomponent
        </div>
        <div class="col-sm-12 col-md-6">
            @component('partials.dominion.insight.box')

                @slot('title', 'Units returning from battle')
                @slot('titleIconClass', 'ra ra-boot-stomp')
                @slot('noPadding', true)

                <table class="table">
                    <colgroup>
                        <col>
                        @for ($i = 1; $i <= 12; $i++)
                            <col width="20">
                        @endfor
                        <col width="100">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Unit</th>
                            @for ($i = 1; $i <= 12; $i++)
                                <th class="text-center">{{ $i }}</th>
                            @endfor
                            <th class="text-center"><br>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (range(1, 4) as $slot)
                            @php
                                $unitType = ('unit' . $slot);
                            @endphp
                            <tr>
                                <td>
                                  <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString($unitType, $dominion->race) }}">
                                    {{ $unitHelper->getUnitName($unitType, $dominion->race) }}
                                  </span>
                                </td>
                                @for ($i = 1; $i <= 12; $i++)
                                    @php
                                        $amount = $queueService->getInvasionQueueAmount($dominion, "military_{$unitType}", $i);
                                    @endphp
                                    <td class="text-center">
                                        @if ($amount === 0)
                                            -
                                        @else
                                            {{ number_format($amount) }}
                                        @endif
                                    </td>
                                @endfor
                                <td class="text-center">
                                    {{ number_format($queueService->getInvasionQueueTotalByResource($dominion, "military_{$unitType}")) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endcomponent
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12 col-md-6">
            @component('partials.dominion.insight.box')

                @slot('title', 'Constructed Buildings')
                @slot('titleIconClass', 'fa fa-home')
                @slot('noPadding', true)
                @slot('titleExtra')
                    @if($selectedDominion->race->name == 'Swarm')
                        <span class="pull-right" data-toggle="tooltip" data-placement="top" title="Barren vs Swarm: <strong>{{ number_format($landCalculator->getTotalBarrenLandForSwarm($dominion)) }}</strong> ({{ number_format((($landCalculator->getTotalBarrenLandForSwarm($dominion) / $landCalculator->getTotalLand($dominion)) * 100), 2) }}%)">
                    @else
                        <span class="pull-right">
                    @endif
                        Barren Land: <strong>{{ number_format($landCalculator->getTotalBarrenLand($dominion)) }}</strong> ({{ number_format((($landCalculator->getTotalBarrenLand($dominion) / $landCalculator->getTotalLand($dominion)) * 100), 2) }}%)</span>
                @endslot

                <table class="table">
                    <colgroup>
                        <col>
                        <col width="100">
                        <col width="100">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Building Type</th>
                            <th class="text-center">Amount</th>
                            <th class="text-center">% of land</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($buildingHelper->getBuildingsByRace($dominion->race) as $building)
                            @php
                                $amount = $buildingCalculator->getBuildingAmountOwned($dominion, $building);
                            @endphp
                            <tr>
                                <td>
                                    <span data-toggle="tooltip" data-placement="top" title="{!! $buildingHelper->getBuildingDescription($building) !!}">
                                        {{ $building->name }}
                                    </span>
                                </td>
                                <td class="text-center">{{ number_format($amount) }}</td>
                                <td class="text-center">{{ number_format((($amount / $landCalculator->getTotalLand($dominion)) * 100), 2) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endcomponent
        </div>
{{--
        <div class="col-sm-12 col-md-6">
            @component('partials.dominion.insight.box')

                @slot('title', 'Incoming building breakdown')
                @slot('titleIconClass', 'fa fa-clock-o')
                @if(isset($infoOp->data['constructing_land']))
                @slot('titleExtra')
                    <span class="pull-right">Incoming Buildings: <strong>{{ number_format(array_get($infoOp->data, 'constructing_land')) }}</strong> ({{ number_format(((array_get($infoOp->data, 'constructing_land') / $landCalculator->getTotalLand($dominion)) * 100), 2) }}%)</span>
                @endslot
                @endif

                @if ($infoOp === null)
                    <p>No recent data available.</p>
                    <p>Perform espionage operation 'Survey Dominion' to reveal information.</p>
                @else
                    @slot('noPadding', true)

                    <table class="table">
                        <colgroup>
                            <col>
                            @for ($i = 1; $i <= 12; $i++)
                                <col width="20">
                            @endfor
                            <col width="100">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Building Type</th>
                                @for ($i = 1; $i <= 12; $i++)
                                    <th class="text-center">{{ $i }}</th>
                                @endfor
                                <th class="text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($buildingHelper->getBuildingsByRace($dominion->race) as $building)
                                <tr>
                                    <td>
                                        <span data-toggle="tooltip" data-placement="top" title="{!! $buildingHelper->getBuildingDescription($building) !!}">
                                            {{ $building->name }}
                                        </span>
                                    </td>
                                    @for ($i = 1; $i <= 12; $i++)
                                        @php
                                            $amount = array_get($infoOp->data, "constructing.{$building->key}.{$i}", 0);
                                        @endphp
                                        <td class="text-center">
                                            @if ($amount === 0)
                                                -
                                            @else
                                                {{ number_format($amount) }}
                                            @endif
                                        </td>
                                    @endfor
                                    <td class="text-center">
                                        @if ($amountConstructing = array_get($infoOp->data, "constructing.{$building->key}"))
                                            {{ number_format(array_sum($amountConstructing)) }}
                                        @else
                                            0
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endcomponent
        </div>

    </div>
    <div class="row">

        <div class="col-sm-12 col-md-6">
            @component('partials.dominion.insight.box')

                @slot('title', 'Explored Land')
                @slot('titleIconClass', 'ra ra-honeycomb')

                @if ($infoOp === null)
                    <p>No recent data available.</p>
                    <p>Perform espionage operation 'Land Spy' to reveal information.</p>
                @else
                    @slot('noPadding', true)

                    <table class="table">
                        <colgroup>
                            <col>
                            <col width="100">
                            <col width="100">
                            <col width="100">
                            @if ($dominion->race->getPerkValue('land_improvements'))
                                <col width="100">
                            @endif
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Land Type</th>
                                <th class="text-center">Number</th>
                                <th class="text-center">% of total</th>
                                <th class="text-center">Barren</th>
                                @if ($dominion->race->getPerkValue('land_improvements') or $dominion->race->getPerkValue('defense_from_forest'))
                                    <th class="text-center">Bonus</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($landHelper->getLandTypes() as $landType)
                                <tr>
                                    <td>
                                        {{ ucfirst($landType) }}
                                        @if ($landType === $dominion->race->home_land_type)
                                            <small class="text-muted"><i>(home)</i></small>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ number_format(array_get($infoOp->data, "explored.{$landType}.amount")) }}</td>
                                    <td class="text-center">{{ number_format(array_get($infoOp->data, "explored.{$landType}.percentage"), 2) }}%</td>
                                    <td class="text-center">{{ number_format(array_get($infoOp->data, "explored.{$landType}.barren")) }}</td>

                                    @if ($dominion->race->getPerkValue('land_improvements') and isset($infoOp->data['land_improvements']))
                                        <td class="text-center">
                                              +{{ number_format($infoOp->data['land_improvements'][$landType]*100,2) }}%

                                              @if($landType == 'plain')
                                                  Offensive Power
                                              @elseif($landType == 'mountain')
                                                  Gold Production
                                              @elseif($landType == 'swamp')
                                                   Wizard Strength
                                              @elseif($landType == 'forest')
                                                  Max Population
                                              @elseif($landType == 'hill')
                                                  Defensive Power
                                              @elseif($landType == 'water')
                                                  Food and Boat Production
                                              @endif
                                        </td>
                                    @endif

                                    @if ($dominion->race->getPerkValue('defense_from_forest') and isset($infoOp->data['landtype_defense']))
                                        <td class="text-center">
                                            @if($infoOp->data['landtype_defense'][$landType] !== 0)
                                                +{{ number_format($infoOp->data['landtype_defense'][$landType]*100,2) }}% Defensive Power
                                            @else
                                                &mdash;
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @slot('boxFooter')
                    @if ($infoOp !== null)
                        <em>Revealed {{ $infoOp->created_at }} by {{ $infoOp->sourceDominion->name }}</em>
                        @if ($infoOp->isInvalid())
                            <span class="label label-danger">Invalid</span>
                        @elseif ($infoOp->isStale())
                            <span class="label label-warning">Stale</span>
                        @endif
                        @if ($infoOp->isInaccurate())
                            <span class="label label-info" data-toggle="tooltip" data-placement="top" title="The information is distorted by magic, consult the Scribes">Inaccurate</span>
                        @endif
                    @endif

                    <div class="pull-right">
                        <form action="{{ route('dominion.insight') }}" method="post" role="form">
                            @csrf
                            <input type="hidden" name="espionage_dominion" value="{{ $dominion->id }}">
                            <input type="hidden" name="type" value="espionage">
                            <input type="hidden" name="operation" value="land_spy">
                            <button type="submit" class="btn btn-sm btn-primary">Land Spy</button>
                        </form>
                    </div>
                    <div class="clearfix"></div>

                    <div class="text-center">
                        <a href="{{ route('dominion.insight.archive', [$dominion, 'land_spy']) }}">View Archives</a>
                    </div>
                @endslot
            @endcomponent
        </div>

        <div class="col-sm-12 col-md-6">
            @component('partials.dominion.insight.box')

                @slot('title', 'Incoming land breakdown')
                @slot('titleIconClass', 'fa fa-clock-o')

                @if ($infoOp === null)
                    <p>No recent data available.</p>
                    <p>Perform espionage operation 'Land Spy' to reveal information.</p>
                @else
                    @slot('noPadding', true)

                    <table class="table">
                        <colgroup>
                            <col>
                            @for ($i = 1; $i <= 12; $i++)
                                <col width="20">
                            @endfor
                            <col width="100">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Land Type</th>
                                @for ($i = 1; $i <= 12; $i++)
                                    <th class="text-center">{{ $i }}</th>
                                @endfor
                                <th class="text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($landHelper->getLandTypes() as $landType)
                                <tr>
                                    <td>
                                        {{ ucfirst($landType) }}
                                        @if ($landType === $dominion->race->home_land_type)
                                            <small class="text-muted"><i>(home)</i></small>
                                        @endif
                                    </td>
                                    @for ($i = 1; $i <= 12; $i++)
                                        @php
                                            $amount = array_get($infoOp->data, "incoming.{$landType}.{$i}", 0);
                                        @endphp
                                        <td class="text-center">
                                            @if ($amount === 0)
                                                -
                                            @else
                                                {{ number_format($amount) }}
                                            @endif
                                        </td>
                                    @endfor
                                    <td class="text-center">
                                        @if ($amountIncoming = array_get($infoOp->data, "incoming.{$landType}"))
                                            {{ number_format(array_sum($amountIncoming)) }}
                                        @else
                                            0
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endcomponent
        </div>

    </div>
    <div class="row">

        <div class="col-sm-12 col-md-6">
            @component('partials.dominion.insight.box')

                @slot('title', 'Technological Advancements')
                @slot('titleIconClass', 'fa fa-flask')

                @if ($infoOp === null)
                    <p>No recent data available.</p>
                    <p>Cast magic spell 'Vision' to reveal information.</p>
                @else
                    @slot('noPadding', true)

                    <table class="table">
                        <colgroup>
                            <col width="150">
                            <col>
                            <col>
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Advancement</th>
                                <th>Level</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($infoOp->data['advancements'] as $advancement)
                                @php
                                    $tech = OpenDominion\Models\Tech::where('key', $advancement['key'])->firstOrFail();
                                @endphp
                                <tr>
                                    <td>{{ $advancement['name'] }}</td>
                                    <td>{{ $advancement['level'] }}</td>
                                    <td>{{ $techHelper->getTechDescription($tech) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @slot('boxFooter')
                    @if ($infoOp !== null)
                        <em>Revealed {{ $infoOp->created_at }} by {{ $infoOp->sourceDominion->name }}</em>
                        @if ($infoOp->isInvalid())
                            <span class="label label-danger">Invalid</span>
                        @elseif ($infoOp->isStale())
                            <span class="label label-warning">Stale</span>
                        @endif
                        @if ($infoOp->isInaccurate())
                            <span class="label label-info" data-toggle="tooltip" data-placement="top" title="The information is distorted by magic, consult the Scribes">Inaccurate</span>
                        @endif
                    @endif

                    <div class="pull-right">
                        <form action="{{ route('dominion.insight') }}" method="post" role="form">
                            @csrf
                            <input type="hidden" name="spell_dominion" value="{{ $dominion->id }}">
                            <input type="hidden" name="type" value="spell">
                            <input type="hidden" name="operation" value="vision">
                            <button type="submit" class="btn btn-sm btn-primary">Vision ({{ number_format($spellCalculator->getManaCost($selectedDominion, 'vision')) }} mana)</button>
                        </form>
                    </div>
                    <div class="clearfix"></div>

                    <div class="text-center">
                        <a href="{{ route('dominion.insight.archive', [$dominion, 'vision']) }}">View Archives</a>
                    </div>
                @endslot
            @endcomponent
        </div>

        <div class="col-sm-12 col-md-6">
            @component('partials.dominion.insight.box')
                @slot('title', 'Heroes')
                @slot('titleIconClass', 'ra ra-knight-helmet')
                <p>Not yet implemented.</p>
            @endcomponent
        </div>
         --}}
    </div>
@endsection
