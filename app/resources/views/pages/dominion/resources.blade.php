@extends('layouts.master')
@section('title', 'Resources')

@section('content')

<div class="row">

    <div class="col-md-12 col-md-9">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-industry"></i> Production</h3>
            </div>
            <div class="box-body no-padding">
                <div class="row">
                    <div class="col-xs-12 col-sm-12">
                        <table class="table">
                            <colgroup>
                                <col width="150">
                            </colgroup>
                            <tbody>
                                  @foreach($selectedDominion->race->resources as $resourceKey)
                                      @php
                                          $resource = OpenDominion\Models\Resource::where('key', $resourceKey)->first();
                                      @endphp

                                    <tr>
                                        <td>
                                            <span data-toggle="tooltip" data-placement="top" title="{{ $resource->description }}">{{ $resource->name }}</span>
                                        </td>
                                        <td>
                                            @if ($production = $resourceCalculator->getProduction($selectedDominion, $resourceKey))
                                                <span data-toggle="tooltip" data-placement="top" title="Raw: {{ number_format($resourceCalculator->getProductionRaw($selectedDominion, $resourceKey)) }}">
                                                    <span class="text-green">{{ number_format($production) }}</span>
                                                </span>
                                            @else
                                                0
                                            @endif

                                            @if ($selectedDominion->getBuildingPerkValue($resourceKey . '_production_raw_random') > 0.00)
                                                <span class="text-muted" data-toggle="tooltip" data-placement="top" title="Production of this resource is wholly or partly random. Actual production is determined during at each tick.">*</span>
                                            @endif

                                            <small class="text-muted">({{ number_format(($resourceCalculator->getProductionMultiplier($selectedDominion, $resourceKey)-1) * 100,2) }}%)</small>


                                            @if ($consumption = $resourceCalculator->getConsumption($selectedDominion, $resourceKey))
                                                <span class="text-muted">
                                                    @php
                                                        $netConsumption = $resourceCalculator->getProduction($selectedDominion, $resourceKey) - $resourceCalculator->getConsumption($selectedDominion, $resourceKey);
                                                    @endphp
                                                    <br>
                                                    {{ $resourceHelper->getResourceConsumptionTerm($resourceKey) }}: <span class="text-red">{{ number_format($consumption) }}</span>

                                                    <br>
                                                    Net:
                                                    @if($netConsumption < 0)
                                                        <span class="text-red">{{ number_format($netConsumption) }}</span>
                                                    @else
                                                        <span class="text-green">+{{ number_format($netConsumption) }}</span>
                                                    @endif
                                                </span>
                                            @endif




                                            @if ($maxStorage = $resourceCalculator->getMaxStorage($selectedDominion, $resourceKey))
                                                <span class="text-muted">
                                                    <br>
                                                    Max storage: <span class="text-red">{{ number_format($maxStorage) }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach

                                <tr>
                                    <td>Experience points:</td>
                                    <td>
                                        @if ($xpGeneration = $productionCalculator->getXpGeneration($selectedDominion))
                                            <span data-toggle="tooltip" data-placement="top" title="Raw: {{ number_format($productionCalculator->getXpGenerationRaw($selectedDominion, $resourceKey)) }}">
                                                <span class="text-green">{{ number_format($xpGeneration) }}</span>
                                            </span>
                                        @else
                                            0
                                        @endif

                                        <small class="text-muted">({{ number_format(($productionCalculator->getXpGenerationMultiplier($selectedDominion)-1) * 100,2) }}%)</small>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-12 col-md-3">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Information</h3>
                <a href="{{ route('dominion.advisors.production') }}" class="pull-right"><span>Production Advisor</span></a>
            </div>
            <div class="box-body">
                  <table class="table">
                      <colgroup>
                          <col width="50%">
                          <col width="50%">
                      </colgroup>
                      <tbody>
                        <tr>
                          <td><span data-toggle="tooltip" data-placement="top" title="Total population:<br>Current / Available">Population:</span></td>
                          <td>{{ number_format($populationCalculator->getPopulation($selectedDominion)) }} / {{ number_format($populationCalculator->getMaxPopulation($selectedDominion)) }}</td>
                        </tr>
                        <tr>
                          <td>{{ str_plural($raceHelper->getPeasantsTerm($selectedDominion->race)) }}</span>:</td>
                          <td>

                              @if ($annexedPeasants = $populationCalculator->getAnnexedPeasants($selectedDominion))

                                  {{ number_format($selectedDominion->peasants) }} (+{{ number_format($annexedPeasants) }} annexed) / {{ number_format($populationCalculator->getMaxPopulation($selectedDominion) - $populationCalculator->getPopulationMilitary($selectedDominion)) }}

                              @else
                                  {{ number_format($selectedDominion->peasants) }} / {{ number_format($populationCalculator->getMaxPopulation($selectedDominion) - $populationCalculator->getPopulationMilitary($selectedDominion)) }}
                              @endif

                              @if ($selectedDominion->peasants_last_hour < 0)
                                  <span class="text-red">{{ number_format($selectedDominion->peasants_last_hour) }} last tick</span>
                              @elseif ($selectedDominion->peasants_last_hour > 0)
                                  <span class="text-green">+{{ number_format($selectedDominion->peasants_last_hour) }} last tick</span>
                              @endif
                          </td>
                        </tr>
                        @include('partials.dominion.housing')
                        <tr>
                          <td>Military:</td>
                          <td>{{ number_format($populationCalculator->getPopulationMilitary($selectedDominion)) }}</td>
                        </tr>

                        </tr>
                          <td><span data-toggle="tooltip" data-placement="top" title="Available jobs:<br>Peasants / Available Jobs">Jobs:</span></td></td>
                          <td>{{ number_format($populationCalculator->getPopulationEmployed($selectedDominion)) }} / {{ number_format($populationCalculator->getEmploymentJobs($selectedDominion)) }}</td>
                        </tr>
                        @php
                            $jobsNeeded = ($selectedDominion->peasants - $populationCalculator->getEmploymentJobs($selectedDominion))
                        @endphp
                        @if ($jobsNeeded < 0)
                        <tr>
                          <td><span data-toggle="tooltip" data-placement="top" title="How many peasants you need in order to fill all available jobs">Jobs available:</span></td>
                          <td>{{ number_format(abs($jobsNeeded)) }}</td>
                        </tr>
                        @else
                        <tr>
                          <td><span data-toggle="tooltip" data-placement="top" title="How many new jobs need to be created to provide employment for all currently unemployed peasants<br>Peasants - Jobs = Jobs Needed">Jobs needed:</span></td>
                          <td>{{ number_format(abs($jobsNeeded)) }}</td>
                        </tr>
                        @endif
                        {{--
                        <tr>
                          <td>Lost income:</td>
                          <td>{{ number_format(2.7 * abs($jobsNeeded) * $productionCalculator->getGoldProductionMultiplier($selectedDominion)) }} gold</td>
                        </tr>
                        <tr>
                          <td>Per {{ $raceHelper->getPeasantsTerm($selectedDominion->race) }}:</td>
                          <td>{{ number_format((2.7 * abs($jobsNeeded) * $productionCalculator->getGoldProductionMultiplier($selectedDominion)) / max(1, abs($jobsNeeded)), 3) }} gold</td>
                        </tr>
                        --}}
                      </tbody>
                  </table>

            </div>
        </div>
    </div>

</div>
@if(!$selectedDominion->race->getPerkValue('cannot_exchange'))
    <div class="row">
        <div class="col-sm-12 col-md-9">
            <div class="box box-primary">
                <div class="box-header with-border">
                  <h3 class="box-title"><i class="fas fa-exchange-alt"></i> Trade</h3>
                </div>
                <form action="{{ route('dominion.resources') }}" method="post" {{--class="form-inline" --}}role="form">
                    @csrf
                    <div class="box-body">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="row">
                                    <div class="form-group col-sm-6">
                                        <label for="source">Sell</label>
                                        <select name="source" id="source" class="form-control" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                            @foreach ($selectedDominion->race->resources as $resourceKey)

                                                @php
                                                    $resource = OpenDominion\Models\Resource::where('key', $resourceKey)->first();
                                                @endphp

                                                @if($resource->sell)
                                                    <option value="{{ $resourceKey }}" {{ $resourceKey  == $selectedDominion->most_recent_exchange_from ? 'selected' : ''}} >{{ $resource->name }}</option>
                                                @endif

                                            @endforeach

                                        </select>
                                    </div>
                                    <div class="form-group col-sm-6">
                                        <label for="target">Buy</label>
                                        <select name="target" id="target" class="form-control" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                            @foreach ($selectedDominion->race->resources as $resourceKey)

                                                @php
                                                    $resource = OpenDominion\Models\Resource::where('key', $resourceKey)->first();
                                                @endphp

                                                @if($resource->buy)
                                                    <option value="{{ $resourceKey }}" {{ $resourceKey  == $selectedDominion->most_recent_exchange_to ? 'selected' : ''}} >{{ $resource->name }}</option>}
                                                @endif

                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="row">
                                    <div class="form-group col-sm-3  text-center">
                                        <label for="amount" id="amountLabel">{{ reset($resources)['label'] }}</label>
                                        <input type="number"
                                               name="amount"
                                               id="amount"
                                               class="form-control text-center"
                                               value="{{ old('amount') }}"
                                               placeholder="0"
                                               min="0"
                                               max="{{ reset($resources)['max'] }}"
                                                {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                    </div>
                                    <div class="form-group col-sm-6 text-center">
                                        <label for="amountSlider">Amount</label>
                                        <input type="number"
                                               id="amountSlider"
                                               class="form-control slider"
                                               {{--value="0"--}}
                                               data-slider-value="0"
                                               data-slider-min="0"
                                               data-slider-max="{{ reset($resources)['max'] }}"
                                               data-slider-step="1"
                                               data-slider-tooltip="show"
                                               data-slider-handle="round"
                                               data-slider-id="red"
                                                {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                    </div>
                                    <div class="form-group col-sm-3 text-center">
                                        <label id="resultLabel">{{ reset($resources)['label'] }}</label>
                                        <p id="result" class="form-control-static text-center">0</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary" {{ $selectedDominion->isLocked() ? 'disabled' : null }} id="submit">
                            Trade
                        </button>
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
                    <p>You can exchange resources for other resources.</p>

                    <table class="table striped responsive">
                        <thead>
                            <tr>
                                <th>Resource</th>
                                <th>Buy</th>
                                <th>Sell</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($selectedDominion->race->resources as $resourceKey)
                                @php
                                    $resource = OpenDominion\Models\Resource::where('key', $resourceKey)->first();
                                @endphp

                                @if(($resource->buy + $resource->sell) != 0)
                                    <tr>
                                        <td>{{ $resource->name }}</td>
                                        <td>{!! number_format($resource->buy,2) ?: '&mdash;' !!}</td>
                                        <td>{{ number_format($resource->sell,2) }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>

                    @if($resourceCalculator->getExchangeRatePerkMultiplier($selectedDominion) != 1.0)
                        Perks are
                        @if($resourceCalculator->getExchangeRatePerkMultiplier($selectedDominion) > 1)
                            increasing
                        @else
                            decreasing
                        @endif
                        the sell price of your resources by <b>{{ number_format(($resourceCalculator->getExchangeRatePerkMultiplier($selectedDominion)-1) * 100, 2) }}%</b>.
                    @endif
                    <p>
                </div>
            </div>
        </div>

    </div>
@endif

@endsection

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/admin-lte/plugins/bootstrap-slider/slider.css') }}">
@endpush

@push('page-scripts')
    <script type="text/javascript" src="{{ asset('assets/vendor/admin-lte/plugins/bootstrap-slider/bootstrap-slider.js') }}"></script>
@endpush

@push('inline-scripts')
    <script type="text/javascript">
        (function ($) {
            const resources = JSON.parse('{!! json_encode($resources) !!}');

            // todo: let/const aka ES6 this
            var sourceElement = $('#source'),
                targetElement = $('#target'),
                amountElement = $('#amount'),
                amountLabelElement = $('#amountLabel'),
                amountSliderElement = $('#amountSlider'),
                resultLabelElement = $('#resultLabel'),
                resultElement = $('#result');

            function updateResources() {
                var sourceOption = sourceElement.find(':selected'),
                    sourceResourceType = _.get(resources, sourceOption.val()),
                    sourceAmount = Math.min(parseInt(amountElement.val()), _.get(sourceResourceType, 'max')),
                    targetOption = targetElement.find(':selected'),
                    targetResourceType = _.get(resources, targetOption.val()),
                    targetAmount = (Math.floor(sourceAmount * sourceResourceType['sell'] * targetResourceType['buy']) || 0);

                // Change labels
                amountLabelElement.text(sourceOption.text());
                resultLabelElement.text(targetOption.text());

                // Update amount
                amountElement
                    .attr('max', sourceResourceType['max'])
                    .val(sourceAmount);

                // Update slider
                amountSliderElement
                    .slider('setAttribute', 'max', sourceResourceType['max'])
                    .slider('setValue', sourceAmount);

                // Update target amount
                resultElement.text(targetAmount.toLocaleString());
            }

            sourceElement.on('change', updateResources);
            targetElement.on('change', updateResources);
            amountElement.on('change', updateResources);

            amountSliderElement.slider({
                formatter: function (value) {
                    return value.toLocaleString();
                }
            }).on('change', function (slideEvent) {
                amountElement.val(slideEvent.value.newValue).change();
            });

            updateResources();
        })(jQuery);
    </script>
@endpush

@push('page-scripts')
    <script type="text/javascript">
    $("form").submit(function () {
        // prevent duplicate form submissions
        $(this).find(":submit").attr('disabled', 'disabled');
    });
    </script>
@endpush
