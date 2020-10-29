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
                          @foreach ($buildingHelper->getBuildingsByRace($selectedDominion->race) as $building)
                              <tr class="text-normal">
                                  <td>
                                      {{ ucwords($building->land_type) }}
                                  </td>
                                  <td>
                                      {{ $building->name }}
                                  </td>
                                  <td>
                                      0
                                      <small>(0%)</small>
                                      <br>
                                      @if($queueService->getConstructionQueueTotalByResource($selectedDominion, "building_{$building->key}"))
                                      ({{$queueService->getConstructionQueueTotalByResource($selectedDominion, "building_{$building->key}")}})
                                      @endif
                                  </td>
                                  <td>
                                      <input type="number" inputmode="numeric" pattern="[0-9]*" name="construct[building_{{ $building->key }}]" class="form-control text-center" placeholder="0" min="0" max="{{ $constructionCalculator->getMaxAfford($selectedDominion) }}" value="{{ old('construct.' . $building->key) }}" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                  </td>
                                  <td>
                                      {!! $buildingHelper->getBuildingDescription($building) !!}
                                  </td>

                              </tr>
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
                    @php
                        $constructionMaterials = $raceHelper->getConstructionMaterials($selectedDominion->race);
                        $primaryCost = $constructionCalculator->getConstructionCostPrimary($selectedDominion);
                        $secondaryCost = $constructionCalculator->getConstructionCostSecondary($selectedDominion);

                        if(count($constructionMaterials) == 2)
                        {
                            $costString = 'Each building costs ' . number_format($primaryCost) . ' ' . $constructionMaterials[0] . ' and ' . number_format($secondaryCost) . ' ' . $constructionMaterials[1] . '.';
                        }
                        else
                        {
                            $costString = 'Each building costs ' . number_format($primaryCost) . ' ' . $constructionMaterials[0] . '.';
                        }



                    @endphp

                    <p>{{ $costString }}</p>

                    <p>You have {{ number_format($landCalculator->getTotalBarrenLand($selectedDominion)) }} {{ str_plural('acre', $landCalculator->getTotalBarrenLand($selectedDominion)) }} of barren land
                      and can afford to construct <strong>{{ number_format($constructionCalculator->getMaxAfford($selectedDominion)) }} {{ str_plural('building', $constructionCalculator->getMaxAfford($selectedDominion)) }}</strong>.</p>

                    <p>You may also <a href="{{ route('dominion.destroy') }}">destroy buildings</a> if you wish.</p>

                    <a href="{{ route('scribes.construction') }}"><span><i class="ra ra-scroll-unfurled"></i> Read more about Buildings in the Scribes.</span></a>
                </div>
            </div>
        </div>

    </div>
@else
<div class="row">
    <div class="col-sm-12 col-md-9">
        <div class="box box-primary">
            <p>{{ $selectedDominion->race->name }} cannot construct buildings.</p>
        </div>
    </div>
</div>
@endif
@endsection
