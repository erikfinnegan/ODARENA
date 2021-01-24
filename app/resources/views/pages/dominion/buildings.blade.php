@extends('layouts.master')

@section('content')
@php
    $dominionBuildings = $buildingCalculator->getDominionBuildings($selectedDominion);
@endphp


<div class="row">
  <div class="col-sm-12 col-md-9">
      <div class="box box-primary">
          <div class="box-header with-border">
              <h3 class="box-title"><i class="fa fa-home"></i> Buildings</h3>
              <span class="label label-danger pull-right">Beta</span></a>
          </div>

          @foreach($selectedDominion->buildings as $building)
            {{ $building->name }}: {{ var_dump($building->pivot->owned) }}
          @endforeach

          <form action="{{ route('dominion.buildings') }}" method="post" role="form">
              @csrf
              <div class="box-body table-responsive no-padding">
                  <table class="table">
                      <colgroup>
                          <col width="100">
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
                                  @if($dominionBuildings->contains('building_id', $building->id))
                                      {{ $dominionBuildings->where('building_id', $building->id)->first()->owned }}
                                      <small>({{ number_format(($dominionBuildings->where('building_id', $building->id)->first()->owned / $landCalculator->getTotalLand($selectedDominion))*100,2) }}%)</small>
                                  @else
                                      0 <small>(0%)</small>
                                  @endif

                                  <br>
                                  @if($queueService->getConstructionQueueTotalByResource($selectedDominion, "building_{$building->key}"))
                                  ({{$queueService->getConstructionQueueTotalByResource($selectedDominion, "building_{$building->key}")}})
                                  @endif
                              </td>
                              <td>
                                  <input type="number" name="construct[building_{{ $building->key }}]" class="form-control text-center" placeholder="0" min="0" max="{{ $constructionCalculator->getMaxAfford($selectedDominion) }}" value="{{ old('construct.' . $building->key) }}" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
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
            </div>
            <div class="box-body">

                <p>This page is currently in <span class="label label-danger">beta</span> and shown for testing purposes only.</p>

                <p>The building perks shown here may not be accurate. They have no effect.</p>

                <p>Use the other <a href="{{ route('dominion.construct') }}">Buildings</a> page.</p>

            </div>
        </div>
    </div>

</div>

@endsection
