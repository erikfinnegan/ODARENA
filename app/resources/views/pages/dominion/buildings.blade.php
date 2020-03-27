@extends('layouts.master')

@section('page-header', 'Buildings')

@section('content')

@if (!(bool)$selectedDominion->race->getPerkValue('cannot_construct'))
    <div class="row">
      <div class="col-sm-12 col-md-9">
          <div class="box box-primary">
              <div class="box-header with-border">
                  <h3 class="box-title"><i class="fa fa-home"></i> Buildings</h3>
              </div>
              <form action="{{ route('dominion.buildings') }}" method="post" role="form">
                  @csrf
                  <div class="box-body table-responsive no-padding">
                      <table class="table">
                          <colgroup>
                              <col width="100">
                              <col width="100">
                              <col width="100">
                              <col>
                          </colgroup>
                          <thead>
                              <tr>
                                  <th>Land</th>
                                  <th>Building</th>
                                  <th>Owned</th>
                                  <th>Construct</th>
                                  <th>Description</th>
                              </tr>
                          </thead>
                          @foreach ($buildings as $building)
                              @if(count(array_diff($building->excluded_races, [$selectedDominion->race->name])) !== 0 or count(array_diff($building->exclusive_races, [$selectedDominion->race->name])) == 0)
                                  <tr class="text-normal">
                                      <td>
                                          {{ $building->land_type }}
                                      </td>
                                      <td>
                                          {{ $building->name }}
                                      </td>
                                      <td>
                                          0
                                          <small>(0%)</small> <br>
                                          (0)
                                      </td>
                                      <td>
                                          [input]
                                      </td>
                                      <td>
                                          {!! $buildingHelper->getBuildingDescription($building) !!}
                                      </td>

                                  </tr>
                              @endif
                          @endforeach
                      </table>
                  </div>
                  <div class="box-footer">
                      <button type="submit" class="btn btn-primary">Build</button>
                  </div>
              </form>
          </div>
      </div>

        <div class="col-sm-12 col-md-3">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Information</h3>
                    <a href="{{ route('dominion.advisors.construct') }}" class="pull-right">Construction Advisor</a>
                </div>
                <div class="box-body">
                    <p>Here you can construct buildings. Each building takes <b>12 ticks</b> to complete.</p>
                    <p>Each building costs
                    @if ($selectedDominion->race->getPerkValue('construction_cost_only_mana'))
                      {{ number_format($constructionCalculator->getManaCost($selectedDominion)) }} mana.
                    @elseif ($selectedDominion->race->getPerkValue('construction_cost_only_food'))
                      {{ number_format($constructionCalculator->getFoodCost($selectedDominion)) }} food.
                    @else
                      {{ number_format($constructionCalculator->getPlatinumCost($selectedDominion)) }} platinum and {{ number_format($constructionCalculator->getLumberCost($selectedDominion)) }} lumber.
                    @endif
                    </p>



                    @if (1-$constructionCalculator->getCostMultiplier($selectedDominion) !== 0)
                      <p>Bonuses are

                      @if (1-$constructionCalculator->getCostMultiplier($selectedDominion) > 0)
                        decreasing
                      @else
                        increasing
                      @endif

                       your construction costs by <strong>{{ number_format((abs(1-$constructionCalculator->getCostMultiplier($selectedDominion)))*100, 2) }}%</strong>.</p>

                    @endif

                    <p>You have {{ number_format($landCalculator->getTotalBarrenLand($selectedDominion)) }} {{ str_plural('acre', $landCalculator->getTotalBarrenLand($selectedDominion)) }} of barren land
                      and can afford to construct <strong>{{ number_format($constructionCalculator->getMaxAfford($selectedDominion)) }} {{ str_plural('building', $constructionCalculator->getMaxAfford($selectedDominion)) }}</strong>.</p>

                    @if ($selectedDominion->discounted_land)
                    <p>Additionally, {{ $selectedDominion->discounted_land }} acres from invasion can be built at 25% reduced cost.</p>
                    @endif

                    <p>You may also <a href="{{ route('dominion.destroy') }}">destroy buildings</a> if you wish.</p>
                </div>
            </div>
        </div>

    </div>
@else
<div class="row">
    <div class="col-sm-12 col-md-9">
        <div class="box box-primary">
            <p>Your faction is not able to construct buildings.</p>
        </div>
    </div>
</div>
@endif
@endsection
