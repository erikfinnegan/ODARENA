@extends('layouts.master')
@section('content')

@if(!$dominion->round->hasStarted() or $protectionService->isUnderProtection($dominion))
<div class="row">
    <div class="col-sm-12 col-md-9">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-eye-slash"></i> Insight unavailable</h3>
            </div>
            <div class="box-body">
                @if(!$dominion->round->hasStarted())
                    <p>The round has not started yet.</p>
                @elseif($protectionService->isUnderProtection($dominion))
                    <p>This dominion is under protection.</p>
                @else
                    <p>Insight is not available for this dominion right now.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@elseif($dominion->getSpellPerkValue('fog_of_war'))
<div class="row">
    <div class="col-sm-12 col-md-9">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-cloud"></i> Fog of war</h3>
            </div>
            <div class="box-body">
                <p>This dominion is temporarily hidden from insight.</p>
                <p><em>Strike at your own risk!</em></p>
                @if($insightHelper->getArchiveCount($dominion, $selectedDominion) > 0)
                    <p>
                        <a href="{{ route('dominion.insight.archive', $dominion) }}">View Archive</a> ({{ number_format($insightHelper->getArchiveCount($dominion, $selectedDominion)) }})
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
@else
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
                                <td>{{ number_format($militaryCalculator->getTotalUnitsForSlot($dominion, $dominion->race->units->get(0)->slot)) }}</td>
                            </tr>
                            <tr>
                                <td>
                                  <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString('unit2', $dominion->race) }}">
                                      {{ $dominion->race->units->get(1)->name }}:
                                  </span>
                                </td>
                                <td>{{ number_format($militaryCalculator->getTotalUnitsForSlot($dominion, $dominion->race->units->get(1)->slot)) }}</td>
                            </tr>
                            <tr>
                                <td>
                                  <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString('unit3', $dominion->race) }}">
                                      {{ $dominion->race->units->get(2)->name }}:
                                  </span>
                                </td>
                                <td>{{ number_format($militaryCalculator->getTotalUnitsForSlot($dominion, $dominion->race->units->get(2)->slot)) }}</td>
                            </tr>
                            <tr>
                                <td>
                                  <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString('unit4', $dominion->race) }}">
                                      {{ $dominion->race->units->get(3)->name }}:
                                  </span>
                                </td>
                                <td>{{ number_format($militaryCalculator->getTotalUnitsForSlot($dominion, $dominion->race->units->get(3)->slot)) }}</td>
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
            <div class="box-body text-center">
                  <form action="{{ route('dominion.insight.archive', $dominion) }}" method="post">
                      @csrf
                      <input type="hidden" name="target_dominion_id" value="{{ $dominion->id }}">
                      <input type="hidden" name="round_tick" value="{{ $selectedDominion->round->ticks }}">
                      <button class="btn btn-primary btn-block" type="submit" id="capture">Archive this Insight</button>
                  </form>
                  @if($insightHelper->getArchiveCount($dominion, $selectedDominion) > 0)
                      <p>
                          <a href="{{ route('dominion.insight.archive', $dominion) }}">View Archive</a> ({{ number_format($insightHelper->getArchiveCount($dominion, $selectedDominion)) }})
                      </p>
                  @endif
            </div>
        </div>
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
                        <li><a href="{{ route('dominion.insight.show', $barbarian) }}">{{ $barbarian->name }}</a> ({{ $spellCalculator->getTicksRemainingOfAnnexation($dominion, $barbarian) . ' ' . str_plural('tick', $spellCalculator->getTicksRemainingOfAnnexation($dominion, $barbarian))}} remaining)</li>
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
            @php
                $activePassiveSpells = $spellCalculator->getPassiveSpellsCastOnDominion($dominion);
            @endphp

            @if(count($activePassiveSpells) > 0)

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
                        @foreach ($activePassiveSpells as $dominionSpell)
                            @php
                                $spell = OpenDominion\Models\Spell::where('id', $dominionSpell->spell_id)->first();
                                $caster = $spellCalculator->getCaster($dominion, $spell->key);
                            @endphp
                            <tr>
                                <td>{{ $spell->name }}</td>
                                <td>
                                    <ul>
                                    @foreach($spellHelper->getSpellEffectsString($spell, $dominion->race) as $effect)
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
            @else
                <div class="box-body">
                    <p>There are currently no spells affecting this dominion.</p>
                </div>
            @endif
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
                                        $improvementPerkMax = $dominion->extractImprovementPerkValues($perk->pivot->value)[0] * (1 + $dominion->getBuildingPerkMultiplier('improvements') + $dominion->getBuildingPerkMultiplier('improvements_capped') + $dominion->getTechPerkMultiplier('improvements') + $dominion->getSpellPerkMultiplier('improvements') + $dominion->race->getPerkMultiplier('improvements_max'));
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

            @slot('title', 'Buildings')
            @slot('titleIconClass', 'fa fa-home')
            @slot('noPadding', true)
            @slot('titleExtra')
                @if($dominion->race->name == 'Swarm')
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
    <div class="col-sm-12 col-md-6">
        @component('partials.dominion.insight.box')

            @slot('title', 'Incoming buildings')
            @slot('titleIconClass', 'fa fa-clock-o')
            @slot('titleExtra')
                <span class="pull-right">Incoming Buildings: <strong>{{ number_format($queueService->getConstructionQueueTotal($dominion)) }}</strong> ({{ number_format((($queueService->getConstructionQueueTotal($dominion) / $landCalculator->getTotalLand($dominion)) * 100), 2) }}%)</span>
            @endslot
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
                                    $amount = $queueService->getConstructionQueueAmount($dominion, "building_{$building->key}", $i);
                                @endphp
                                <td class="text-center">
                                    @if ($amount === 0)
                                        -
                                    @else
                                        {{ number_format($amount) }}
                                    @endif
                                </td>
                            @endfor
                            <td class="text-center">{{ number_format($queueService->getConstructionQueueTotalByResource($dominion, "building_{$building->key}")) }}</td>
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

            @slot('title', 'Land')
            @slot('titleIconClass', 'ra ra-honeycomb')
            <table class="table">
                <colgroup>
                    <col>
                    <col width="100">
                    <col width="100">
                    <col width="100">
                    @if ($dominion->race->getPerkValue('land_improvements') or $dominion->race->getPerkValue('defense_from_forest'))
                        <col width="150">
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
                            <td class="text-center">{{ number_format($dominion->{'land_' . $landType}) }}</td>
                            <td class="text-center">{{ number_format(($dominion->{'land_' . $landType} / $landCalculator->getTotalLand($dominion)) * 100, 2) }}%</td>
                            <td class="text-center">{{ number_format($landCalculator->getTotalBarrenLandByLandType($dominion, $landType)) }}</td>

                            @if ($dominion->race->getPerkValue('land_improvements'))
                                <td class="text-center">
                                      @if($landType == 'plain')
                                          +{{ number_format($landImprovementCalculator->getOffensivePowerBonus($dominion)*100,2) }}% Offensive Power
                                      @elseif($landType == 'mountain')
                                          +{{ number_format($landImprovementCalculator->getGoldProductionBonus($dominion)*100,2) }}% Gold Production
                                      @elseif($landType == 'swamp')
                                          +{{ number_format($landImprovementCalculator->getWizardPowerBonus($dominion)*100,2) }}% Wizard Strength
                                      @elseif($landType == 'forest')
                                          +{{ number_format($landImprovementCalculator->getPopulationBonus($dominion)*100,2) }}% Max Population
                                      @elseif($landType == 'hill')
                                          +{{ number_format($landImprovementCalculator->getDefensivePowerBonus($dominion)*100,2) }}% Defensive Power
                                      @elseif($landType == 'water')
                                          +{{ number_format($landImprovementCalculator->getFoodProductionBonus($dominion)*100,2) }}% Food Production
                                      @endif
                                </td>
                            @endif

                            @if ($dominion->race->getPerkValue('defense_from_' . $landType))
                                <td class="text-center">
                                      +{{ number_format($militaryCalculator->getDefensivePowerModifierFromLandType($dominion, $landType)*100,2) }}% Defensive Power
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endcomponent
    </div>

    <div class="col-sm-12 col-md-6">
        @component('partials.dominion.insight.box')

            @slot('title', 'Incoming land breakdown')
            @slot('titleIconClass', 'fa fa-clock-o')
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
                                    $amount = $queueService->getExplorationQueueAmount($dominion, "land_{$landType}", $i);
                                    $amount += $queueService->getInvasionQueueAmount($dominion, "land_{$landType}", $i);
                                @endphp
                                <td class="text-center">
                                    @if ($amount === 0)
                                        -
                                    @else
                                        {{ number_format($amount) }}
                                    @endif
                                </td>
                            @endfor
                            <td class="text-center">{{ number_format($queueService->getExplorationQueueTotalByResource($dominion, "land_{$landType}") + $queueService->getInvasionQueueTotalByResource($dominion, "land_{$landType}")) }}</td>
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

            @slot('title', 'Advancements')
            @slot('titleIconClass', 'fa fa-flask')
            @slot('noPadding', true)

            @if(count($advancements) > 0)
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
                        @foreach ($advancements as $advancement)
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
            @else
                <div class="box-body">
                    <p>There are currently no advancements affecting this dominion.</p>
                </div>
            @endif
        @endcomponent
    </div>

    <div class="col-sm-12 col-md-6">
        @component('partials.dominion.insight.box')
            @slot('title', 'Statistics')
            @slot('titleIconClass', 'fa fa-chart-bar')
            @slot('noPadding', true)

            <table class="table">
                <colgroup>
                    <col width="50%">
                    <col width="50%">
                </colgroup>
                <thead class="hidden-xs">
                    <tr>
                        <th colspan="2">Offensive Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Attacking victory</td>
                        <td>
                            <strong>{{ number_format($statsService->getStat($dominion, 'invasion_victories')) }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Bottomfeeds</td>
                        <td>
                            <strong>{{ number_format($statsService->getStat($dominion, 'invasion_bottomfeeds')) }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Tactical razes</td>
                        <td>
                            <strong>{{ number_format($statsService->getStat($dominion, 'invasion_razes')) }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Overwhelmed failures</td>
                        <td>
                            <strong>{{ number_format($statsService->getStat($dominion, 'invasion_failures')) }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Land conquered</td>
                        <td>
                            <strong>{{ number_format($statsService->getStat($dominion, 'land_conquered')) }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Land discovered</td>
                        <td>
                            <strong>{{ number_format($statsService->getStat($dominion, 'land_discovered')) }}</strong>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="table">
                <colgroup>
                    <col width="50%">
                    <col width="50%">
                </colgroup>
                <thead class="hidden-xs">
                    <tr>
                        <th colspan="2">Defensive Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Invasions fought back</td>
                        <td>
                            <strong>{{ number_format($statsService->getStat($dominion, 'defense_success')) }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Invasions lost</td>
                        <td>
                            <strong>{{ number_format($statsService->getStat($dominion, 'defense_failures')) }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Land lost</td>
                        <td>
                            <strong>{{ number_format($statsService->getStat($dominion, 'land_lost')) }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Land explored</td>
                        <td>
                            <strong>{{ number_format($statsService->getStat($dominion, 'land_explored')) }}</strong>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="table">
                <colgroup>
                    <col width="50%">
                    <col width="50%">
                </colgroup>
                <thead class="hidden-xs">
                    <tr>
                        <th colspan="2">Enemy Units</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Enemy units killed</td>
                        <td>
                            <strong>{{ number_format($statsService->getStat($dominion, 'units_killed')) }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Total units converted</td>
                        <td>
                            <strong>{{ number_format($statsService->getStat($dominion, 'units_converted')) }}</strong>
                        </td>
                    </tr>
                </tbody>
            </table>
        @endcomponent
    </div>
</div>


@endif

@endsection
