@extends('layouts.master')

{{--
@section('page-header', 'Government')
--}}

@section('content')

<div class="row">
    <div class="col-sm-12 col-md-9">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-pray"></i> Deity</h3>
            </div>
            <div class="box-body">
                @if(!$selectedDominion->hasDeity())
                <form action="{{ route('dominion.government.deity') }}" method="post" role="form">
                    @csrf
                    <div class="row">
                        <div class="col-md-12">
                            <table class="table table-striped">
                                <colgroup>
                                    <col width="50">
                                    <col width="200">
                                    <col width="100">
                                    <col>
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Deity</th>
                                        <th>Range Multiplier</th>
                                        <th>Perks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($deityHelper->getDeitiesByRace($selectedDominion->race) as $deity)
                                    <tr>
                                        <td>
                                            @if($selectedDominion->hasPendingDeitySubmission() and $selectedDominion->getPendingDeitySubmission()->key == $deity->key)
                                                <span class="text-muted"><i class="fas fa-pray"></i></span>
                                            @else
                                                <input type="radio" name="key" id="{{ $deity->key }}" value="{{ $deity->key }}" {{ ($selectedDominion->isLocked() || $selectedDominion->hasPendingDeitySubmission()) ? 'disabled' : null }}>
                                            @endif
                                        </td>
                                        <td>
                                            <label for="{{ $deity->key }}">{{ $deity->name }}</label>
                                            @if($selectedDominion->hasPendingDeitySubmission() and $selectedDominion->getPendingDeitySubmission()->key == $deity->key)
                                            <br><span class="small text-muted"><strong>{{ $selectedDominion->getPendingDeitySubmissionTicksLeft() }}</strong> {{ str_plural('tick', $selectedDominion->getPendingDeitySubmissionTicksLeft()) }} left until devotion is in effect</span>
                                            @endif
                                        </td>
                                        <td>{{ $deity->range_multiplier }}x</td>
                                        <td>
                                            <ul>
                                                @foreach($deityHelper->getDeityPerksString($deity) as $effect)
                                                    <li>{{ ucfirst($effect) }}</li>
                                                @endforeach
                                            </ul>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-6">
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-block" {{ ($selectedDominion->isLocked() || $selectedDominion->hasPendingDeitySubmission()) ? 'disabled' : null }}>
                                Submit To This Deity
                            </button>
                        </div>
                    </div>
                </form>
                @elseif($deity = $selectedDominion->getDeity())
                <form id="renounce-deity" action="{{ route('dominion.government.renounce') }}" method="post" role="form">
                    @csrf
                    <div class="row">
                        <div class="col-md-12">
                            <form action="{{ route('dominion.government.deity') }}" method="post" role="form">
                            <p>You have been devoted to <strong>{{ $deity->name }}</strong> for {{ $selectedDominion->getDominionDeity()->duration }} ticks, granting you the following perks:</p>
                            <ul>
                                @foreach($deityHelper->getDeityPerksString($deity, $selectedDominion->getDominionDeity()) as $effect)
                                    <li>{{ ucfirst($effect) }}</li>
                                @endforeach
                                    <li>Range multiplier: {{ $deity->range_multiplier }}x</li>
                            </ul>
                            <p>If you wish to devote your dominion to another deity, you may renounce your devotion to {{ $deity->name }} below.</p>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-6">
                        <div class="form-group">
                            <select id="renounce-deity"  class="form-control">
                                <option value="0">Renounce devotion?</option>
                                <option value="1">Confirm renounce</option>
                            </select>
                            <button id="renounce-deity" type="submit" class="btn btn-danger btn-block" disabled {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                Renounce This Deity
                            </button>
                        </div>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-sm-12 col-md-3">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Information</h3>
            </div>
            <div class="box-body">
                <p>You can devote your dominion to a deity in exchange for some perks (good and bad). For every tick that you remain devoted to a deity, the perks are increased by 0.10% per tick to a maximum of +100%, when the perk values are doubled.</p>
                <p>It takes 48 ticks for a devotion to take effect. Your dominion can only be submitted to one deity at a time. However, you can renounce your deity to select a new one (which resets the ticks counter).</p>
                <p>The range multiplier is the maximum land size range the deity permits you to interact with, unless recently invaded, and takes effect immediately once you submit to a deity. A dominion with a wider range cannot take actions against a dominion with a more narrow range, unless the two ranges overlap.</p>
            </div>
        </div>
    </div>
</div>

@if ($selectedDominion->isMonarch())
<div class="row">
    <div class="col-sm-12 col-md-9">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-star"></i> Governor's Duties</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-12">
                        <form action="{{ route('dominion.government.realm') }}" method="post" role="form">
                            @csrf
                            <label for="realm_name">Realm Message</label>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <input type="text" class="form-control" name="realm_motd" id="realm_motd" placeholder="{{ $selectedDominion->realm->motd }}" maxlength="256" autocomplete="off" />
                                    </div>
                                </div>
                            </div>
                            <label for="realm_name">Realm Name</label>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <input type="text" class="form-control" name="realm_name" id="realm_name" placeholder="{{ $selectedDominion->realm->name }}" maxlength="64" autocomplete="off" />
                                    </div>
                                </div>
                            </div>
                            <label for="realm_name">Discord link</label> <small class="text-muted">(format: https://discord.gg/xxxxxxx)</small>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <input type="text" class="form-control" name="discord_link" id="discord_link" placeholder="{{ $selectedDominion->realm->discord_link }}" maxlength="64" autocomplete="off" />
                                    </div>
                                </div>
                            </div>
                            @if($realmCalculator->hasMonster($selectedDominion->realm))
                                <label for="realm_name">Percentage Contribution to the Monster</label>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <input type="number" class="form-control" name="realm_contribution" id="realm_contribution" placeholder="{{ $selectedDominion->realm->contribution }}" min=0 max=10 step=1 autocomplete="off" />
                                        </div>
                                    </div>
                                    <div class="col-xs-offset-6 col-xs-6 col-sm-offset-8 col-sm-4 col-lg-offset-10 col-lg-2">
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-primary btn-block" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                                Change
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @else

                                    <div class="col-xs-offset-6 col-xs-6 col-sm-offset-8 col-sm-4 col-lg-offset-10 col-lg-2">
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-primary btn-block" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                                Change
                                            </button>
                                        </div>
                                    </div>

                            @endif
                        </form>
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
                    <p><i class="fa fa-star fa-lg text-orange" title="Governor of The Realm"></i> <strong>Welcome, Governor!</strong></p>
                    <p>As the Governor, you have the power to declare war, revoke declarations of war against other realms, and moderate the
                      @if($selectedDominion->realm->alignment == 'evil')
                        Senate.
                      @elseif($selectedDominion->realm->alignment == 'good')
                        Parliament.
                      @elseif($selectedDominion->realm->alignment == 'independent')
                        Assembly.
                      @else
                        Council.
                      @endif

                      @if($realmCalculator->hasMonster($selectedDominion->realm))
                      <p>There is monster in your realm. You are tasked with deciding what percentage of everyone's food, lumber, and ore stockpiles shall be given to the monster each tick.</p>
                      @endif
            </div>
        </div>
    </div>
</div>
@endif

@if(!$selectedDominion->race->getPerkValue('cannot_vote'))
<div class="row">
    <div class="col-sm-12 col-md-9">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-ticket"></i> Vote for Governor</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-12">
                        <form action="{{ route('dominion.government.monarch') }}" method="post" role="form">
                            @csrf
                            <label for="monarch">Select your candidate</label>
                            <div class="row">
                                <div class="col-sm-8 col-lg-10">
                                    <div class="form-group">
                                        <select name="monarch" id="monarch" class="form-control select2" required style="width: 100%" data-placeholder="Select a dominion" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                            <option></option>
                                            @foreach ($dominions as $dominion)
                                                @if(!$dominion->race->getPerkValue('cannot_vote'))
                                                    <option value="{{ $dominion->id }}"
                                                            data-land="{{ number_format($landCalculator->getTotalLand($dominion)) }}"
                                                            data-networth="{{ number_format($networthCalculator->getDominionNetworth($dominion)) }}"
                                                            data-percentage="{{ number_format($rangeCalculator->getDominionRange($selectedDominion, $dominion), 1) }}">
                                                        {{ $dominion->name }} (#{{ $dominion->realm->number }})
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-xs-offset-6 col-xs-6 col-sm-offset-0 col-sm-4 col-lg-2">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary btn-block" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                            Vote
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <table class="table table-condensed">
                                    <tr><th>Dominion</th><th>Voted for</th></tr>
                                    @foreach ($dominions as $dominion)
                                        @if(!$dominion->race->getPerkValue('cannot_vote'))
                                            <tr>
                                                <td>
                                                    @if ($dominion->isMonarch())
                                                        <span class="text-red">{{ $dominion->name }}</span>
                                                    @else
                                                        {{ $dominion->name }}
                                                    @endif
                                                </td>
                                                @if ($dominion->monarchVote)
                                                    <td>{{ $dominion->monarchVote->name }}</td>
                                                @else
                                                    <td>N/A</td>
                                                @endif
                                            </tr>
                                        @endif
                                    @endforeach
                                </table>
                            </div>
                        </form>
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
                <p>Here you can vote for the governor of your realm. You can change your vote at any time.</p>
                <p>The governor has the power to declare war and peace as well as moderate the council.</p>
            </div>
        </div>
    </div>
</div>
@endif

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
