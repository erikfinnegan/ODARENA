@extends('layouts.master')

@if ((bool)$selectedDominion->race->getPerkValue('tissue_improvement'))
  @section('page-header', 'Feeding')
@else
  @section('page-header', 'Improvements')
@endif

@section('content')

@if ((bool)$selectedDominion->race->getPerkValue('tissue_improvement'))
<div class="row">

    <div class="col-sm-12 col-md-9">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-arrow-up fa-fw"></i> Feeding</h3>
            </div>
            <form action="{{ route('dominion.improvements') }}" method="post" role="form">
                @csrf
                <div class="box-body table-responsive no-padding">
                    <table class="table">
                        <colgroup>
                            <col width="150">
                            <col width="100">
                            <col>
                            <col width="100">
                        </colgroup>
                        <tbody>
                          @foreach ($improvementHelper->getImprovementTypes($selectedDominion->race->name) as $improvementType)
                              <tr>
                                  <td>
                                      <i class="ra ra-{{ $improvementHelper->getImprovementIcon($improvementType) }} ra-fw" data-toggle="tooltip" data-placement="top" title="{{ $improvementHelper->getImprovementHelpString($improvementType) }}"></i>
                                      {{ ucfirst($improvementType) }}
                                      {!! $improvementHelper->getImprovementImplementedString($improvementType) !!}
                                  </td>
                                  <td class="text-center">
                                      <input type="number" name="improve[{{ $improvementType }}]" class="form-control text-center" placeholder="0" min="0" size="8" style="min-width:5em;" value="{{ old('improve.' . $improvementType) }}" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                  </td>
                                  <td>
                                      {{ sprintf(
                                          $improvementHelper->getImprovementRatingString($improvementType),
                                          number_format($improvementCalculator->getImprovementMultiplierBonus($selectedDominion, $improvementType) * 100, 2)
                                      ) }}
                                  </td>
                                  <td class="text-center">{{ number_format($selectedDominion->{'improvement_' . $improvementType}) }}</td>
                              </tr>
                          @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="box-footer">
                    <div class="pull-right">
                      <select name="resource" class="form-control" style="display:none;">
                      <option value="food" {{ $selectedResource  === 'food' ? 'selected' : ''}}>Food</option>
                      </select>
                    </div>
                    <button type="submit" class="btn btn-primary" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>Feed</button>
                </div>
            </form>
        </div>
    </div>
</div>

@elseif ((bool)$selectedDominion->race->getPerkValue('cannot_improve_castle'))
    <div class="row">
        <div class="col-sm-12 col-md-9">
            <div class="box box-primary">
                <p>Your race does not have a castle and therefore cannot use castle improvements.</p>
            </div>
        </div>
    </div>
@else
    <div class="row">

        <div class="col-sm-12 col-md-9">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-arrow-up fa-fw"></i> Improvements</h3>
                </div>

                <form action="{{ route('dominion.improvements') }}" method="post" role="form">
                    @csrf
                    <div class="box-body table-responsive no-padding">
                        <table class="table">
                            <colgroup>
                                <col width="150">
                                <col width="150">
                                <col>
                                <col width="100">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Part</th>
                                    <th class="text-center">Invest</th>
                                    <th>Rating</th>
                                    <th class="text-center">Invested</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($improvementHelper->getImprovementTypes($selectedDominion->race->name) as $improvementType)
                                    <tr>
                                        <td>
                                            <i class="ra ra-{{ $improvementHelper->getImprovementIcon($improvementType) }} ra-fw" data-toggle="tooltip" data-placement="top" title="{{ $improvementHelper->getImprovementHelpString($improvementType) }}"></i>
                                            {{ ucfirst($improvementType) }}
                                            {!! $improvementHelper->getImprovementImplementedString($improvementType) !!}
                                        </td>
                                        <td class="text-center">
                                            <input type="number" name="improve[{{ $improvementType }}]" class="form-control text-center" placeholder="0" min="0" size="8" style="min-width:5em;" value="{{ old('improve.' . $improvementType) }}" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                        </td>
                                        <td>
                                            {{ sprintf(
                                                $improvementHelper->getImprovementRatingString($improvementType),
                                                number_format($improvementCalculator->getImprovementMultiplierBonus($selectedDominion, $improvementType) * 100, 2)
                                            ) }}
                                        </td>
                                        <td class="text-center">{{ number_format($selectedDominion->{'improvement_' . $improvementType}) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer">
                        <div class="pull-right">
                            <select name="resource" class="form-control">
                                @if ((bool)$selectedDominion->race->getPerkValue('can_invest_mana'))
                                <option value="mana" {{ $selectedResource  === 'mana' ? 'selected' : ''}}>Mana</option>
                                @else
                                  @if ((bool)$selectedDominion->race->getPerkValue('can_invest_soul'))
                                  <option value="soul" {{ $selectedResource  === 'soul' ? 'selected' : ''}}>Soul</option>
                                  @endif
                                <option value="gems" {{ $selectedDominion->most_recent_improvement_resource  === 'gems' ? 'selected' : ''}}>Gems</option>
                                <option value="lumber" {{ $selectedDominion->most_recent_improvement_resource  === 'lumber' ? 'selected' : ''}}>Lumber</option>
                                <option value="ore" {{ $selectedDominion->most_recent_improvement_resource  === 'ore' ? 'selected' : ''}}>Ore</option>
                                <option value="platinum" {{ $selectedDominion->most_recent_improvement_resource === 'platinum' ? 'selected' : ''}}>Platinum</option>
                                @endif
                            </select>
                        </div>

                        <div class="pull-right" style="padding: 7px 8px 0 0">
                            Resource to invest:
                        </div>

                        <button type="submit" class="btn btn-primary" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>Invest</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-sm-12 col-md-3">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Information</h3>
                </div>
                <div class="box-body">
                    <p>Invest resources in your castle to improve certain parts of your dominion. Improvements take effect immediately.</p>

                    @if($improvementCalculator->getMasonriesBonus($selectedDominion) > 0 or $improvementCalculator->getTechBonus($selectedDominion) > 0)
                    <p>
                      @if($improvementCalculator->getMasonriesBonus($selectedDominion) > 0 and $improvementCalculator->getTechBonus($selectedDominion) == 0)
                        Masonries
                      @elseif($improvementCalculator->getTechBonus($selectedDominion) > 0 and $improvementCalculator->getMasonriesBonus($selectedDominion) == 0)
                        Advancements
                      @elseif($improvementCalculator->getTechBonus($selectedDominion) > 0 and $improvementCalculator->getMasonriesBonus($selectedDominion) > 0)
                        Masonries and Advancements
                      @endif

                      are increasing your castle improvements by <strong>{{ number_format(($improvementCalculator->getTechBonus($selectedDominion) + $improvementCalculator->getMasonriesBonus($selectedDominion))*100,2) }}%</strong>.
                    </p>
                    @endif

                    <p>Resources invested are converted to points.</p>
                    <table class="table">
                        <colgroup>
                            <col width="25%">
                            <col width="25%">
                            <col width="25%">
                            <col width="25%">
                        </colgroup>
                        <thead>
                          <tr>
                            <th>Resource</th>
                            <th>Points each</th>
                            <th>Points each (raw)</th>
                            <th>Modifier</th>
                          </tr>
                        </thead>
                      <tbody>
                      @if ((bool)$selectedDominion->race->getPerkValue('can_invest_mana'))
                        <tr>
                          <td>Mana</td>
                          <td>{{ $improvementCalculator->getResourceWorth('mana', $selectedDominion) }}</td>
                          <td>{{ $improvementCalculator->getResourceWorthRaw('mana', $selectedDominion) }}</td>
                          <td>{{ $improvementCalculator->getResourceWorthMultipler('mana', $selectedDominion)*100 }}%</td>
                        </tr>
                      @endif
                      @if ((bool)$selectedDominion->race->getPerkValue('can_invest_soul'))
                        <tr>
                          <td>Souls</td>
                          <td>{{ $improvementCalculator->getResourceWorth('soul', $selectedDominion) }}</td>
                          <td>{{ $improvementCalculator->getResourceWorthRaw('soul', $selectedDominion) }}</td>
                          <td>{{ $improvementCalculator->getResourceWorthMultipler('soul', $selectedDominion)*100 }}%</td>
                        </tr>
                      @endif
                        <tr>
                          <td>Gems</td>
                          <td>{{ $improvementCalculator->getResourceWorth('gems', $selectedDominion) }}</td>
                          <td>{{ $improvementCalculator->getResourceWorthRaw('gems', $selectedDominion) }}</td>
                          <td>{{ $improvementCalculator->getResourceWorthMultipler('gems', $selectedDominion)*100 }}%</td>
                        </tr>
                        <tr>
                          <td>Lumber</td>
                          <td>{{ $improvementCalculator->getResourceWorth('lumber', $selectedDominion) }}</td>
                          <td>{{ $improvementCalculator->getResourceWorthRaw('lumber', $selectedDominion) }}</td>
                          <td>{{ $improvementCalculator->getResourceWorthMultipler('lumber', $selectedDominion)*100 }}%</td>
                        </tr>
                        <tr>
                          <td>Ore</td>
                          <td>{{ $improvementCalculator->getResourceWorth('ore', $selectedDominion) }}</td>
                          <td>{{ $improvementCalculator->getResourceWorthRaw('ore', $selectedDominion) }}</td>
                          <td>{{ $improvementCalculator->getResourceWorthMultipler('ore', $selectedDominion)*100 }}%</td>
                        </tr>
                        <tr>
                          <td>Platinum</td>
                          <td>{{ $improvementCalculator->getResourceWorth('platinum', $selectedDominion) }}</td>
                          <td>{{ $improvementCalculator->getResourceWorthRaw('platinum', $selectedDominion) }}</td>
                          <td>{{ $improvementCalculator->getResourceWorthMultipler('platinum', $selectedDominion)*100 }}%</td>
                        </tr>
                      </tbody>
                    </table>

                </div>
            </div>
        </div>

    </div>
@endif
@endsection
