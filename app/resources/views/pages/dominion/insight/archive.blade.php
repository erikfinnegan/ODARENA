
@extends('layouts.master')
@section('content')

@foreach($dominionInsights as $dominionInsight)
    @php
        $data = json_decode($dominionInsight->data, TRUE);
    @endphp
@endforeach

@if($insightHelper->getArchiveCount($dominion, $selectedDominion) == 0)
<div class="row">
    <div class="col-sm-12 col-md-9">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-eye-slash"></i> No archive</h3>
            </div>
            <div class="box-body">
                <p>No insight has been archived for this dominion yet.</p>
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
                                      <em>
                                          <span data-toggle="tooltip" data-placement="top" title="{!! $data['title_perks'] !!}">
                                              {{ $dominion->title->name }}
                                          </span>
                                      </em>

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
                                    {{ number_format($data['total_land']) }}
                                </td>
                            </tr>
                            <tr>
                                <td>{{ str_plural($raceHelper->getPeasantsTerm($dominion->race)) }}:</td>
                                <td>{{ number_format($data['peasants']) }}</td>
                            </tr>
                            <tr>
                                <td>Employment:</td>
                                <td>{{ number_format($data['employment'], 2) }}%</td>
                            </tr>
                            <tr>
                                <td>Networth:</td>
                                <td>{{ number_format($data['networth']) }}</td>
                            </tr>
                            <tr>
                                <td>Prestige:</td>
                                <td>{{ number_format($data['prestige']) }}</td>
                            </tr>
                            <tr>
                                <td>Victories:</td>
                                <td>{{ number_format($data['victories']) }}</td>
                            </tr>
                            <tr>
                                <td>Net Victories:</td>
                                <td>{{ number_format($data['net_victories']) }}</td>
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
                            @foreach($dominion->race->resources as $resourceKey)
                                @php
                                    $resource = OpenDominion\Models\Resource::where('key', $resourceKey)->first();
                                @endphp

                                <tr>
                                    <td>{{ $resource->name }}:</td>
                                    <td>{{ number_format($data['resource_' . $resourceKey]) }}
                                </tr>
                            @endforeach
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
                                <td>{{ number_format($data['morale']) }}%</td>
                            </tr>
                            <tr>
                                <td>
                                    <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getDrafteeHelpString( $dominion->race) }}">
                                        {{ $raceHelper->getDrafteesTerm($dominion->race) }}:
                                    </span>
                                </td>
                                <td>{{ number_format($data['military_draftees']) }}</td>
                            </tr>
                            <tr>
                                <td>
                                  <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString('unit1', $dominion->race) }}">
                                      {{ $dominion->race->units->get(0)->name }}:
                                  </span>
                                </td>
                                <td>{{ number_format($data['military_unit1']) }}</td>
                            </tr>
                            <tr>
                                <td>
                                  <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString('unit2', $dominion->race) }}">
                                      {{ $dominion->race->units->get(1)->name }}:
                                  </span>
                                </td>
                                <td>{{ number_format($data['military_unit2']) }}</td>
                            </tr>
                            <tr>
                                <td>
                                  <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString('unit3', $dominion->race) }}">
                                      {{ $dominion->race->units->get(2)->name }}:
                                  </span>
                                </td>
                                <td>{{ number_format($data['military_unit3']) }}</td>
                            </tr>
                            <tr>
                                <td>
                                  <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString('unit4', $dominion->race) }}">
                                      {{ $dominion->race->units->get(3)->name }}:
                                  </span>
                                </td>
                                <td>{{ number_format($data['military_unit4']) }}</td>
                            </tr>
                            <tr>
                                <td>Spies:</td>
                                <td>{{ number_format($data['military_spies']) }}</td>
                            </tr>
                            <tr>
                                <td>Wizards:</td>
                                <td>{{ number_format($data['military_wizards']) }}</td>
                            </tr>
                            <tr>
                                <td>ArchMages:</td>
                                <td>{{ number_format($data['military_archmages']) }}</td>
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
                  <a href="{{ route('dominion.insight.show', ['dominion' => $dominion]) }}" class="btn btn-primary btn-block">View current Insight</a>
                  <p class="text-muted">
                      <em>
                          Currently viewing insight archived at tick {{ $dominionInsight->round_tick }},
                          <span data-toggle="tooltip" data-placement="top" title="{{ $dominionInsight->created_at }} {{ isset($dominionInsight->source_dominion_id) ? 'by ' . OpenDominion\Models\Dominion::findOrFail($dominionInsight->source_dominion_id)->name : '' }}">
                              {{ number_format($selectedDominion->round->ticks - $dominionInsight->round_tick) . ' ' . str_plural('tick', $selectedDominion->round->ticks - $dominionInsight->round_tick) }} ago</a>.
                          </span>
                      </em>
                  </p>
            </div>
        </div>
    </div>
    <div class="col-sm-12 col-md-3">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-pray"></i> Deity</h3>
            </div>
            <div class="box-body">
                @if(!$data['deity'])
                    <p>This dominion is not currently devoted to a deity.</p>
                @else
                    <p>This dominion has been devoted to <b>{{ $data['deity'] }}</b> for {{ $data['deity_devotion'] . ' ' . str_plural('tick', $data['deity_devotion'])}}.</p>
                @endif
            </div>
        </div>

        @if($data['has_annexed'])
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="ra ra-castle-flag"></i> Annexed dominions</h3>
                </div>
                <div class="box-body">
                    <p>The Legion has annexed Barbarians, providing <b>{{ number_format($data['military_power_from_annexed_dominions']) }}</b> additional raw military power.</p>
                </div>
            </div>
        @endif

        @if($data['is_annexed'])
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="ra ra-castle-flag"></i> Annexation</h3>
                </div>
                <div class="box-body">
                    <p>This dominion was annexed, providing the Legion with <b>{{ number_format($data['annexed_military_power_provided']) }}</b> additional raw military power.</p>
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

            @if(count($data['spells']) > 0)

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
                        @foreach ($data['spells'] as $spellKey => $spellData)
                            @php
                                $spell = OpenDominion\Models\Spell::where('key', $spellKey)->first();
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
                                <td class="text-center">{{ $spellData['remaining'] }} / {{ $spellData['duration'] }} ticks</td>
                                <td class="text-center">{{ $spellData['caster_name'] }} (#{{ $spellData['caster_realm'] }})</td>
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
                                        $spanClass = 'text-muted';

                                        if($improvementPerkMultiplier = $data['improvements'][$perk->key]['rating'])
                                        {
                                            $spanClass = '';
                                        }
                                    @endphp

                                    <span class="{{ $spanClass }}">
                                    @if($data['improvements'][$perk->key]['rating'] > 0)
                                        +{{ number_format($data['improvements'][$perk->key]['rating'] * 100, 2) }}%
                                    @else
                                        {{ number_format($data['improvements'][$perk->key]['rating'] * 100, 2) }}%
                                    @endif

                                     {{ $improvementHelper->getImprovementPerkDescription($perk->key) }} <br></span>

                                @endforeach
                            </td>
                            <td class="text-center">{{ number_format($data['improvements'][$improvement->key]['points']) }}</td>
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
                                    $amount = $data['units']['training'][$unitType][$i];
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
                                {{ number_format($data['units']['home'][$unitType]) }}
                                ({{ number_format(array_sum($data['units']['training'][$unitType])) }})
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
                    @foreach ([1,2,3,4,'spies'] as $slot)
                        @php
                            if($slot == 'spies')
                            {
                                $unitType = 'spies';
                            }
                            else
                            {
                                $unitType = 'unit' . $slot;
                            }
                        @endphp
                        <tr>
                            <td>
                              <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString($unitType, $dominion->race) }}">
                                {{ $unitHelper->getUnitName($unitType, $dominion->race) }}
                              </span>
                            </td>
                            @for ($i = 1; $i <= 12; $i++)
                                @php
                                    $amount = $data['units']['returning'][$unitType][$i];
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
                                {{ number_format(array_sum($data['units']['returning'][$unitType])) }}
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
                <span class="pull-right">
                    Barren Land: <strong>{{ number_format($data['barren_land']) }}</strong> ({{ number_format((($data['barren_land'] / $data['total_land']) * 100), 2) }}%)
                </span>
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
                            $amount = $data['buildings']['constructed'][$building->key];
                        @endphp
                        <tr>
                            <td>
                                <span data-toggle="tooltip" data-placement="top" title="{!! $buildingHelper->getBuildingDescription($building) !!}">
                                    {{ $building->name }}
                                </span>
                            </td>
                            <td class="text-center">{{ number_format($amount) }}</td>
                            <td class="text-center">{{ number_format((($amount / $data['total_land']) * 100), 2) }}%</td>
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
                <span class="pull-right">Incoming Buildings: <strong>{{ number_format($data['constructing_land']) }}</strong> ({{ number_format((($data['constructing_land'] / $data['total_land']) * 100), 2) }}%)</span>
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
                                @if(isset($data['buildings']['constructing'][$building->key]))
                                    @php
                                        $amount = $data['buildings']['constructing'][$building->key][$i];
                                    @endphp
                                @else
                                    @php
                                        $amount = 0;
                                    @endphp
                                @endif
                                <td class="text-center">
                                    @if ($amount === 0)
                                        -
                                    @else
                                        {{ number_format($amount) }}
                                    @endif
                                </td>
                            @endfor
                            <td class="text-center">{{ isset($data['buildings']['constructing'][$building->key]) ? number_format(array_sum($data['buildings']['constructing'][$building->key])) : 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endcomponent
    </div>
</div>
<div class="row">

    <div class="col-sm-12 {{ $raceHelper->hasLandImprovements($dominion->race) ? 'col-md-5' : 'col-md-6' }} ">
        @component('partials.dominion.insight.box')

            @slot('title', 'Land')
            @slot('titleIconClass', 'ra ra-honeycomb')
            <table class="table">
                <colgroup>
                    <col>
                    <col width="100">
                    <col width="100">
                    <col width="100">
                </colgroup>
                <thead>
                    <tr>
                        <th>Land Type</th>
                        <th class="text-center">Number</th>
                        <th class="text-center">% of total</th>
                        <th class="text-center">Barren</th>
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
                            <td class="text-center">{{ number_format($data['land'][$landType]['amount']) }}</td>
                            <td class="text-center">{{ number_format($data['land'][$landType]['percentage'], 2) }}%</td>
                            <td class="text-center">{{ number_format($data['land'][$landType]['barren']) }}</td>

                            @if ($dominion->race->getPerkValue('defense_from_' . $landType))
                                <td class="text-center">
                                      +{{ number_format($data['land'][$landType]['landtype_defense']*100,2) }}% Defensive Power
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endcomponent
    </div>

    <div class="col-sm-12 {{ $raceHelper->hasLandImprovements($dominion->race) ? 'col-md-5' : 'col-md-6' }} ">
        @component('partials.dominion.insight.box')

            @slot('title', 'Incoming land breakdown')
            @slot('titleIconClass', 'fas fa-map-marked-alt')
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
                                    $amount = $data['land']['incoming'][$landType][$i];
                                @endphp
                                <td class="text-center">
                                    @if ($amount === 0)
                                        -
                                    @else
                                        {{ number_format($amount) }}
                                    @endif
                                </td>
                            @endfor
                            <td class="text-center">{{ number_format(array_sum($data['land']['incoming'][$landType])) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endcomponent
    </div>

    @if($raceHelper->hasLandImprovements($dominion->race))
        <div class="col-sm-12 col-md-2">
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fas fa-map-marked"></i> Land Perks</h3>
                </div>
                <div class="box-body">
                    @foreach ($data['land']['land_improvements'] as $landImprovement)
                        <ul>
                              <li>{{ $landImprovement }}</li>
                        </ul>
                    @endforeach
                </div>
            </div>
        </div>
    @endif


</div>
<div class="row">

    <div class="col-sm-12 col-md-6">
        @component('partials.dominion.insight.box')

            @slot('title', 'Advancements')
            @slot('titleIconClass', 'fa fa-flask')
            @slot('noPadding', true)

            @if(count($data['advancements']) > 0)
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
                        @foreach ($data['advancements'] as $advancement)
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

    {{--
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
    --}}
</div>

<div class="box-footer">
    <div class="pull-right">
        {{ $dominionInsights->links() }}
    </div>
</div>

@endif

@endsection
