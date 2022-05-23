@extends('layouts.master')
@section('title', "Round {$round->number} Quickstart")

@section('content')
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Round #{{ $round->number }} &mdash; <strong>{{ $round->name }}</strong> (Quickstart)</h3>
            <span class="pull-right">
                <a href="{{ route('round.register', $round) }}" class="btn btn-warning">Normal Registration</a>
            </span>
        </div>
        <form action="{{ route('round.quickstart', $round) }}" method="post" class="form-horizontal" role="form">
            @csrf

            <div class="box-body">

                    {{-- //Columns must be a factor of 12 (1,2,3,4,6,12) --}}
                    @php
                        $numOfCols = 3;
                        $rowCount = 0;
                        $bootstrapColWidth = 12 / $numOfCols;
                    @endphp

                    <div class="row">

                    @foreach($quickstarts as $quickstart)

                        @include('partials.register-quickstart')

                        @php
                            $rowCount++;
                        @endphp

                        @if($rowCount % $numOfCols == 0)
                            </div><div class="row">
                        @endif

                    @endforeach
                    </div>



                <!-- Dominion Name -->
                <div class="form-group">
                    <label for="dominion_name" class="col-sm-3 control-label">Dominion Name</label>
                    <div class="col-sm-6">
                        <input type="text" name="dominion_name" id="dominion_name" class="form-control" placeholder="Dominion Name" value="{{ old('dominion_name') }}" maxlength="50" required autofocus>
                        <p class="help-block">Your dominion name is shown when viewing and interacting with other players.</p>
                    </div>
                </div>


                <!-- Ruler Name -->
                <div class="form-group">
                    <label for="ruler_name" class="col-sm-3 control-label">Ruler Name</label>
                    <div class="col-sm-6">
                        <input type="text" name="ruler_name" id="ruler_name" class="form-control" placeholder="{{ Auth::user()->display_name }}" value="{{ old('ruler_name') }}" disabled>
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
                        @if($round->mode == 'deathmatch' or $round->mode == 'deathmatch-duration')
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="no_multis" required> <span class="label label-danger">Special rule:</span> This is deathmatch round and clause 3.2 of the Terms and Conditions does not apply. No multis are allowed this round.
                                </label>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Notice --}}
                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-6">
                        @include('partials.register-notice')
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
