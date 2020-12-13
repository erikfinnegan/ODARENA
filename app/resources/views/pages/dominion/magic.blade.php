@extends ('layouts.master')

@section('page-header', 'Magic')

@section('content')
    <div class="row">

        <div class="col-sm-12 col-md-9">
            <div class="row">

                <div class="col-md-4">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="ra ra-fairy-wand"></i> Self Spells</h3>
                        </div>
                        <form action="{{ route('dominion.magic') }}" method="post" role="form">
                            @csrf

                            <div class="box-body">
                                @foreach ($spellHelper->getSelfSpells($selectedDominion)->chunk(2) as $spells)
                                    <div class="row">
                                        @foreach ($spells as $spell)
                                            <div class="col-xs-6 col-sm-6 col-md-12 col-lg-6 text-center">
                                                @php
                                                    $canCast = $spellCalculator->canCast($selectedDominion, $spell['key']);
                                                    $cooldownHours = $spellCalculator->getSpellCooldown($selectedDominion, $spell['key']);
                                                    $isActive = $spellCalculator->isSpellActive($selectedDominion, $spell['key']);
                                                    $buttonStyle = ($isActive ? 'btn-success' : 'btn-primary');
                                                @endphp
                                                <div class="form-group">
                                                    <button type="submit" name="spell" value="{{ $spell['key'] }}" class="btn {{ $buttonStyle }} btn-block" title="{{ $spell['description'] }}" {{ $selectedDominion->isLocked() || !$canCast || $cooldownHours ? 'disabled' : null }}>
                                                        {{ $spell['name'] }}
                                                    </button>
                                                    <p style="margin: 5px 0;">{{ $spell['description'] }}</p>
                                                    <p>
                                                        <small>
                                                            @if ($isActive)
                                                                {{ $spellCalculator->getSpellDuration($selectedDominion, $spell['key']) }} of {{ $spell['duration'] }} ticks remaining,
                                                            @else
                                                                Lasts {{ $spell['duration'] }} ticks,
                                                            @endif


                                                            @if ($cooldownHours)
                                                                <span class="text-danger">{{ $cooldownHours }} hour recharge remaining</span>,
                                                            @elseif (isset($spell['cooldown']))
                                                                @if ($spell['cooldown'] > 0)
                                                                    <span class="text-danger">{{ $spell['cooldown'] }} hours to recharge</span>,
                                                                @endif
                                                            @endif
                                                            @if ($canCast)
                                                                <span class="text-success">
                                                            @else
                                                                <span class="text-danger">
                                                            @endif
                                                              {{ number_format($spellCalculator->getManaCost($selectedDominion, $spell['key'])) }} mana
                                                            </span>
                                                            @if (isset($spell['races']))
                                                                <br/>{{ $selectedDominion->race->name }} only
                                                            @endif
                                                        </small>
                                                    </p>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>

                        </form>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="ra ra-burning-embers"></i> Offensive Spells</h3>
                        </div>

                        @if ($protectionService->isUnderProtection($selectedDominion))
                            <div class="box-body">
                                You are currently under protection for <b>{{ $selectedDominion->protection_ticks }}</b> {{ str_plural('tick', $selectedDominion->protection_ticks) }} and may not cast any offensive spells during that time.
                            </div>
                        @else
                            <form action="{{ route('dominion.magic') }}" method="post" role="form">
                                @csrf

                                <div class="box-body">

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="target_dominion">Select a target</label>
                                                <select name="target_dominion" id="target_dominion" class="form-control select2" required style="width: 100%" data-placeholder="Select a target dominion" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                                    <option></option>
                                                    @foreach ($rangeCalculator->getDominionsInRange($selectedDominion) as $dominion)
                                                        <option value="{{ $dominion->id }}"
                                                                data-land="{{ number_format($landCalculator->getTotalLand($dominion)) }}"
                                                                data-networth="{{ number_format($networthCalculator->getDominionNetworth($dominion)) }}"
                                                                data-percentage="{{ number_format($rangeCalculator->getDominionRange($selectedDominion, $dominion), 1) }}"
                                                                data-war="{{ ($selectedDominion->realm->war_realm_id == $dominion->realm->id || $dominion->realm->war_realm_id == $selectedDominion->realm->id) ? 1 : 0 }}">
                                                            {{ $dominion->name }} (#{{ $dominion->realm->number }}) - {{ $dominion->race->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <label>Information Gathering Spells</label>
                                        </div>
                                    </div>

                                    @foreach ($spellHelper->getInfoOpSpells()->chunk(4) as $spells)
                                        <div class="row">
                                            @foreach ($spells as $spell)
                                                @php
                                                    $canCast = $spellCalculator->canCast($selectedDominion, $spell['key']);
                                                @endphp
                                                <div class="col-xs-6 col-sm-3 col-md-6 col-lg-3 text-center">
                                                    <div class="form-group">
                                                        <button type="submit" name="spell" value="{{ $spell['key'] }}" class="btn btn-primary btn-block" {{ $selectedDominion->isLocked() || !$canCast ? 'disabled' : null }}>
                                                            {{ $spell['name'] }}
                                                        </button>
                                                        <p>{{ $spell['description'] }}</p>
                                                        <small>
                                                            @if ($canCast)
                                                                <span class="text-success">
                                                            @else
                                                                <span class="text-danger">
                                                            @endif
                                                              {{ number_format($spellCalculator->getManaCost($selectedDominion, $spell['key'])) }} mana
                                                            </span>
                                                        </small>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach

                                    <div class="row">
                                        <div class="col-md-12">
                                            <label>Offensive Spells</label>
                                        </div>
                                    </div>

                                    @foreach ($spellHelper->getBlackOpSpells($selectedDominion)->chunk(4) as $spells)
                                        <div class="row">
                                            @foreach ($spells as $spell)
                                                @php
                                                    $canCast = $spellCalculator->canCast($selectedDominion, $spell['key']);
                                                @endphp
                                                <div class="col-xs-6 col-sm-3 col-md-6 col-lg-3 text-center">
                                                    <div class="form-group">
                                                        <button type="submit" name="spell" value="{{ $spell['key'] }}" class="btn btn-primary btn-block" {{ $selectedDominion->isLocked() || !$canCast ? 'disabled' : null }}>
                                                            {{ $spell['name'] }}
                                                        </button>
                                                        <p>{{ $spell['description'] }}</p>
                                                        <small>
                                                            @if ($canCast)
                                                                <span class="text-success">
                                                            @else
                                                                <span class="text-danger">
                                                            @endif
                                                              {{ number_format($spellCalculator->getManaCost($selectedDominion, $spell['key'])) }} mana
                                                            </span>

                                                        </small>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </form>
                        @endif

                    </div>
                </div>

            </div>
        </div>

        <div class="col-sm-12 col-md-3">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Information</h3>
                    <a href="{{ route('dominion.advisors.magic') }}" class="pull-right">Magic Advisor</a>
                </div>
                <div class="box-body">
                    <p>Here you may cast spells which temporarily benefit your dominion or hinder opposing dominions. You can also perform information gathering operations with magic.</p>
                    <p>Self-spells have a duration after which they expire and need to be cast again. Information Gathering spells never expire and </p>
                    <p>Any obtained data after successfully casting an information gathering spell gets posted to the <a href="{{ route('dominion.op-center') }}">Op Center</a> for your realmies.</p>
                    <p>Casting spells spends some wizard strength, but it regenerates a bit every tick.</p>

                    <ul>
                      <li>Mana: {{ number_format($selectedDominion->resource_mana) }}</li>
                      <li>Wizard Strength:  {{ floor($selectedDominion->wizard_strength) }}%</li>
                      <li>Wizard Ratio (offense): {{ number_format($militaryCalculator->getWizardRatio($selectedDominion, 'offense'), 3) }}</li>
                      <li>Spell damage modifier: {{ number_format((1-$spellDamageCalculator->getDominionHarmfulSpellDamageModifier($selectedDominion, null, null, null))*100, 2) }}% <span class="text-muted">(base, may vary by spell)</span></li>
                    </ul>

                    <a href="{{ route('scribes.magic') }}"><span><i class="ra ra-scroll-unfurled"></i> Read more about Magic in the Scribes.</span></a>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2/css/select2.min.css') }}">
@endpush

@push('page-scripts')
    <script type="text/javascript" src="{{ asset('assets/vendor/select2/js/select2.full.min.js') }}"></script>
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
                $('#target_dominion').val('{{ session('target_dominion') }}').trigger('change.select2').trigger('change');
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

            return $(`
                <div class="pull-left">${state.text}</div>
                ${warStatus}
                <div class="pull-right">${land} acres <span class="${difficultyClass}">(${percentage}%)</span> - ${networth} networth</div>
                <div style="clear: both;"></div>
            `);
        }
    </script>
@endpush
