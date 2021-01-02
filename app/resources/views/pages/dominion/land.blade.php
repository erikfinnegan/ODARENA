@extends('layouts.master')

@section('page-header', 'Land')

@section('content')
<div class="row">
    <div class="col-sm-12 col-md-9">
        <div class="row">
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-refresh"></i> Re-zone Land</h3>
                    </div>
                    <form action="{{ route('dominion.rezone') }}" method="post" role="form">
                        @csrf
                        <div class="box-body table-responsive no-padding">
                            <table class="table">
                                <colgroup>
                                    <col>
                                    <col width="100">
                                    <col width="100">
                                    <col width="100">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Land type</th>
                                        <th class="text-center">Barren</th>
                                        <th class="text-center">Rezone From</th>
                                        <th class="text-center">Rezone Into</th>
                                    </tr>
                                </thead>
                                @foreach ($landCalculator->getBarrenLandByLandType($selectedDominion) as $landType => $amount)
                                    <tr>
                                        <td>
                                          {{ ucfirst($landType) }}
                                          @if ($landType === $selectedDominion->race->home_land_type)
                                              <small class="text-muted"><span title="This is the land type where {{ ($selectedDominion->race->name) }} constructs home buildings."><i class="fa fa-home"></span></i></small>
                                          @endif
                                        </td>
                                        <td class="text-center">{{ number_format($amount) }}</td>
                                        <td class="text-center">
                                            <input name="remove[{{ $landType }}]" type="number"
                                                   class="form-control text-center" placeholder="0" min="0"
                                                   max="{{ $amount }}"
                                                   value="{{ old('remove.' . $landType) }}" {{ $selectedDominion->isLocked() || $amount == 0 ? 'disabled' : null }}>
                                        </td>
                                        <td class="text-center">
                                            <input name="add[{{ $landType }}]" type="number"

                                                   class="form-control text-center" placeholder="0" min="0"
                                                   max="{{ $rezoningCalculator->getMaxAfford($selectedDominion) }}"
                                                   value="{{ old('add.' . $landType) }}" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                        <div class="box-footer">
                            <button class="btn btn-primary" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>Re-Zone</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="ra ra-telescope"></i> Explore Land</h3>
                    </div>
                    <form action="{{ route('dominion.explore') }}" method="post" role="form">
                        @csrf
                        <div class="box-body table-responsive no-padding">
                            <table class="table">
                                <colgroup>
                                    <col>
                                    <col width="100">
                                    <col width="100">
                                    <col width="100">
                                    <col width="100">
                                    @if ($selectedDominion->race->getPerkValue('land_improvements'))
                                    <col width="200">
                                    @endif
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Terrain</th>
                                        <th class="text-center">Owned</th>
                                        <th class="text-center">Barren</th>
                                        <th class="text-center">Incoming</th>
                                        <th class="text-center">Explore For</th>
                                        @if ($selectedDominion->race->getPerkValue('land_improvements'))
                                            <th class="text-center">Bonus</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $totalIncomingLand = 0;
                                    @endphp
                                    @foreach ($landHelper->getLandTypes() as $landType)
                                        <tr>
                                            <td>
                                                {{ ucfirst($landType) }}
                                                @if ($landType === $selectedDominion->race->home_land_type)
                                                    <small class="text-muted"><span title="This is the land type where {{ ($selectedDominion->race->name) }} constructs home buildings."><i class="fa fa-home"></span></i></small>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                {{ number_format($selectedDominion->{'land_' . $landType}) }}
                                                <small>
                                                    ({{ number_format((($selectedDominion->{'land_' . $landType} / $landCalculator->getTotalLand($selectedDominion)) * 100), 2) }}%)
                                                </small>
                                            </td>
                                            <td class="text-center">{{ number_format($landCalculator->getTotalBarrenLandByLandType($selectedDominion, $landType)) }}</td>
                                            <td class="text-center">{{ number_format($queueService->getExplorationQueueTotalByResource($selectedDominion, "land_{$landType}") + $queueService->getInvasionQueueTotalByResource($selectedDominion, "land_{$landType}")) }}</td>
                                            <td class="text-center">
                                                <input type="number" name="explore[land_{{ $landType }}]" class="form-control text-center" placeholder="0" min="0" max="{{ $explorationCalculator->getMaxAfford($selectedDominion) }}" value="{{ old('explore.' . $landType) }}" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                            </td>
                                            @if ($selectedDominion->race->getPerkValue('land_improvements'))
                                                <td class="text-center">
                                                      @if($landType == 'plain')
                                                          +{{ number_format($landImprovementCalculator->getOffensivePowerBonus($selectedDominion)*100,2) }}% Offensive Power
                                                      @elseif($landType == 'mountain')
                                                          +{{ number_format($landImprovementCalculator->getPlatinumProductionBonus($selectedDominion)*100,2) }}% Platinum Production
                                                      @elseif($landType == 'swamp')
                                                          +{{ number_format($landImprovementCalculator->getWizardPowerBonus($selectedDominion)*100,2) }}% Wizard Strength
                                                      @elseif($landType == 'forest')
                                                          +{{ number_format($landImprovementCalculator->getPopulationBonus($selectedDominion)*100,2) }}% Max Population
                                                      @elseif($landType == 'hill')
                                                          +{{ number_format($landImprovementCalculator->getDefensivePowerBonus($selectedDominion)*100,2) }}% Defensive Power
                                                      @elseif($landType == 'water')
                                                          +{{ number_format($landImprovementCalculator->getFoodProductionBonus($selectedDominion)*100,2) }}% Food and Boat Production
                                                      @endif
                                                </td>
                                            @endif
                                        </tr>
                                        @php
                                            $totalIncomingLand += $queueService->getExplorationQueueTotalByResource($selectedDominion, "land_{$landType}");
                                            $totalIncomingLand += $queueService->getInvasionQueueTotalByResource($selectedDominion, "land_{$landType}")
                                        @endphp
                                    @endforeach
                                    {{--
                                        <tr>
                                            <td><em>Total</em></td>
                                            <td class="text-center"><em>{{ number_format($landCalculator->getTotalLand($selectedDominion)) }}</em></td>
                                            <td class="text-center"><em>{{ number_format($landCalculator->getTotalBarrenLand($selectedDominion)) }} <small class="text-muted">({{ number_format(($landCalculator->getTotalBarrenLand($selectedDominion) / $landCalculator->getTotalLand($selectedDominion))*100)}}%)</span></em></td>
                                            <td class="text-center"><em>{{ number_format($totalIncomingLand) }}</em></td>
                                            <td></td>
                                        </tr>
                                    --}}
                                </tbody>
                            </table>
                        </div>
                        <div class="box-footer">
                          @if ((bool)$selectedDominion->race->getPerkValue('cannot_explore'))
                              <p><strong>Your faction is not able to explore.</strong></p>
                          @elseif ($guardMembershipService->isEliteGuardMember($selectedDominion))
                              <p><strong>As a member of the Warriors League, you cannot explore.</strong></p>
                          @elseif ($spellCalculator->isSpellActive($selectedDominion, 'rainy_season'))
                              <p><strong>Your cannot explore during the Rainy Season.</strong></p>
                          @elseif ($spellCalculator->isSpellActive($selectedDominion, 'stasis'))
                              <p><strong>You cannot explore while you are in stasis.</strong></p>
                          @elseif ($selectedDominion->resource_food <= 0 and $selectedDominion->race->getPerkMultiplier('food_consumption') != -1)
                              <p><strong>Due to starvation, you cannot explore until you have more food.</strong></p>
                              <p><strong>Go to the <a href="{{ route('dominion.exchange') }}">Exchange</a> to convert other resources to food or <a href="{{ route('dominion.construct') }}">build more farms</a>.</strong></p>
                          @else
                            <button type="submit" class="btn btn-primary" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>Explore</button>
                          @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-12 col-md-3">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Information</h3>
                <a href="{{ route('dominion.advisors.land') }}" class="pull-right">Land Advisor</a>
            </div>
            <div class="box-body">
                <p>Land rezoning is the art of converting barren land of one type into another type. Rezoning is instant.</p>

                <p>Each acre costs {{ number_format($rezoningCalculator->getRezoningCost($selectedDominion)) }} {{ $rezoningCalculator->getRezoningMaterial($selectedDominion) }} to rezone.</p>

                @if (1-$rezoningCalculator->getCostMultiplier($selectedDominion) !== 0)
                  <p>Your rezoning costs are

                  @if (1-$rezoningCalculator->getCostMultiplier($selectedDominion) > 0)
                    decreased
                  @else
                    increased
                  @endif

                   by <strong>{{ number_format((abs(1-$rezoningCalculator->getCostMultiplier($selectedDominion)))*100, 2) }}%</strong>.</p>

                @endif

                <p>You have {{ number_format($landCalculator->getTotalBarrenLand($selectedDominion)) }} {{ str_plural('acre', $landCalculator->getTotalBarrenLand($selectedDominion)) }} of barren land
                  and you can afford to re-zone <b>{{ number_format($rezoningCalculator->getMaxAfford($selectedDominion)) }} {{ str_plural('acre', $rezoningCalculator->getMaxAfford($selectedDominion)) }}</b>.</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-9">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-plus"></i> Daily Bonus</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-xs-6">
                        <p>The Daily Land Bonus instantly gives you some barren acres of <strong>{{ $selectedDominion->race->home_land_type }}</strong>.</p>
                        <p>You have a 0.50% chance to get 100 acres, and a 99.50% chance to get a random amount between 10 and 40 acres.</p>
                        @if ($selectedDominion->protection_ticks > 0 or !$selectedDominion->round->hasStarted())
                        <p><strong>You cannot claim daily bonus while you are in protection or before the round has started.</strong></p>
                        @endif
                    </div>
                    <div class="col-xs-6">
                        <form action="{{ route('dominion.bonuses.land') }}" method="post" role="form">
                            @csrf
                            <button type="submit" name="land" class="btn btn-primary btn-lg" {{ $selectedDominion->isLocked() || $selectedDominion->daily_land || $selectedDominion->protection_ticks > 0 || !$selectedDominion->round->hasStarted() ? 'disabled' : null }}>
                                <i class="ra ra-compass ra-fw"></i>
                                Claim Daily Land Bonus
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-9">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa fa-users"></i> Support ODARENA</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <script type='text/javascript' src='https://ko-fi.com/widgets/widget_2.js'></script><script type='text/javascript'>kofiwidget2.init('Support ODARENA on Ko-fi', '#dd4b39', 'P5P526XK1');kofiwidget2.draw();</script>
                    </div>
                    <div class="col-md-9">
                        <p>In addition to be free open source software, ODARENA is and always will be free to play. There will be no advertising and your data will never be used for anything other than game statistics.</p>
                        <p>While not much, maintaining the game is a side project and costs are taken out of pocket. Any support of any kind is highly appreciated.</p>
                    </div>
                </div>


            </div>
        </div>
    </div>
</div>
@endsection
