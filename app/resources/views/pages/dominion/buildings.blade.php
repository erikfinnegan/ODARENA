@extends('layouts.master')

@section('page-header', 'Buildings')

@section('content')

@php dd($buildings)

@if (!(bool)$selectedDominion->race->getPerkValue('cannot_construct'))
    <div class="row">
        <div class="col-sm-12 col-md-9">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-home"></i> Buildings</h3>
                </div>
                <form action="{{ route('dominion.construct') }}" method="post" role="form">
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

                                @include('partials.dominion.construction.table')
                            </div>

                            <div class="col-md-12 col-lg-6">
                                @php
                                    /** @var \Illuminate\Support\Collection $buildingTypesLeft */
                                    $landTypesBuildingTypes = collect($buildingHelper->getBuildingTypesByRace($selectedDominion))->filter(function ($buildingTypes, $landType) {
                                        return in_array($landType, ['cavern', 'forest', 'hill', 'water'], true);
                                    });
                                @endphp

                                @include('partials.dominion.construction.table')
                            </div>

                        </div>
                    </div>
                    <div class="box-footer">
                            <button type="submit" class="btn btn-primary" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>Build</button>
                        <div class="pull-right">
                            You have {{ number_format($landCalculator->getTotalLand($selectedDominion)) }} {{ str_plural('acre', $landCalculator->getTotalLand($selectedDominion)) }} of land.
                        </div>
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
