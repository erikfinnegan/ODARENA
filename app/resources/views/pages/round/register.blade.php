@extends('layouts.master')

@section('page-header', "Round {$round->number} of ODARENA")

@section('content')

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Round #{{ $round->number }} &mdash; <strong>{{ $round->name }}</strong></h3>
        </div>
        <form action="{{ route('round.register', $round) }}" method="post" class="form-horizontal" role="form">
            @csrf

            <div class="box-body">

                <!-- Dominion Name -->
                <div class="form-group">
                    <label for="dominion_name" class="col-sm-3 control-label">Dominion Name</label>
                    <div class="col-sm-6">
                        <input type="text" name="dominion_name" id="dominion_name" class="form-control" placeholder="Dominion Name" value="{{ old('dominion_name') }}" maxlength="50" required autofocus>
                        <p class="help-block">Your dominion name is shown when viewing and interacting with other players.</p>
                    </div>
                </div>

                <!-- Title -->
                <div class="form-group">
                    <label for="title" class="col-sm-3 control-label">Ruler Title</label>
                    <div class="col-sm-6">
                        <select name="title" id="title" class="form-control select2" data-placeholder="Select a title" required>
                          <option></option>
                            @foreach ($titles as $title)

                            <option value="{{ $title->id }}">
                                  {{ $title->name }}
                                  (@foreach ($title->perks as $perk)
                                      @php
                                          $perkDescription = $titleHelper->getPerkDescriptionHtmlWithValue($perk);
                                      @endphp
                                          {!! $perkDescription['description'] !!} {!! $perkDescription['value']  !!}
                                  @endforeach)
                            </option>
                        @endforeach
                      </select>
                        <p class="help-block">This is the title you will go by. Select one that complements your intended strategy.</p>
                    </div>
                </div>

                <!-- Ruler Name -->
                <div class="form-group">
                    <label for="ruler_name" class="col-sm-3 control-label">Ruler Name</label>
                    <div class="col-sm-6">
                        <input type="text" name="ruler_name" id="ruler_name" class="form-control" placeholder="{{ Auth::user()->display_name }}" value="{{ old('ruler_name') }}">
                        <p class="help-block">If you leave it as default (<b>{{ Auth::user()->display_name }}</b>), you get 100 extra prestige for playing with your real display name.</p>
                    </div>
                </div>

                <!-- Race -->
                <div class="form-group">
                    <label for="faction" class="col-sm-3 control-label">Faction</label>
                    <div class="col-sm-6">
                        <select name="race" id="faction" class="form-control select2" data-placeholder="Select a faction" required>
                            <option></option>
                            <option value="random_any"
                                      data-current="{{ array_sum($countAlignment) }}">
                                  Random
                            </option>

                            <optgroup label="The Commonwealth">
                                <option value="random_good"
                                          data-current="{{ isset($countAlignment['good']) ? number_format($countAlignment['good']) : 0 }}"
                                          data-alignment="Commonwealth">
                                      Random Commonwealth
                                </option>
                                @foreach ($races->filter(function ($race) { return $race->playable && $race->alignment === 'good'; }) as $race)
                                    <option value="{{ $race->id }}"
                                          data-current="{{ isset($countRaces[$race->name]) ? number_format($countRaces[$race->name]) : 0 }}"
                                          data-maxPerRound="{{ $race->getPerkValue('max_per_round') }}"
                                          data-experimental="{{ $race->experimental }}"
                                          data-minRoundsPlayed="{{ $race->getPerkValue('min_rounds_played') ? number_format($race->getPerkValue('min_rounds_played')) : 0 }}">
                                        {{ $race->name }}
                                    </option>
                                @endforeach
                            </optgroup>

                            <optgroup label="The Empire">
                                <option value="random_evil"
                                          data-current="{{ isset($countAlignment['evil']) ? number_format($countAlignment['evil']) : 0 }}"
                                          data-alignment="Imperial">
                                      Random Imperial
                                </option>
                                @foreach ($races->filter(function ($race) { return $race->playable && $race->alignment === 'evil'; }) as $race)
                                    <option value="{{ $race->id }}"
                                          data-current="{{ isset($countRaces[$race->name]) ? number_format($countRaces[$race->name]) : 0 }}"
                                          data-maxPerRound="{{ $race->getPerkValue('max_per_round') }}"
                                          data-experimental="{{ $race->experimental }}"
                                          data-minRoundsPlayed="{{ $race->getPerkValue('min_rounds_played') ? number_format($race->getPerkValue('min_rounds_played')) : 0 }}">
                                        {{ $race->name }}
                                    </option>
                                @endforeach
                            </optgroup>

                            <optgroup label="The Independent">
                                <option value="random_independent"
                                          data-current="{{ isset($countAlignment['independent']) ? number_format($countAlignment['independent']) : 0 }}"
                                          data-alignment="Independent">
                                      Random Independent
                                </option>
                                @foreach ($races->filter(function ($race) { return $race->playable && $race->alignment === 'independent'; }) as $race)
                                    <option value="{{ $race->id }}"
                                          data-current="{{ isset($countRaces[$race->name]) ? number_format($countRaces[$race->name]) : 0 }}"
                                          data-maxPerRound="{{ $race->getPerkValue('max_per_round') }}"
                                          data-experimental="{{ $race->experimental }}"
                                          data-minRoundsPlayed="{{ $race->getPerkValue('min_rounds_played') ? number_format($race->getPerkValue('min_rounds_played')) : 0 }}">
                                        {{ $race->name }}
                                    </option>
                                @endforeach
                            </optgroup>

                      </select>
                        <p class="help-block">Consult <a href="{{ route('scribes.factions') }}" target="_blank">The Scribes</a> for details about each faction.</p>
                        <p class="help-block">Factions labelled <span class="label label-danger">Experimental</span> have significant changes that have been deemed at risk of being overpowered. If you play such a faction, you understand that if the community agrees (or if admin decides) that it is overpowered, the dominion will be locked. Not for violating any rules; but to keep the round fun and exciting. &mdash; We recommend playing at least one additional dominion, just in case.</p>
                    </div>
                </div>

                {{-- Terms and Conditions --}}
                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-6">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="terms" required> I have read, understood, and agree with the <a href="{{ route('legal.termsandconditions') }}">Terms and Conditions</a> and the <a href="{{ route('legal.privacypolicy') }}">Privacy Policy</a>
                            </label>
                        </div>
                    </div>
                </div>


                {{-- Notice --}}
                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-6">

                      @if($round->hasStarted())
                          @if($round->hasCountdown())
                              <p>This round has already started and ends {{ $round->end_date->format('l, jS \o\f F Y \a\t H:i') }}.</p>
                          @else
                              <p>This round has already started!</p>
                          @endif
                          <p>When you register, you start with 96 protection ticks. Make the most of them. Once you have used them all, you leave protection immediately.</p>
                          <p>Regularly scheduled ticks do not count towards your dominion while you are in protection.</p>
                          <p>To help you get going, you will get 1% extra starting resources for every hour since the round started (max +100%).</p>

                          @if ($discordInviteLink = config('app.discord_invite_link'))
                          <p>If you need any help or just want to chat, come join us on <a href="{{ $discordInviteLink }}" target="_blank">Discord</a>.</p>
                          @endif

                      @else
                          <p>The round starts on {{ $round->start_date->format('l, jS \o\f F Y \a\t H:i') }}. The target land size is {{ number_format($round->land_target) }} acres. Once a dominion has reached that, a countdown of 12 hours begins, and then the round ends.</p>
                          <p>When you register, you start with 96 protection ticks. Make the most of them. Once you have used them all, you leave protection immediately.</p>
                          <p>Regularly scheduled ticks do not count towards your dominion while you are in protection.</p>

                            @if ($discordInviteLink = config('app.discord_invite_link'))
                                <p>In the meantime, come join us on <a href="{{ $discordInviteLink }}" target="_blank">Discord</a>.</p>
                            @endif

                      @endif

                      <p>Head over to the <a href="https://sim.odarena.com/" target="_blank">ODARENA Simulator</a> if you want to sim protection. Click <a href="https://lounge.odarena.com/2020/02/24/odarena-sim/" target="_blank">here</a> to read about how the sim works.</p>

                    </div>
                </div>

            </div>

            <div class="box-footer">
                <button type="submit" class="btn btn-primary">Register</button>
            </div>

        </form>
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
            $('#title').select2({
                templateResult: select2Template,
                templateSelection: select2Template,
            });
            @if (session('title'))
                $('#title').val('{{ session('title') }}').trigger('change.select2').trigger('change');
            @endif
        })(jQuery);


        (function ($) {
            $('#faction').select2({
                templateResult: select2Template,
                templateSelection: select2Template,
            });
            @if (session('faction'))
                $('#faction').val('{{ session('faction') }}').trigger('change.select2').trigger('change');
            @endif
        })(jQuery);

        function select2Template(state)
        {
            if (!state.id)
            {
                return state.text;
            }

            const current = state.element.dataset.current;
            const experimental = state.element.dataset.experimental;
            const maxPerRound = state.element.dataset.maxperround;

            experimentalStatus = ''
            if (experimental == 1) {
                experimentalStatus = '&nbsp;<div class="pull-left">&nbsp;<span class="label label-danger">Experimental</span></div>';
            }

            maxPerRoundStatus = ''
            if (maxPerRound == 1) {
                maxPerRoundStatus = '&nbsp;<div class="pull-left">&nbsp;<span class="label label-warning">Max ' + maxPerRound + ' per round</span></div>';
            }

            var xId = state.id;

            if(xId.startsWith("random") && state.id !== 'random_any')
            {
                const alignment = state.element.dataset.alignment;
                return $(`
                    <div class="pull-left">${state.text}</div>
                    ${experimentalStatus}
                    ${maxPerRoundStatus}
                    <div class="pull-right">${current} total dominion(s) in the ${alignment} realm</div>
                    <div style="clear: both;"></div>
                `);
            }

            if(state.id == 'random_any')
            {
                const alignment = state.element.dataset.alignment;
                return $(`
                    <div class="pull-left">${state.text}</div>
                    ${experimentalStatus}
                    ${maxPerRoundStatus}
                    <div class="pull-right">${current} total dominion(s) registered</div>
                    <div style="clear: both;"></div>
                `);
            }

            return $(`
                <div class="pull-left">${state.text}</div>
                ${experimentalStatus}
                ${maxPerRoundStatus}
                <div class="pull-right">${current} dominion(s)</div>
                <div style="clear: both;"></div>
            `);
        }
    </script>
@endpush
