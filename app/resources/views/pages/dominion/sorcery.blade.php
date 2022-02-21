@extends ('layouts.master')

@section('content')
    <div class="row">
        <div class="col-sm-12 col-md-9">
            <form action="{{ route('dominion.sorcery')}}" method="post" role="form">
            @csrf

            <!-- TARGET -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="ra ra-on-target"></i> Target</h3>
                            <small class="pull-right text-muted">
                                <span data-toggle="tooltip" data-placement="top" title="Wizards Per Acre (Wizard Ratio) on offense">WPA</span>: {{ number_format($militaryCalculator->getWizardRatio($selectedDominion, 'offense'),3) }},
                                <span data-toggle="tooltip" data-placement="top" title="Wizard Strength">WS</span>: {{ $selectedDominion->wizard_strength }}%
                            </small>
                        </div>

                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <select name="target_dominion" id="target_dominion" class="form-control select2" required style="width: 100%" data-placeholder="Select a target dominion" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                            <option></option>
                                            @foreach ($rangeCalculator->getDominionsInRange($selectedDominion) as $dominion)
                                                <option value="{{ $dominion->id }}"
                                                        data-land="{{ number_format($landCalculator->getTotalLand($dominion)) }}"
                                                        data-networth="{{ number_format($networthCalculator->getDominionNetworth($dominion)) }}"
                                                        data-percentage="{{ number_format($rangeCalculator->getDominionRange($selectedDominion, $dominion), 1) }}"
                                                        data-abandoned="{{ $dominion->isAbandoned() ? 1 : 0 }}">
                                                    {{ $dominion->name }} (#{{ $dominion->realm->number }}) - {{ $dominion->race->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ENHANCEMENT -->
            {{--
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="ra ra-mining-diamonds"></i> Enhancement</h3>
                        </div>
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <select name="resource" id="resource" class="form-control select2" required style="width: 100%" data-placeholder="Select a target dominion" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                            @foreach ($selectedDominion->race->resources as $resourceKey)
                                                @php
                                                    $resource = OpenDominion\Models\Resource::where('key', $resourceKey)->first();
                                                @endphp

                                                @if(!$selectedDominion->race->getPerkValue('no_' . $resource->key . '_theft'))
                                                    <option value="{{ $resource->id }}" {{ $selectedDominion->most_recent_theft_resource  == $resource->key ? 'selected' : '' }}>
                                                        {{ $resource->name }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            --}}

            <!-- RESOURCE -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fas fa-scroll"></i> Spell</h3>
                        </div>
                        <div class="box-body">
                          {{-- //Columns must be a factor of 12 (1,2,3,4,6,12) --}}
                        @php
                            $numOfCols = 3;
                            $rowCount = 0;
                            $bootstrapColWidth = 12 / $numOfCols;
                        @endphp

                        <div class="row">

                        @foreach($spells as $spell)
                            @php
                                $canCast = $spellCalculator->canCastSpell($selectedDominion, $spell);
                            @endphp
                            <div class="col-md-{{ $bootstrapColWidth }}">
                                <label class="btn btn-block">
                                <div class="box {!! $sorceryHelper->getSpellClassBoxClass($spell) !!}">
                                    <div class="box-header with-border">
                                        <input type="radio" name="spell" value="{{ $spell->id }}" autocomplete="off" {{ (old('spell') == $spell->id) ? 'checked' : null }} required>&nbsp;<h4 class="box-title">{{ $spell->name }}</h4>
                                        <span class="pull-right" data-toggle="tooltip" data-placement="top" title="{!! $sorceryHelper->getSpellClassDescription($spell) !!}"><i class="{!! $sorceryHelper->getSpellClassIcon($spell) !!}"></i></span>
                                    </div>

                                    <div class="box-body">
                                        <ul>
                                            @foreach($spellHelper->getSpellEffectsString($spell) as $effect)
                                                <li>{{ $effect }}</li>
                                            @endforeach
                                        </ul>

                                        <div class="box-footer">
                                            @include('partials.dominion.sorcery-spell-basics')
                                        </div>
                                    </div>
                                </div>
                                </label>
                            </div>

                            @php
                                $rowCount++;
                            @endphp

                            @if($rowCount % $numOfCols == 0)
                                </div><div class="row">
                            @endif

                        @endforeach
                        </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WIZARD UNITS -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fas fa-hat-wizard"></i> Wizard Strength</h3>
                        </div>
                        <div class="box-body">
                            <input type="number"
                                   id="amountSlider"
                                   class="form-control slider"
                                   name="wizard_strength"
                                   value="0"
                                   data-slider-value="{{ min($selectedDominion->wizard_strength, 4) }}"
                                   data-slider-min="0"
                                   data-slider-max="{{ $selectedDominion->wizard_strength }}"
                                   data-slider-step="1"
                                   data-slider-tooltip="show"
                                   data-slider-handle="round"
                                   data-slider-id="blue"
                                    {{ $selectedDominion->isLocked() ? 'disabled' : null }}>

                            <div class="box-footer">
                                <button type="submit"
                                        class="btn btn-danger"
                                        {{ $selectedDominion->isLocked() ? 'disabled' : null }}
                                        id="invade-button">
                                    <i class="fas fa-hand-sparkles"></i>
                                    Cast Spell
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-12 col-md-3">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Information</h3>
                </div>
                <div class="box-body">
                    <p>Select target, spell, and how much of your wizard strength you wish to use.</p>
                    <p>The amount of wizard strength you use determines how much mana you need </p>
                    <table class="table">
                        <colgroup>
                            <col>
                            <col width="80">
                            <col width="80">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Unit</th>
                                <th>Total</th>
                                <th>Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($selectedDominion->race->units as $unit)
                                @if($unitHelper->isUnitOffensiveWizard($unit))
                                    @php
                                        $unitSlot = $unit->slot;
                                    @endphp
                                    <tr>
                                        <td>{{ $unit->name }}</td>
                                        <td>{{ number_format($militaryCalculator->getTotalUnitsForSlot($selectedDominion, $unitSlot)) }}</td>
                                        <td>{{ number_format($selectedDominion->{"military_unit{$unitSlot}"}) }}</td>
                                    </tr>
                                @endif
                            @endforeach
                            @if(!$selectedDominion->race->getPerkValue('cannot_train_wizards'))
                                <tr>
                                    <td>Wizards</td>
                                    <td>{{ number_format($militaryCalculator->getTotalUnitsForSlot($selectedDominion, 'wizards')) }}</td>
                                    <td>{{ number_format($selectedDominion->military_wizards) }}</td>
                                </tr>
                            @endif
                            @if(!$selectedDominion->race->getPerkValue('cannot_train_archmages'))
                                <tr>
                                    <td>Archmages</td>
                                    <td>{{ number_format($militaryCalculator->getTotalUnitsForSlot($selectedDominion, 'archmages')) }}</td>
                                    <td>{{ number_format($selectedDominion->military_archmages) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>

                </div>
            </div>
        </div>

    </div>

    @push('page-styles')
        <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.bootstrap.css') }}">
    @endpush

    @push('page-scripts')
        <script type="text/javascript" src="{{ asset('assets/vendor/datatables/js/jquery.dataTables.js') }}"></script>
        <script type="text/javascript" src="{{ asset('assets/vendor/datatables/js/dataTables.bootstrap.js') }}"></script>
    @endpush

    @push('inline-scripts')
        <script type="text/javascript">
            (function ($) {
                $('#dominions-table').DataTable({
                    order: [[4, 'desc']],
                });
                //$('#clairvoyance-table').DataTable({
                //    order: [[2, 'desc']],
                //});
            })(jQuery);
        </script>
    @endpush

@endsection

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/admin-lte/plugins/bootstrap-slider/slider.css') }}">
@endpush

@push('page-scripts')
    <script type="text/javascript" src="{{ asset('assets/vendor/select2/js/select2.full.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('assets/vendor/admin-lte/plugins/bootstrap-slider/bootstrap-slider.js') }}"></script>
@endpush

@push('inline-scripts')
    <script type="text/javascript">
        (function ($) {
            $('#target_dominion').select2({
                templateResult: select2Template,
                templateSelection: select2Template,
            });
            $('#target_dominion').change(function(e) {
                var warStatus = $(this).find(":selected").data('war');
                if (warStatus == 1) {
                    $('.war-spell').removeClass('disabled');
                } else {
                    $('.war-spell').addClass('disabled');
                }
            });
            @if (session('target_dominion'))
                $('#spell_dominion').val('{{ session('spell_dominion') }}').trigger('change.select2').trigger('change');
            @endif
        })(jQuery);

        function select2Template(state) {
            if (!state.id) {
                return state.text;
            }

            const land = state.element.dataset.land;
            const percentage = state.element.dataset.percentage;
            const networth = state.element.dataset.networth;
            const war = state.element.dataset.war;
            const abandoned = state.element.dataset.abandoned;
            let difficultyClass;

            if (percentage >= 120) {
                difficultyClass = 'text-red';
            } else if (percentage >= 75) {
                difficultyClass = 'text-green';
            } else if (percentage >= 66) {
                difficultyClass = 'text-muted';
            } else {
                difficultyClass = 'text-gray';
            }

            warStatus = '';
            if (war == 1) {
                warStatus = '<div class="pull-left">&nbsp;<span class="text-red">WAR</span></div>';
            }

            abandonedStatus = '';
            if (abandoned == 1) {
                abandonedStatus = '<div class="pull-left">&nbsp;<span class="label label-warning">Abandoned</span></div>';
            }

            return $(`
                <div class="pull-left">${state.text}</div>
                ${abandonedStatus}
                <div class="pull-right">${land} acres <span class="${difficultyClass}">(${percentage}%)</span> - ${networth} networth</div>
                <div style="clear: both;"></div>
            `);
        }
    </script>


        <script type="text/javascript">
            (function ($) {
                const resources = JSON.parse('{!! json_encode([1,2,3,4]) !!}');

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
