@extends('layouts.master')

@section('page-header', 'Daily Bonus')

@section('content')
    <div class="row">

        <div class="col-sm-12 col-md-9">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-plus"></i> Daily Bonus</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-xs-6 text-center">
                            <form action="{{ route('dominion.bonuses.land') }}" method="post" role="form">
                                @csrf
                                <button type="submit" name="land" class="btn btn-primary btn-lg" {{ $selectedDominion->isLocked() || $selectedDominion->daily_land || $selectedDominion->protection_ticks > 0 || !$selectedDominion->round->hasStarted() ? 'disabled' : null }}>
                                    <i class="ra ra-scroll-unfurled ra-fw"></i>
                                    Claim Daily Land Bonus
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box">
                <div class="box-body">
                    <p>While you're here, consider supporting the project in one (or more) of the following ways:</p>

                    <div class="row">
                        @if ($discordInviteLink = config('app.discord_invite_link'))
                            <div class="col-md-4 text-center">
                                <h4>Join us on Discord</h4>
                                <p>
                                    <a href="{{ $discordInviteLink }}" target="_blank">
                                        <img src="{{ asset('assets/app/images/join-the-discord.png') }}" alt="Join the Discord" class="img-responsive" style="max-width: 200px; margin: 0 auto;">
                                    </a>
                                </p>
                                <p>Discord is a chat program that I use for ODARENA's game announcements, its development, and generic banter with other players and people interested in the project.</p>
                                <p>Feel free to join us and chat along!</p>
                            </div>
                        @endif

                        @if ($patreonPledgeLink = config('app.patreon_pledge_link'))
                            <div class="col-md-4 text-center">
                                <h4>Become a Patron</h4>
                                <p>
                                    <a href="{{ $patreonPledgeLink }}" data-patreon-widget-type="become-patron-button">Become a Patron!</a>
                                </p>
                                <p>ODARENA is and always will be free to play. There will be no advertising or microtransactions.</p>
                                <p>Maintaining the game is a side project and costs are taken out of pocket. Any support of any kind through Patreon is therefore appreciated.</p>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="box-footer">
                    <p>Thank you for your attention, and please enjoy playing ODARENA!</p>
                </div>
            </div>
        </div>

        <div class="col-sm-12 col-md-3">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Information</h3>
                </div>
                <div class="box-body">
                    <p>The Daily Land Bonus instantly gives you some barren acres of {{ str_plural($selectedDominion->race->home_land_type) }}. You have a 0.50% chance to get 100 acres, otherwise you get a random amount between 10 and 40 acres</p>

                    @if ($selectedDominion->protection_ticks > 0 or !$selectedDominion->round->hasStarted())
                    <p><strong>You cannot claim daily bonus while you are in protection or before the round has started.</strong></p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@if (config('app.patreon_pledge_link'))
    @push('page-scripts')
        <script async src="https://c6.patreon.com/becomePatronButton.bundle.js"></script>
    @endpush

    @push('inline-styles')
        <style type="text/css">
            .patreon-widget {
                width: 176px !important;
            }
        </style>
    @endpush
@endif
