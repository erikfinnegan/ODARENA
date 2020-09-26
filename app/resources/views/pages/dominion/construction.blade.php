@extends('layouts.master')

@section('page-header', 'Construction')

@section('content')
@if (!(bool)$selectedDominion->race->getPerkValue('cannot_construct'))
    <div class="row">
        <div class="col-sm-12 col-md-9">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-home"></i> Construct Buildings</h3>
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
                    @php
                        $constructionMaterials = $raceHelper->getConstructionMaterials($selectedDominion->race);
                        $primaryCost = $constructionCalculator->getConstructionCostPrimary($selectedDominion);
                        $secondaryCost = $constructionCalculator->getConstructionCostSecondary($selectedDominion);
                        $multiplier = $constructionCalculator->getCostMultiplier($selectedDominion);

                        if(count($constructionMaterials) == 2)
                        {
                            $costString = 'Each building costs ' . number_format($primaryCost) . ' ' . $constructionMaterials[0] . ' and ' . number_format($secondaryCost) . ' ' . $constructionMaterials[1] . '.';
                        }
                        else
                        {
                            $costString = 'Each building costs ' . number_format($primaryCost) . ' ' . $constructionMaterials[0] . '.';
                        }

                    @endphp

                    <p>
                        {{ $costString }}

                        @if($multiplier !== 1)
                            Your construction costs are
                            @if($multiplier > 1)
                                increased
                            @else
                                decreased
                            @endif
                            by <strong>{{ number_format(abs(($multiplier-1)*100),2) }}%</strong>.
                        @endif
                    </p>

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
