@extends('layouts.master')

{{--
@section('page-header', 'Destroy Buildings')
--}}

@section('content')
@if (!(bool)$selectedDominion->race->getPerkValue('cannot_construct'))
    <div class="row">

        <div class="col-sm-12 col-md-9">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="ra ra-demolish"></i> Destroy Buildings</h3>
                </div>
                <form action="{{ route('dominion.destroy') }}" method="post" role="form">
                    @csrf
                    <div class="box-body no-padding">
                        <div class="row">

                            <div class="col-md-12 col-lg-6">
                                @php
                                    /** @var \Illuminate\Support\Collection $buildingTypesLeft */
                                    $landTypesBuildingTypes = collect($buildingHelper->getBuildingTypesByRace($selectedDominion))->filter(function ($buildingTypes, $landType) {
                                        return in_array($landType, ['plain', 'mountain', 'swamp'], true);
                                    });
                                @endphp

                                @include('partials.dominion.destroy.table')
                            </div>

                            <div class="col-md-12 col-lg-6">
                                @php
                                    /** @var \Illuminate\Support\Collection $buildingTypesLeft */
                                    $landTypesBuildingTypes = collect($buildingHelper->getBuildingTypesByRace($selectedDominion))->filter(function ($buildingTypes, $landType) {
                                        return in_array($landType, ['cavern', 'forest', 'hill', 'water'], true);
                                    });
                                @endphp

                                @include('partials.dominion.destroy.table')
                            </div>

                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-danger" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>Destroy</button>

                        <span class="pull-right">
                        <a href="{{ route('dominion.construct') }}" class="btn btn-primary">Cancel</a>
                        </span>
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
                    <p><b>Warning</b>: You are about to destroy buildings to reclaim barren land.</p>
                    <p>Any gold and lumber used to construct any destroyed buildings <b>will be lost</b>.</p>
                    <p>Destroying buildings processes <b>instantly</b>.</p>
                </div>
                <div class="box-footer">
                </div>
            </div>
        </div>

    </div>

@else
    <div class="row">
        <div class="col-sm-12 col-md-9">
            <div class="box box-primary">
                <p>𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣</p>
                <p style="opacity:0.8;">𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣</p>
                <p style="opacity:0.6;">𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣</p>
                <p style="opacity:0.4;">𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣</p>
                <p style="opacity:0.2;">𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣</p>
                <p>Void cannot construct buildings.</p>
                <p style="opacity:0.2;">𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣</p>
                <p style="opacity:0.4;">𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣</p>
                <p style="opacity:0.6;">𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣</p>
                <p style="opacity:0.8;">𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣</p>
                <p>𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣𒄩𒍑𒉣</p>
            </div>
        </div>
    </div>
@endif
@endsection
