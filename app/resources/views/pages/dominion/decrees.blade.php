@extends('layouts.master')
@section('title', 'Decrees')

{{--
@section('page-header', 'Government')
--}}

@section('content')

<div class="row">
    @foreach($decrees as $decree)
        <div class="col-sm-12 col-md-9">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title"><i class="fas fa-gavel fw-fw"></i> {{ ($decree->name) }}</h4>
                </div>
                <div class="box-body">
                    <div class="col-xs-12">
                        <div class="row">
                            <div class="col-lg-12"><i class="far fa-file-alt fa-fw"></i> <b>Description</b></div>
                            <div class="col-lg-12">{{ $decree->description }}</div>
                        </div>
                    </div>                    
                    @foreach($decree->states as $decreeState)
                        <div class="row">
                            @php
                                #$isIssued = $decreeState->isIssued();
                                #$canIssue = $spellCalculator->canCastSpell($selectedDominion, $spell, $resourceCalculator->getAmount($selectedDominion, 'mana'));
                            @endphp

                            <input type="hidden" name="decreeState" value="{{ $decreeState->id }}">
                            
                            <div class="box-body">
                                <div class="col-sm-12 col-md-3">
                                    <div class="box-header">
                                        <button type="button" class="btn btn-block btn-primary">
                                            {{ $decreeState->name }}
                                        </button>
                                    </div>
                                </div>

                                <div class="col-sm-12 col-md-9">
                                    <div class="box-body">
                                        Perks go here.
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

    <div class="col-sm-12 col-md-3">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Information</h3>
            </div>
            <div class="box-body">
                <p>As the ruler of your dominion, you can enact and revoke decrees.<p>
                <p>Once enacted or revoked, the effect is immediate. If revoked, it will be available to be enacted again.</p>
                <p>Some decrees have a cooldown period before they can be revoked.</p>
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

@push('inline-scripts')
     <script type="text/javascript">
         (function ($) {
             $('#renounce-deity select').change(function() {
                 var confirm = $(this).val();
                 if (confirm == "1") {
                     $('#renounce-deity button').prop('disabled', false);
                 } else {
                     $('#renounce-deity button').prop('disabled', true);
                 }
             });
         })(jQuery);
     </script>
 @endpush

    <script type="text/javascript">
        (function ($) {
            $('#monarch').select2({
                templateResult: select2Template,
                templateSelection: select2Template,
            });
            $('#realm_number').select2();
        })(jQuery);

        function select2Template(state) {
            if (!state.id) {
                return state.text;
            }

            const land = state.element.dataset.land;
            const percentage = state.element.dataset.percentage;
            const networth = state.element.dataset.networth;
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

            return $(`
                <div class="pull-left">${state.text}</div>
                <div class="pull-right">${land} acres <span class="${difficultyClass}">(${percentage}%)</span> - ${networth} networth</div>
                <div style="clear: both;"></div>
            `);
        }
    </script>
@endpush
